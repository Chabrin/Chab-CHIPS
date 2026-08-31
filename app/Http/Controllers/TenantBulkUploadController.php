<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientNextOfKin;
use App\Models\ClientOtherContact;
use App\Models\Property;
use App\Models\SystemCountry;
use App\Models\TempUploadedTenant;
use App\Models\Unit;
use App\Traits\TenantRecordHelperTrait;
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
 * Bulk upload of tenant demographic records from the Tenants tab of a
 * landlord's client page (property.clients.show). Two-stage flow:
 *
 *   1. import() — an uploaded template is parsed and dumped, as-is, into the
 *      temp_uploaded_tenants staging table. No business validation here
 *      (unit vacancy, nationality lookup, duplicate national ID, ...) — the
 *      point of staging is to let the row be reviewed/edited before it
 *      becomes a real tenant.
 *   2. pushToMain() — every staged, not-yet-pushed row for a property is run
 *      through the real validation + Client-creation logic (the same rules
 *      TenantsController::store()/createTenant() and the old single-stage
 *      importer used). Rows that fail stay staged with push_error set, ready
 *      to be fixed via updateUpload() and pushed again; rows that succeed
 *      flip pushed_to_main and drop out of the staging listing.
 *
 * Deliberately narrower than BulkUploadController::processTenants(): no
 * Tenant Type/Company support, and no lease terms (lease_start_date/years/
 * frequency) — a row here only creates the tenant and links them to a
 * vacant unit, the same as TenantsController::assignUnit() does for a
 * pending-unit tenant. Lease creation stays a separate, deliberate step
 * (LeasesController).
 */
class TenantBulkUploadController extends Controller
{
    use TenantRecordHelperTrait;

    private const MAX_ROWS = 100;

    /** Column order shared by the template and the importer — keep in sync. */
    private const COLUMNS = [
        'Unit Code', 'Title', 'Names', 'Gender', 'Mobile Number', 'Email Address',
        'KRA PIN', 'Nationality', 'National ID', 'Passport Number',
        'Other Contact Name', 'Other Contact Email', 'Other Contact Phone',
        'Next of Kin Name', 'Next of Kin Mobile', 'Next of Kin Email',
        'Next of Kin ID', 'Next of Kin Relationship',
    ];

    /**
     * Excel template for a single property, one row pre-filled per vacant
     * unit's Unit Code so a row can only ever reference a real, currently
     * vacant unit under this property.
     */
    public function downloadTemplate(Request $request)
    {
        try {
            $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
            ]);

            $property = Property::findOrFail($request->property_id);

            $vacantUnitCodes = Unit::where('property_id', $property->id)
                ->vacant()
                ->orderBy('unit_code')
                ->limit(self::MAX_ROWS)
                ->pluck('unit_code');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tenants');

