<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\TempUploadedUnit;
use App\Models\Unit;
use App\Models\UnitCategory;
use App\Models\UnitTopology;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bulk upload of units from the Units tab of a landlord's client page
 * (property.clients.show). Same two-stage staging flow as
 * TenantBulkUploadController — read that class's docblock for the full
 * rationale, this one mirrors it exactly:
 *
 *   1. import() — an uploaded template is parsed and dumped into the
 *      temp_uploaded_units staging table. Only Unit Number/Usage Type
 *      (required) and unit-number availability are checked at this point —
 *      a failing row is still staged, just flagged with push_error.
 *   2. pushToMain() — every staged, not-yet-pushed row for a property is run
 *      through the real validation + Unit::create() logic (the same rules
 *      UnitsController::store() and BulkUploadController::processUnits()
 *      use). Rows that fail stay staged with push_error set, ready to be
 *      fixed via updateUpload() and pushed again; rows that succeed flip
 *      pushed_to_main and drop out of the staging listing.
 *
 * Deliberately synchronous throughout, same as the tenant upload — no queue
 * jobs, batch capped at MAX_ROWS so a run stays fast enough to run inline.
 */
class UnitBulkUploadController extends Controller
{
    private const MAX_ROWS = 100;

    /** Column order shared by the template and the importer — keep in sync. */
    private const COLUMNS = [
        'Unit Number', 'Unit Name', 'Usage Type', 'Unit Condition', 'Topology',
        'Category', 'Rent Amount', 'Description', 'VAT (0/1)', 'Initial Water Meter Reading',
    ];

    private const USAGE_TYPE_MAP = ['commercial' => 1, 'residential' => 2, 'hybrid' => 3];

    /**
     * Excel template for a single property. Not pre-filled with data (unlike
     * the tenant template's vacant-unit rows) since there's nothing to
     * choose from when creating brand new units — just the header, two
     * example rows, and reference lists for Topology/Category.
     */
    public function downloadTemplate(Request $request)
    {
        try {
            $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
            ]);

            $property = Property::findOrFail($request->property_id);
            $topologies = UnitTopology::orderBy('topology_name')->pluck('topology_name');
            $categories = UnitCategory::orderBy('category_name')->pluck('category_name');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Units');

            $sheet->fromArray(self::COLUMNS, null, 'A1');
            $lastCol = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
            ]);

            $sampleTopology1 = $topologies->get(0, '1 Bedroom');
            $sampleTopology2 = $topologies->get(1, '2 Bedroom');
            $sampleCategory1 = $categories->get(0, 'Standard');
            $sampleCategory2 = $categories->get(1, 'Premium');
            $sheet->fromArray([
                ['Shop 1', 'Unit A1', 'Commercial', 'Good', $sampleTopology1, $sampleCategory1, '50000', 'Ground floor shop', '0', '0'],
                ['Apt 101', 'Apartment 101', 'Residential', 'Excellent', $sampleTopology2, $sampleCategory2, '35000', '2 bedroom apartment', '0', '100'],
            ], null, 'A2');

            // Notes block, well clear of the data columns.
            $refCol = Coordinate::stringFromColumnIndex(count(self::COLUMNS) + 2);
            $refRow = 1;

            $sheet->setCellValue($refCol . $refRow, 'This file is locked to property:');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, $property->reference_number . ' - ' . $property->property_name);
            $refRow += 2;

            $sheet->setCellValue($refCol . $refRow, 'Required: Unit Number, Usage Type');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Everything else is optional.');
            $refRow += 2;

            $sheet->setCellValue($refCol . $refRow, 'Usage Type: Commercial, Residential, or Hybrid');
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Maximum ' . self::MAX_ROWS . ' units per upload.');
            $refRow += 2;

            $sheet->setCellValue($refCol . $refRow, 'Valid Topology values:');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            foreach ($topologies as $topologyName) {
                $sheet->setCellValue($refCol . $refRow, $topologyName);
                $refRow++;
            }
            $refRow++;

            $sheet->setCellValue($refCol . $refRow, 'Valid Category values:');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            foreach ($categories as $categoryName) {
                $sheet->setCellValue($refCol . $refRow, $categoryName);
                $refRow++;
            }

            foreach (range('A', $refCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'units_template_' . $property->reference_number . '_' . date('Ymd') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating unit bulk upload template: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate template'], 500);
        }
    }

    /**
     * Stage an uploaded template into temp_uploaded_units. Required fields
     * and Unit Number availability are checked here (flagged via push_error,
     * not rejected) — everything else deferred to push.
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
                'bulk_file' => 'required|file|mimes:xlsx,xls|max:10240',
            ]);

            $property = Property::findOrFail($request->property_id);

            $spreadsheet = IOFactory::load($request->file('bulk_file')->getPathname());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // header row

            $rows = array_values(array_filter($rows, function ($row) {
                return !empty(array_filter($row, fn ($cell) => trim((string) $cell) !== ''));
            }));

            if (count($rows) === 0) {
                return response()->json(['success' => false, 'message' => 'The uploaded file has no data rows.'], 422);
            }

            if (count($rows) > self::MAX_ROWS) {
                return response()->json([
                    'success' => false,
                    'message' => 'This file has ' . count($rows) . ' rows — the limit is ' . self::MAX_ROWS . ' units per upload. Please split it into smaller batches.',
                ], 422);
            }

            $result = DB::transaction(function () use ($rows, $property) {
                $count = 0;
                $flagged = 0;
                $usedUnitNumbers = [];

                foreach ($rows as $row) {
                    $unitNumber = trim($row[0] ?? '');
                    $usageType = trim($row[2] ?? '');

                    $issues = [];

                    $missing = $this->missingRequiredFields($unitNumber, $usageType);
                    if (!empty($missing)) {
                        $issues[] = implode(', ', $missing) . ' required';
                    }

                    if ($unitNumber !== '') {
                        $unitIssue = $this->unitNumberIssue($unitNumber, $property, $usedUnitNumbers);
                        if ($unitIssue) {
                            $issues[] = $unitIssue;
                        }
                    }

                    $pushError = !empty($issues) ? implode('; ', $issues) : null;

                    TempUploadedUnit::create([
                        'property_id' => $property->id,
                        'unit_number' => $unitNumber ?: null,
                        'unit_name' => trim($row[1] ?? '') ?: null,
                        'usage_type' => $usageType ?: null,
                        'unit_condition' => trim($row[3] ?? '') ?: null,
                        'topology' => trim($row[4] ?? '') ?: null,
                        'category' => trim($row[5] ?? '') ?: null,
                        'rent_amount' => trim($row[6] ?? '') ?: null,
                        'description' => trim($row[7] ?? '') ?: null,
                        'vat_able' => trim($row[8] ?? '') ?: null,
                        'initial_water_meter_reading' => trim($row[9] ?? '') ?: null,
                        'push_error' => $pushError,
                        'created_by' => Auth::id(),
                    ]);
                    $count++;
                    if ($pushError) {
                        $flagged++;
                    }
                }

                return ['count' => $count, 'flagged' => $flagged];
            });

            $message = "Staged {$result['count']} row(s) for review.";
            if ($result['flagged'] > 0) {
                $message .= " {$result['flagged']} row(s) need attention — see the list.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'staged' => $result['count'],
                'flagged' => $result['flagged'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error staging unit bulk upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process upload: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Not-yet-pushed staged rows for a property (AJAX, for the Units
     * Uploads DataTable).
     */
    public function uploadsData(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $rows = TempUploadedUnit::where('property_id', $request->property_id)
                ->where('pushed_to_main', false)
                ->orderBy('id')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading staged unit uploads: ' . $e->getMessage());
            return response()->json(['data' => [], 'message' => 'Failed to load uploads'], 500);
        }
    }

    /**
     * Edit a staged row before it's pushed.
     */
    public function updateUpload(Request $request, $id)
    {
        try {
            $row = TempUploadedUnit::findOrFail($id);

            if ($row->pushed_to_main) {
                return response()->json(['success' => false, 'message' => 'This row has already been pushed and can no longer be edited here.'], 422);
            }

            $validated = $request->validate([
                'unit_number' => 'nullable|string|max:255',
                'unit_name' => 'nullable|string|max:255',
                'usage_type' => 'nullable|string|max:50',
                'unit_condition' => 'nullable|string|max:255',
                'topology' => 'nullable|string|max:100',
                'category' => 'nullable|string|max:100',
                'rent_amount' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'vat_able' => 'nullable|string|max:10',
                'initial_water_meter_reading' => 'nullable|string|max:50',
            ]);

            $row->update($validated + ['push_error' => null]);

            return response()->json(['success' => true, 'message' => 'Row updated.', 'data' => $row]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating staged unit upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update row'], 500);
        }
    }

    /**
     * Discard a staged row before it's pushed.
     */
    public function destroyUpload($id)
    {
        try {
            $row = TempUploadedUnit::findOrFail($id);

            if ($row->pushed_to_main) {
                return response()->json(['success' => false, 'message' => 'This row has already been pushed and can no longer be deleted here.'], 422);
            }

            $row->delete();

            return response()->json(['success' => true, 'message' => 'Row deleted.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting staged unit upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete row'], 500);
        }
    }

    /**
     * Bulk-discard several staged rows at once. Already-pushed rows in the
     * selection are silently skipped rather than erroring the whole request.
     */
    public function destroySelectedUploads(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer',
            ]);

            $deleted = TempUploadedUnit::whereIn('id', $validated['ids'])
                ->where('pushed_to_main', false)
                ->delete();

            $skipped = count($validated['ids']) - $deleted;

            $message = "Deleted {$deleted} row(s).";
            if ($skipped > 0) {
                $message .= " {$skipped} row(s) were skipped (already pushed).";
            }

            return response()->json(['success' => true, 'message' => $message, 'deleted' => $deleted]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk-deleting staged unit uploads: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete selected rows'], 500);
        }
    }

    /**
     * Run every not-yet-pushed staged row for a property through the real
     * unit-creation validation. Each row is independent — one failing row
     * neither blocks nor rolls back the others.
     */
    public function pushToMain(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $property = Property::findOrFail($request->property_id);

            $rows = TempUploadedUnit::where('property_id', $property->id)
                ->where('pushed_to_main', false)
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Nothing to push — there are no staged rows for this property.'], 422);
            }

            $pushed = 0;
            $failed = 0;
            $usedUnitNumbers = [];

            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($row, $property, &$usedUnitNumbers) {
                        $this->pushOneRow($row, $property, $usedUnitNumbers);
                    });
                    $pushed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $row->update(['push_error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Pushed {$pushed} unit(s) to main. {$failed} row(s) still need attention.",
                'pushed' => $pushed,
                'failed' => $failed,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error pushing unit uploads to main: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to push uploads: ' . $e->getMessage()], 500);
        }
    }

    /**
     * The two required fields (empty/null not allowed), shared by import()
     * (flags the row immediately) and pushOneRow() (re-checked at push time,
     * since a row can be edited in between).
     */
    private function missingRequiredFields(string $unitNumber, string $usageType): array
    {
        $missing = [];
        if ($unitNumber === '') $missing[] = 'Unit Number';
        if ($usageType === '') $missing[] = 'Usage Type';

        return $missing;
    }

    /**
     * Import-time Unit Number check: would this row's generated unit code
     * already exist under the property (as a real unit, or claimed by an
     * earlier row in this same file)? Re-checked for real at push time too.
     */
    private function unitNumberIssue(string $unitNumber, Property $property, array &$usedInFile): ?string
    {
        if (isset($usedInFile[$unitNumber])) {
            return "Unit Number '{$unitNumber}' is used by more than one row in this file";
        }

        $unitCode = Unit::generateUnitCode($property, $unitNumber);
        if (Unit::where('property_id', $property->id)->where('unit_code', $unitCode)->exists()) {
            return "Unit '{$unitCode}' already exists under {$property->property_name}";
        }

        $usedInFile[$unitNumber] = true;

        return null;
    }

    /**
     * Validate one staged row and create the real unit. Throws on any
     * validation failure — the caller (pushToMain) catches it and records
     * push_error on the row, leaving it staged for editing and another push
     * attempt.
     *
     * Field mapping mirrors BulkUploadController::processUnits() exactly so
     * the two paths can't drift apart: unit_type_id always Normal (1, no
     * bulk column for it), usage type text mapped to its id, unit_condition
     * defaults to 'Good' when blank, vat_able normalized to Yes/No.
     */
    private function pushOneRow(TempUploadedUnit $row, Property $property, array &$usedUnitNumbers): void
    {
        $unitNumber = trim((string) $row->unit_number);
        $unitName = trim((string) $row->unit_name);
        $usageTypeLabel = trim((string) $row->usage_type);
        $unitCondition = trim((string) $row->unit_condition);
        $topologyName = trim((string) $row->topology);
        $categoryName = trim((string) $row->category);
        $rentAmount = trim((string) $row->rent_amount);
        $description = trim((string) $row->description);
        $vatAble = trim((string) $row->vat_able);
        $waterMeter = trim((string) $row->initial_water_meter_reading);

        $missing = $this->missingRequiredFields($unitNumber, $usageTypeLabel);
        if (!empty($missing)) {
            throw new \RuntimeException(implode(', ', $missing) . ' required');
        }

        $usageType = self::USAGE_TYPE_MAP[strtolower($usageTypeLabel)] ?? null;
        if (!$usageType) {
            throw new \RuntimeException("Usage Type must be Commercial, Residential, or Hybrid (got '{$usageTypeLabel}')");
        }

        $unitCode = Unit::generateUnitCode($property, $unitNumber);
        if (isset($usedUnitNumbers[$unitNumber])) {
            throw new \RuntimeException("Unit '{$unitCode}' is also claimed by another row in this push");
        }
        if (Unit::where('property_id', $property->id)->where('unit_code', $unitCode)->exists()) {
            throw new \RuntimeException("Unit '{$unitCode}' already exists under {$property->property_name}");
        }

        $topologyId = null;
        if ($topologyName !== '') {
            $topology = UnitTopology::whereRaw('LOWER(TRIM(topology_name)) = ?', [strtolower($topologyName)])->first();
            if (!$topology) {
                throw new \RuntimeException("Topology '{$topologyName}' not recognized");
            }
            $topologyId = $topology->id;
        }

        $categoryId = null;
        if ($categoryName !== '') {
            $category = UnitCategory::whereRaw('LOWER(TRIM(category_name)) = ?', [strtolower($categoryName)])->first();
            if (!$category) {
                throw new \RuntimeException("Category '{$categoryName}' not recognized");
            }
            $categoryId = $category->id;
        }

        if ($rentAmount !== '' && !is_numeric($rentAmount)) {
            throw new \RuntimeException("Rent Amount '{$rentAmount}' is not a number");
        }
        if ($waterMeter !== '' && !is_numeric($waterMeter)) {
            throw new \RuntimeException("Initial Water Meter Reading '{$waterMeter}' is not a number");
        }

        Unit::create([
            'unit_code' => $unitCode,
            'property_id' => $property->id,
            'client_id' => $property->client_id,
            'zone_id' => $property->zone_id,
            'unit_number' => $unitNumber,
            'unit_name' => $unitName !== '' ? $unitName : null,
            'unit_type_id' => 1, // Normal — no bulk column, matches BulkUploadController::processUnits()
            'usage_type_id' => $usageType,
            'unit_condition_id' => $unitCondition !== '' ? $unitCondition : 'Good',
            'topology_id' => $topologyId,
            'category_id' => $categoryId,
            'rent_amount' => $rentAmount !== '' ? $rentAmount : 0,
            'description' => $description !== '' ? $description : null,
            'vat_able' => $this->normalizeVatAble($vatAble),
            'initial_water_meter_reading' => $waterMeter !== '' ? $waterMeter : 0,
            'date_time' => now(),
            'created_by_id' => Auth::id(),
            'occupancy_status_id' => 31, // Vacant
            'current_status_id' => 1, // Pending — awaiting maker-checker approval
        ]);

        $usedUnitNumbers[$unitNumber] = true;

        $row->update(['pushed_to_main' => true, 'push_error' => null]);
    }

    /**
     * A bulk-uploaded VAT cell may arrive as "Yes"/"No", "1"/"0", "y"/"n", or
     * blank — normalized to the "Yes"/"No" string format the manual Add/Edit
     * Unit form already stores. Same rule as BulkUploadController's.
     */
    private function normalizeVatAble(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'yes', 'y', 'true'], true) ? 'Yes' : 'No';
    }
}