            $sheet->fromArray(self::COLUMNS, null, 'A1');
            $lastCol = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
            ]);

            $row = 2;
            foreach ($vacantUnitCodes as $unitCode) {
                $sheet->setCellValueExplicit('A' . $row, $unitCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $row++;
            }

            // Notes block, well clear of the data columns.
            $refCol = Coordinate::stringFromColumnIndex(count(self::COLUMNS) + 2);
            $refRow = 1;

            $sheet->setCellValue($refCol . $refRow, 'This file is locked to property:');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, $property->reference_number . ' - ' . $property->property_name);
            $refRow += 2;

            $sheet->setCellValue($refCol . $refRow, 'Required: Unit Code, Names, Gender, Mobile Number, Nationality, National ID');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Everything else is optional.');
            $refRow += 2;

            $sheet->setCellValue($refCol . $refRow, 'Unit Code must be one of this property\'s vacant units — pre-filled below, one per row.');
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Gender: Male, Female, or Other');
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Nationality: full country name, e.g. Kenya');
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Maximum ' . self::MAX_ROWS . ' tenants per upload.');

            foreach (range('A', $refCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'tenants_template_' . $property->reference_number . '_' . date('Ymd') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating tenant bulk upload template: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate template'], 500);
        }
    }

    /**
     * Stage an uploaded template into temp_uploaded_tenants. Rows are taken
     * as-is (trimmed only) — no business validation. The whole run is capped
     * at MAX_ROWS data rows so one file can't silently flood the review
     * table.
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
                    'message' => 'This file has ' . count($rows) . ' rows — the limit is ' . self::MAX_ROWS . ' tenants per upload. Please split it into smaller batches.',
                ], 422);
            }

            $result = DB::transaction(function () use ($rows, $property) {
                $count = 0;
                $flagged = 0;
                $usedUnitCodes = [];

                foreach ($rows as $row) {
                    $unitCode = trim($row[0] ?? '');
                    $unitIssue = $unitCode !== '' ? $this->unitCodeIssue($unitCode, $property, $usedUnitCodes) : null;

                    TempUploadedTenant::create([
                        'property_id' => $property->id,
                        'unit_code' => $unitCode ?: null,
                        'title' => trim($row[1] ?? '') ?: null,
                        'names' => trim($row[2] ?? '') ?: null,
                        'gender' => trim($row[3] ?? '') ?: null,
                        'mobile_number' => trim($row[4] ?? '') ?: null,
                        'email_address' => trim($row[5] ?? '') ?: null,
                        'pin_number' => trim($row[6] ?? '') ?: null,
                        'nationality' => trim($row[7] ?? '') ?: null,
                        'national_id' => trim($row[8] ?? '') ?: null,
                        'passport_number' => trim($row[9] ?? '') ?: null,
                        'other_contact_name' => trim($row[10] ?? '') ?: null,
                        'other_contact_email' => trim($row[11] ?? '') ?: null,
                        'other_contact_phone' => trim($row[12] ?? '') ?: null,
                        'next_of_kin_name' => trim($row[13] ?? '') ?: null,
                        'next_of_kin_mobile' => trim($row[14] ?? '') ?: null,
                        'next_of_kin_email' => trim($row[15] ?? '') ?: null,
                        'next_of_kin_national_id' => trim($row[16] ?? '') ?: null,
                        'next_of_kin_relationship' => trim($row[17] ?? '') ?: null,
                        'push_error' => $unitIssue,
                        'created_by' => Auth::id(),
                    ]);
                    $count++;
                    if ($unitIssue) {
                        $flagged++;
                    }
                }

                return ['count' => $count, 'flagged' => $flagged];
            });

            $message = "Staged {$result['count']} row(s) for review.";
            if ($result['flagged'] > 0) {
                $message .= " {$result['flagged']} row(s) have a Unit Code issue — see \"Needs attention\" in the list.";
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
            Log::error('Error staging tenant bulk upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process upload: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Not-yet-pushed staged rows for a property (AJAX, for the Tenants
     * Uploads DataTable).
     */
    public function uploadsData(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $rows = TempUploadedTenant::where('property_id', $request->property_id)
                ->where('pushed_to_main', false)
                ->orderBy('id')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading staged tenant uploads: ' . $e->getMessage());
            return response()->json(['data' => [], 'message' => 'Failed to load uploads'], 500);
        }
    }

    /**
     * Edit a staged row before it's pushed. Refuses once pushed — at that
     * point the real record lives in sys_pm_clients and should be edited via
     * the normal tenant edit screen instead.
     */
    public function updateUpload(Request $request, $id)
    {
        try {
            $row = TempUploadedTenant::findOrFail($id);

            if ($row->pushed_to_main) {
                return response()->json(['success' => false, 'message' => 'This row has already been pushed and can no longer be edited here.'], 422);
            }

            $validated = $request->validate([
                'unit_code' => 'nullable|string|max:255',
                'title' => 'nullable|string|max:50',
                'names' => 'nullable|string|max:255',
                'gender' => 'nullable|string|max:20',
                'mobile_number' => 'nullable|string|max:50',
                'email_address' => 'nullable|email|max:255',
                'pin_number' => 'nullable|string|max:50',
                'nationality' => 'nullable|string|max:100',
                'national_id' => 'nullable|string|max:50',
                'passport_number' => 'nullable|string|max:50',
                'other_contact_name' => 'nullable|string|max:255',
                'other_contact_email' => 'nullable|email|max:255',
                'other_contact_phone' => 'nullable|string|max:50',
                'next_of_kin_name' => 'nullable|string|max:255',
                'next_of_kin_mobile' => 'nullable|string|max:50',
                'next_of_kin_email' => 'nullable|email|max:255',
                'next_of_kin_national_id' => 'nullable|string|max:50',
                'next_of_kin_relationship' => 'nullable|string|max:100',
            ]);

            // Clear any prior push_error — the row is being retried with new data.
            $row->update($validated + ['push_error' => null]);

            return response()->json(['success' => true, 'message' => 'Row updated.', 'data' => $row]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating staged tenant upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update row'], 500);
        }
    }

    /**
     * Discard a staged row before it's pushed.
     */
    public function destroyUpload($id)
    {
        try {
            $row = TempUploadedTenant::findOrFail($id);

            if ($row->pushed_to_main) {
                return response()->json(['success' => false, 'message' => 'This row has already been pushed and can no longer be deleted here.'], 422);
            }

            $row->delete();

            return response()->json(['success' => true, 'message' => 'Row deleted.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting staged tenant upload: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete row'], 500);
        }
    }

    /**
     * Run every not-yet-pushed staged row for a property through the real
     * tenant-creation validation. Each row is independent — one failing row
     * neither blocks nor rolls back the others, mirroring the row-level
     * error handling BulkUploadController::processTenants() already uses.
     */
    public function pushToMain(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $property = Property::findOrFail($request->property_id);

            $rows = TempUploadedTenant::where('property_id', $property->id)
                ->where('pushed_to_main', false)
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Nothing to push — there are no staged rows for this property.'], 422);
            }

            $pushed = 0;
            $failed = 0;
            $usedUnitIds = [];

            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($row, $property, &$usedUnitIds) {
                        $this->pushOneRow($row, $property, $usedUnitIds);
                    });
                    $pushed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $row->update(['push_error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Pushed {$pushed} tenant(s) to main. {$failed} row(s) still need attention.",
                'pushed' => $pushed,
                'failed' => $failed,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error pushing tenant uploads to main: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to push uploads: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Import-time Unit Code check — the one field validated before staging
     * (everything else is deferred to pushOneRow(), see the class docblock).
     * Returns null when the code is fine, or a human-readable reason why the
     * row is flagged "Needs attention" as soon as it lands in the staging
     * table: unit doesn't exist under the property, isn't vacant, already
     * has a real tenant, or is claimed by an earlier row in this same file.
     * Re-checked for real at push time too (pushOneRow()), since availability
     * can change between upload and push.
     */
    private function unitCodeIssue(string $unitCode, Property $property, array &$usedInFile): ?string
    {
        if (isset($usedInFile[$unitCode])) {
            return "Unit Code '{$unitCode}' is used by more than one row in this file";
        }

        $unit = Unit::where('property_id', $property->id)->where('unit_code', $unitCode)->first();
        if (!$unit) {
            return "Unit Code '{$unitCode}' not found under {$property->property_name}";
        }

        if (!$unit->isVacant()) {
            return "Unit '{$unitCode}' is not vacant";
        }

        if (Client::where('unit_id', $unit->id)->exists()) {
            return "Unit '{$unitCode}' is already assigned to a tenant";
        }

        $usedInFile[$unitCode] = true;

        return null;
    }

    /**
     * Validate one staged row and create the real tenant (+ optional other
     * contact / next of kin). Throws on any validation failure — the caller
     * (pushToMain) catches it and records push_error on the row, leaving it
     * staged for editing and another push attempt.
     *
     * $usedUnitIds tracks units claimed earlier in this same push run —
     * required because a row here never creates a LeaseAgreement, and
     * Unit::isVacant() is lease-based (see Unit model), so it wouldn't
     * otherwise notice two rows in one run both targeting the same
     * still-lease-free unit.
     */
    private function pushOneRow(TempUploadedTenant $row, Property $property, array &$usedUnitIds): void
    {
        $unitCode = trim((string) $row->unit_code);
        $title = trim((string) $row->title);
        $names = trim((string) $row->names);
        $gender = trim((string) $row->gender);
        $mobile = trim((string) $row->mobile_number);
        $email = trim((string) $row->email_address);
        $pin = trim((string) $row->pin_number);
        $nationalityName = trim((string) $row->nationality);
        $nationalId = trim((string) $row->national_id);
        $passport = trim((string) $row->passport_number);
        $otherContactName = trim((string) $row->other_contact_name);
        $otherContactEmail = trim((string) $row->other_contact_email);
        $otherContactPhone = trim((string) $row->other_contact_phone);
        $kinName = trim((string) $row->next_of_kin_name);
        $kinMobile = trim((string) $row->next_of_kin_mobile);
        $kinEmail = trim((string) $row->next_of_kin_email);
        $kinId = trim((string) $row->next_of_kin_national_id);
        $kinRelationship = trim((string) $row->next_of_kin_relationship);

        $missing = [];
        if ($unitCode === '') $missing[] = 'Unit Code';
        if ($names === '') $missing[] = 'Names';
        if ($gender === '') $missing[] = 'Gender';
        if ($mobile === '') $missing[] = 'Mobile Number';
        if ($nationalityName === '') $missing[] = 'Nationality';
        if ($nationalId === '') $missing[] = 'National ID';
        if (!empty($missing)) {
            throw new \RuntimeException(implode(', ', $missing) . ' required');
        }

        $normalizedGender = ucfirst(strtolower($gender));
        if (!in_array($normalizedGender, ['Male', 'Female', 'Other'], true)) {
            throw new \RuntimeException("Gender must be Male, Female, or Other (got '{$gender}')");
        }

        $nationality = SystemCountry::whereRaw('LOWER(TRIM(country_name)) = ?', [strtolower($nationalityName)])->first();
        if (!$nationality) {
            throw new \RuntimeException("Nationality '{$nationalityName}' not recognized");
        }

        $unit = Unit::where('property_id', $property->id)->where('unit_code', $unitCode)->first();
        if (!$unit) {
            throw new \RuntimeException("Unit Code '{$unitCode}' not found under {$property->property_name}");
        }
        if (isset($usedUnitIds[$unit->id])) {
            throw new \RuntimeException("Unit '{$unitCode}' is also claimed by another row in this push");
        }
        if (!$unit->isVacant()) {
            throw new \RuntimeException("Unit '{$unitCode}' is not vacant");
        }
        if (Client::where('unit_id', $unit->id)->exists()) {
            throw new \RuntimeException("Unit '{$unitCode}' is already assigned to a tenant");
        }

        $duplicate = Client::tenants()->where('national_id', $nationalId)
            ->select('id', 'names', 'reference_number')->first();
        if ($duplicate) {
            throw new \RuntimeException("National ID '{$nationalId}' already belongs to tenant {$duplicate->names} ({$duplicate->reference_number})");
        }

        $tenant = Client::create([
            'reference_number' => $this->generateTenantReferenceNumber(),
            'names' => trim($title . ' ' . $names),
            'title' => $title !== '' ? $title : null,
            'gender' => $normalizedGender,
            'mobile_number' => $mobile,
            'email_address' => $email !== '' ? $email : null,
            'pin_number' => $pin !== '' ? $pin : null,
            'national_id' => $nationalId,
            'passport_number' => $passport !== '' ? $passport : null,
            'nationality_id' => $nationality->id,
            'registered_date' => now()->toDateString(),
            'prefered_messages_language_id' => 'English',
            'client_type_id' => 1, // Individual
            'type_id' => 2, // Tenant — see Client::scopeTenants()
            'property_id' => $property->id,
            'sla_id' => $this->resolveSlaId($property->id),
            'unit_id' => $unit->id,
            'rent_amount' => $this->resolveRentAmount($unit->id),
            'overdraft_penalty' => 5, // company-standard default, not user-entered
            'created_by' => Auth::id(),
            'date_time' => now(),
            'current_status_id' => 1, // Pending — awaiting maker-checker approval
        ]);

        if ($otherContactName !== '') {
            ClientOtherContact::create([
                'parent_id' => $tenant->id,
                'contact_names' => $otherContactName,
                'other_email' => $otherContactEmail !== '' ? $otherContactEmail : null,
                'other_mobile_number' => $otherContactPhone !== '' ? $otherContactPhone : null,
            ]);
        }

        if ($kinName !== '') {
            ClientNextOfKin::create([
                'parent_id' => $tenant->id,
                'names' => $kinName,
                'mobile_number' => $kinMobile !== '' ? $kinMobile : null,
                'email_address' => $kinEmail !== '' ? $kinEmail : null,
                'relationship' => $kinRelationship !== '' ? $kinRelationship : null,
                'national_id' => $kinId !== '' ? $kinId : null,
            ]);
        }

        $usedUnitIds[$unit->id] = true;

        $row->update(['pushed_to_main' => true, 'push_error' => null]);
    }
}
