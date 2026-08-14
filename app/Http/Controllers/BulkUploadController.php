<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Zone;
use App\Models\Staff;
use App\Models\Client;
use App\Models\Property;
use App\Models\ZoneArea;
use App\Models\UnitTopology;
use App\Models\UnitCategory;
use App\Models\ClientBankAccount;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BulkUploadController extends Controller
{
    /**
     * Download sample file for bulk upload
     */
    public function downloadSample(Request $request)
    {
        try {
            $type = $request->type;
            $clientId = $request->client_id;
            $propertyId = $request->property_id;

            if ($type === 'properties') {
                return $this->generatePropertiesSample($clientId);
            } elseif ($type === 'units') {
                return $this->generateUnitsSample($clientId, $propertyId);
            } elseif ($type === 'tenants') {
                return $this->generateTenantsSample($clientId);
            }
            
            return redirect()->back()->with('error', 'Invalid upload type');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating sample file: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate sample file');
        }
    }
    
    /**
     * Generate properties sample file
     */
    private function generatePropertiesSample($clientId)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = [
            'Property Name',
            'Zone Name',
            'Zone Area Name',
            'Zone Manager Name',
            'Zone Supervisor Name',
            'Field Officer Name',
            'Settlement Account Number',
            'LR Number',
            'Property Type',
            'Acquisition Date',
            'Description',
            'Latitude',
            'Longitude',
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
        
        // Add sample data
        $sampleData = [
            ['Skyline Apartments', 'A', 'Westlands', 'John Doe', 'Jane Smith', 'Bob Wilson', '1234567890', 'LR123/456', 'Residential', date('Y-m-d'), 'Modern apartment complex', '-1.2634', '36.8073'],
            ['Trade Center Plaza', 'B', 'CBD', 'John Doe', 'Jane Smith', 'Alice Brown', '0987654321', 'LR789/012', 'Commercial', date('Y-m-d'), 'Office and retail space', '-1.2864', '36.8172'],
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Auto-size columns
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Generate file
        $writer = new Xlsx($spreadsheet);
        $filename = 'properties_bulk_upload_sample_' . date('Ymd') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Generate units sample file. When $propertyId is given (property-scoped
     * upload), every sample row's Property Reference is locked to that
     * property and the reference list is skipped, since there's only one
     * property in play.
     */
    private function generateUnitsSample($clientId, $propertyId = null)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $scopedProperty = $propertyId ? Property::find($propertyId) : null;

        // Get client properties for reference (only needed for the unscoped, client-wide upload)
        $properties = $scopedProperty
            ? collect()
            : Property::where('client_id', $clientId)->select('reference_number', 'property_name')->get();

        $topologies = UnitTopology::orderBy('topology_name')->pluck('topology_name');
        $categories = UnitCategory::orderBy('category_name')->pluck('category_name');

        // Set headers (11 columns — "Unit Type" is intentionally not a bulk-upload
        // field: it's a Normal/Owner Occupied/Caretaker classification set manually
        // after import, not something typically known in bulk. "Usage Type" below
        // is the Commercial/Residential/Hybrid field.)
        $headers = [
            'Property Reference',
            'Unit Number',
            'Unit Name',
            'Usage Type',
            'Unit Condition',
            'Topology',
            'Category',
            'Rent Amount',
            'Description',
            'VAT (0/1)',
            'Initial Water Meter Reading',
        ];

        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        // Add sample data
        $propertyRef = $scopedProperty ? $scopedProperty->reference_number : ($properties->first() ? $properties->first()->reference_number : '1A');
        $sampleTopology1 = $topologies->get(0, '1 Bedroom');
        $sampleTopology2 = $topologies->get(1, '2 Bedroom');
        $sampleCategory1 = $categories->get(0, 'Standard');
        $sampleCategory2 = $categories->get(1, 'Premium');
        $sampleData = [
            [$propertyRef, 'Shop 1', 'Unit A1', 'Commercial', 'Good', $sampleTopology1, $sampleCategory1, '50000', 'Ground floor shop', '0', '0'],
            [$propertyRef, 'Apt 101', 'Apartment 101', 'Residential', 'Excellent', $sampleTopology2, $sampleCategory2, '35000', '2 bedroom apartment', '0', '100'],
        ];

        $sheet->fromArray($sampleData, null, 'A2');

        // Reference information block, left of a blank gap column (M) so it
        // never overwrites a real data header.
        $refCol = 'M';
        $refRow = 1;

        if ($scopedProperty) {
            $sheet->setCellValue($refCol . $refRow, 'This file is locked to property:');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, $scopedProperty->reference_number . ' - ' . $scopedProperty->property_name);
            $refRow++;
            $sheet->setCellValue($refCol . $refRow, 'Every row will be attached to this property regardless of what is typed in "Property Reference".');
            $refRow += 2;
        } elseif ($properties->count() > 0) {
            $sheet->setCellValue($refCol . $refRow, 'Available Properties:');
            $sheet->getStyle($refCol . $refRow)->getFont()->setBold(true);
            $refRow++;
            foreach ($properties as $property) {
                $sheet->setCellValue($refCol . $refRow, $property->reference_number . ' - ' . $property->property_name);
                $refRow++;
            }
            $refRow++;
        }

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

        // Auto-size columns
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Generate file
        $writer = new Xlsx($spreadsheet);
        $filename = 'units_bulk_upload_sample_' . date('Ymd') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
    
    /**
     * Generate tenants sample file
     */
    private function generateTenantsSample($clientId)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Get client properties and units for reference
        $properties = Property::where('client_id', $clientId)
            ->with(['units' => function($query) {
                $query->where('occupancy_status_id', 31); // Available units
            }])
            ->select('id', 'reference_number', 'property_name')
            ->get();
        
        // Set headers
        $headers = [
            'Property Reference',
            'Unit Number',
            'Title',
            'First Name',
            'Second Name',
            'Last Name',
            'Gender',
            'Mobile Number',
            'Email Address',
            'National ID',
            'Tenant Type',
            'Lease Start Date',
            'Lease Years',
            'Rent Amount',
            'Frequency',
            'Registered Date',
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);
        
        // Add sample data
        $propertyRef = $properties->first() ? $properties->first()->reference_number : '1A';
        $sampleData = [
            [$propertyRef, 'Apt 101', 'Mr', 'John', 'Kamau', 'Mwangi', 'Male', '254712345678', 'john.kamau@email.com', '12345678', 'Individual', date('Y-m-d'), '1', '35000', 'Monthly', date('Y-m-d')],
            [$propertyRef, 'Shop 1', 'Mrs', 'Jane', 'Wanjiru', 'Njeri', 'Female', '254723456789', 'jane.wanjiru@email.com', '23456789', 'Corporate', date('Y-m-d'), '2', '50000', 'Monthly', date('Y-m-d')],
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Add reference information
        $col = 'R';
        $sheet->setCellValue($col . '1', 'REFERENCE INFORMATION');
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        
        $row = 2;
        $sheet->setCellValue($col . $row, 'Available Properties & Units:');
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $row++;
        
        foreach ($properties as $property) {
            $sheet->setCellValue($col . $row, $property->reference_number . ' - ' . $property->property_name);
            $row++;
            
            if ($property->units->count() > 0) {
                foreach ($property->units as $unit) {
                    $sheet->setCellValue($col . $row, '  • ' . $unit->unit_number . ' (' . ($unit->unit_name ?? 'No Name') . ')');
                    $row++;
                }
            }
            $row++;
        }
        
        $row += 2;
        $sheet->setCellValue($col . $row, 'Valid Values:');
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue($col . $row, 'Title: Mr, Mrs, Ms, Dr, Prof');
        $row++;
        $sheet->setCellValue($col . $row, 'Gender: Male, Female, Other');
        $row++;
        $sheet->setCellValue($col . $row, 'Tenant Type: Individual, Corporate, Government');
        $row++;
        $sheet->setCellValue($col . $row, 'Frequency: Monthly, Quarterly, Semi-Annually, Annually');
        $row++;
        $sheet->setCellValue($col . $row, 'Mobile Format: 254XXXXXXXXX (12 digits)');
        
        // Auto-size columns
        foreach (range('A', 'S') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Generate file
        $writer = new Xlsx($spreadsheet);
        $filename = 'tenants_bulk_upload_sample_' . date('Ymd') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Process bulk upload
     */
    public function upload(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'client_id' => 'required|integer|exists:sys_pm_clients,id',
                'upload_type' => 'required|in:properties,units,tenants',
                'bulk_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
                'property_id' => 'nullable|integer|exists:sys_pm_properties,id',
            ]);

            $uploadType = $request->upload_type;
            $clientId = $request->client_id;
            $propertyId = $request->property_id;
            $file = $request->file('bulk_file');

            // Load spreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            // Remove header row
            array_shift($data);

            if ($uploadType === 'properties') {
                $result = $this->processProperties($data, $clientId);
            } elseif ($uploadType === 'units') {
                $result = $this->processUnits($data, $clientId, $propertyId);
            } elseif ($uploadType === 'tenants') {
                $result = $this->processTenants($data, $clientId);
            }

            DB::commit();

            $message = "Bulk upload successful! Created {$result['success']} records. {$result['failed']} failed.";

            if ($propertyId) {
                return redirect()->route('property.properties.show', Crypt::encrypt($propertyId))
                    ->with('success', $message);
            }

            return redirect()->route('property.clients.show', Crypt::encrypt($clientId))
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            ErrorDispatcher::dispatch($e);
            Log::error('Error processing bulk upload: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process upload: ' . $e->getMessage());
        }
    }
    
    /**
     * Process properties bulk upload
     */
    private function processProperties($data, $clientId)
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($data as $index => $row) {
            try {
                // Skip empty rows
                if (empty($row[0])) continue;
                
                $rowNum = $index + 2; // +2 because Excel is 1-indexed and has header row
                
                // Find zone by name
                $zone = Zone::whereRaw('LOWER(TRIM(zone_name)) = ?', [strtolower(trim($row[1]))])->first();
                if (!$zone) {
                    $errors[] = "Row {$rowNum}: Zone '{$row[1]}' not found";
                    Log::error("Row {$rowNum}: Zone not found - " . json_encode($row));
                    $failed++;
                    continue;
                }

                // Find zone area by name
                $zoneArea = ZoneArea::whereRaw('LOWER(TRIM(zone_area_name)) = ?', [strtolower(trim($row[2]))])
                    ->where('zone_id', $zone->id)
                    ->first();
                if (!$zoneArea) {
                    $errors[] = "Row {$rowNum}: Zone area '{$row[2]}' not found in zone '{$row[1]}'";
                    Log::error("Row {$rowNum}: Zone area not found - " . json_encode($row));
                    $failed++;
                    continue;
                }

                // Find zone manager by name (optional) — matched against sys_staff,
                // the same table the current Add Property form's dropdown uses.
                $zoneManagerId = null;
                if (!empty($row[3])) {
                    $zoneManager = Staff::whereRaw('LOWER(TRIM(full_names)) = ?', [strtolower(trim($row[3]))])->first();
                    if ($zoneManager) {
                        $zoneManagerId = $zoneManager->id;
                    } else {
                        Log::warning("Row {$rowNum}: Zone manager '{$row[3]}' not found, proceeding without");
                    }
                }

                // Find zone supervisor by name (optional)
                $zoneSupervisorId = null;
                if (!empty($row[4])) {
                    $zoneSupervisor = Staff::whereRaw('LOWER(TRIM(full_names)) = ?', [strtolower(trim($row[4]))])->first();
                    if ($zoneSupervisor) {
                        $zoneSupervisorId = $zoneSupervisor->id;
                    } else {
                        Log::warning("Row {$rowNum}: Zone supervisor '{$row[4]}' not found, proceeding without");
                    }
                }

                // Find field officer by name (optional)
                $fieldOfficerId = null;
                if (!empty($row[5])) {
                    $fieldOfficer = Staff::whereRaw('LOWER(TRIM(full_names)) = ?', [strtolower(trim($row[5]))])->first();
                    if ($fieldOfficer) {
                        $fieldOfficerId = $fieldOfficer->id;
                    } else {
                        Log::warning("Row {$rowNum}: Field officer '{$row[5]}' not found, proceeding without");
                    }
                }

                // Find bank account by account number (optional)
                $bankAccountId = null;
                if (!empty($row[6])) {
                    $bankAccount = ClientBankAccount::where('parent_id', $clientId)
                        ->whereRaw('TRIM(account_number) = ?', [trim($row[6])])
                        ->first();
                    if ($bankAccount) {
                        $bankAccountId = $bankAccount->id;
                    } else {
                        Log::warning("Row {$rowNum}: Settlement account '{$row[6]}' not found for this client, proceeding without");
                    }
                }

                // Generate reference number — shared with PropertiesController::store()
                // so the two paths can't drift apart again (this also fixes the previous
                // MySQL-only CAST(... AS UNSIGNED) SQL, which errors on this app's Postgres DB).
                $referenceNumber = Property::generateReferenceNumber($zone);

                // Map property type
                $propertyTypeMap = ['commercial' => 1, 'residential' => 2, 'hybrid' => 3];
                $propertyType = $propertyTypeMap[strtolower($row[8])] ?? 1;
                
                // Format lat_long
                $latLong = null;
                if (!empty($row[11]) && !empty($row[12])) {
                    $latLong = '(' . $row[11] . ',' . $row[12] . '):12';
                }
                
                Property::create([
                    'reference_number' => $referenceNumber,
                    'client_id' => $clientId,
                    'property_name' => $row[0],
                    'zone_id' => $zone->id,
                    'zone_area_id' => $zoneArea->id,
                    'zone_manager_id' => $zoneManagerId,
                    'zone_supervisor_id' => $zoneSupervisorId,
                    'field_officer_id' => $fieldOfficerId,
                    'bank_account_id' => $bankAccountId,
                    'lr_number' => $row[7] ?? null,
                    'usage_type_id' => $propertyType,
                    'acquisition_date' => !empty($row[9]) ? date('Y-m-d', strtotime($row[9])) : now(),
                    'description' => $row[10] ?? null,
                    'lat_long' => $latLong,
                    'date_time' => now(),
                    'created_by' => Auth::id(),
                    'current_status_id' => 1,
                ]);
                
                $success++;
            } catch (\Exception $e) {
                $rowNum = $index + 2;
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::error("Row {$rowNum}: Failed to create property - " . $e->getMessage() . " - Data: " . json_encode($row));
                $failed++;
            }
        }
        
        if (!empty($errors)) {
            Log::error('Property bulk upload errors: ' . json_encode($errors));
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    /**
     * Process units bulk upload. Column layout (11 columns, no "Unit Type" —
     * see generateUnitsSample()): Property Reference, Unit Number, Unit Name,
     * Usage Type, Unit Condition, Topology, Category, Rent Amount, Description,
     * VAT (0/1), Initial Water Meter Reading.
     *
     * When $propertyId is given (property-scoped upload from the property show
     * page), every row is attached to that exact property regardless of what's
     * in the Property Reference column — that column is simply ignored.
     */
    private function processUnits($data, $clientId, $propertyId = null)
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        $scopedProperty = $propertyId ? Property::find($propertyId) : null;

        foreach ($data as $index => $row) {
            try {
                // Skip empty rows
                if (empty($row[0]) && !$scopedProperty) continue;
                // unit_code is now built from the unit number, so every row needs one.
                if (empty($row[1])) continue;

                $rowNum = $index + 2; // +2 because Excel is 1-indexed and has header row

                if ($scopedProperty) {
                    $property = $scopedProperty;
                } else {
                    // Find property by reference number
                    $property = Property::where('reference_number', $row[0])
                        ->where('client_id', $clientId)
                        ->first();

                    if (!$property) {
                        $errors[] = "Row {$rowNum}: Property '{$row[0]}' not found for this client";
                        Log::error("Row {$rowNum}: Property not found - " . json_encode($row));
                        $failed++;
                        continue;
                    }
                }

                // Find topology by name (optional) — matched against sys_pm_unit_topologies,
                // the same table now backing the Add/Edit Unit modal's dropdown.
                $topologyId = null;
                if (!empty($row[5])) {
                    $topology = UnitTopology::whereRaw('LOWER(TRIM(topology_name)) = ?', [strtolower(trim($row[5]))])->first();
                    if ($topology) {
                        $topologyId = $topology->id;
                    } else {
                        Log::warning("Row {$rowNum}: Topology '{$row[5]}' not found, proceeding without");
                    }
                }

                // Find category by name (optional) — matched against sys_pm_unit_categories
                $categoryId = null;
                if (!empty($row[6])) {
                    $category = UnitCategory::whereRaw('LOWER(TRIM(category_name)) = ?', [strtolower(trim($row[6]))])->first();
                    if ($category) {
                        $categoryId = $category->id;
                    } else {
                        Log::warning("Row {$rowNum}: Category '{$row[6]}' not found, proceeding without");
                    }
                }

                // Generate unit code — shared with UnitsController::store() so the two
                // paths can't drift apart again.
                $unitCode = Unit::generateUnitCode($property, $row[1]);

                // Usage Type: Commercial/Residential/Hybrid. Unit Type (Normal/Owner
                // Occupied/Caretaker) has no bulk column — every bulk-created unit
                // defaults to Normal (1) and can be changed manually afterward.
                $usageTypeMap = ['commercial' => 1, 'residential' => 2, 'hybrid' => 3];
                $usageType = $usageTypeMap[strtolower(trim($row[3] ?? ''))] ?? 1;

                Unit::create([
                    'unit_code' => $unitCode,
                    'property_id' => $property->id,
                    'client_id' => $clientId,
                    'zone_id' => $property->zone_id,
                    'unit_number' => $row[1],
                    'unit_name' => $row[2] ?? null,
                    'unit_type_id' => 1, // Normal
                    'usage_type_id' => $usageType,
                    'unit_condition_id' => $row[4] ?? 'Good',
                    'topology_id' => $topologyId,
                    'category_id' => $categoryId,
                    'rent_amount' => $row[7] ?? 0,
                    'description' => $row[8] ?? null,
                    'vat_able' => $this->normalizeVatAble($row[9] ?? null),
                    'initial_water_meter_reading' => $row[10] ?? 0,
                    'date_time' => now(),
                    'created_by_id' => Auth::id(),
                    'occupancy_status_id' => 31, // Vacant
                    'current_status_id' => 1, // Pending — awaiting maker-checker approval, same as manual creation
                ]);

                $success++;
            } catch (\Exception $e) {
                $rowNum = $index + 2;
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::error("Row {$rowNum}: Failed to create unit - " . $e->getMessage() . " - Data: " . json_encode($row));
                $failed++;
            }
        }

        if (!empty($errors)) {
            Log::error('Unit bulk upload errors: ' . json_encode($errors));
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Normalize a bulk-uploaded VAT cell (which may arrive as "Yes"/"No",
     * "1"/"0", "y"/"n", or blank) to the "Yes"/"No" string format the manual
     * Add/Edit Unit form already stores, so vat_able doesn't hold inconsistent
     * data types depending on which flow created the row.
     */
    private function normalizeVatAble($value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'yes', 'y', 'true'], true) ? 'Yes' : 'No';
    }
    
    /**
     * Process tenants bulk upload
     */
    private function processTenants($data, $clientId)
    {
        $success = 0;
        $failed = 0;
        
        foreach ($data as $row) {
            try {
                // Skip empty rows
                if (empty($row[0]) || empty($row[3])) continue;
                
                // Find property by reference number
                $property = Property::where('reference_number', $row[0])
                    ->where('client_id', $clientId)
                    ->first();
                    
                if (!$property) {
                    $failed++;
                    Log::warning("Property not found: {$row[0]}");
                    continue;
                }
                
                // Find unit by unit number
                $unit = Unit::where('property_id', $property->id)
                    ->where('unit_number', $row[1])
                    ->first();
                    
                if (!$unit) {
                    $failed++;
                    Log::warning("Unit not found: {$row[1]} in property {$row[0]}");
                    continue;
                }
                
                // Check if unit is already occupied
                if ($unit->occupancy_status_id != 31) {
                    $failed++;
                    Log::warning("Unit {$row[1]} is already occupied");
                    continue;
                }
                
                // Generate tenant reference number
                $referenceNumber = $this->generateTenantReferenceNumber();
                
                // Combine names
                $names = trim(($row[2] ?? '') . ' ' . ($row[3] ?? '') . ' ' . ($row[4] ?? '') . ' ' . ($row[5] ?? ''));
                
                // Validate mobile number format
                $mobileNumber = $row[7] ?? '';
                if (strlen($mobileNumber) != 12 || !preg_match('/^254[0-9]{9}$/', $mobileNumber)) {
                    $failed++;
                    Log::warning("Invalid mobile number format: {$mobileNumber}");
                    continue;
                }
                
                // Create tenant
                $tenant = Client::create([
                    'reference_number' => $referenceNumber,
                    'names' => $names,
                    'title' => $row[2] ?? 'Mr',
                    'second_name' => $row[4] ?? null,
                    'last_name' => $row[5] ?? null,
                    'gender' => $row[6] ?? 'Male',
                    'mobile_number' => $mobileNumber,
                    'email_address' => $row[8] ?? null,
                    'national_id' => $row[9] ?? null,
                    'client_type_id' => $row[10] ?? 'Individual',
                    'property_id' => $property->id,
                    'unit_id' => $unit->id,
                    'lease_start_date' => $row[11] ?? now(),
                    'lease_years' => $row[12] ?? 1,
                    'rent_amount' => $row[13] ?? 0,
                    'frequency' => $row[14] ?? 'Monthly',
                    'registered_date' => $row[15] ?? now(),
                    'escalation_rate' => 0,
                    'overdraft_penalty' => 5,
                    'created_by' => Auth::id(),
                    'date_time' => now(),
                ]);
                
                // Update unit occupancy status
                $unit->update([
                    'occupancy_status_id' => 32, // Occupied
                ]);
                
                $success++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("Failed to create tenant: " . $e->getMessage());
            }
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    /**
     * Generate tenant reference number with TEN prefix
     */
    private function generateTenantReferenceNumber()
    {
        $prefix = "TEN";
        
        $lastTenant = Client::where('reference_number', 'LIKE', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->first();
    
        if ($lastTenant) {
            $lastNumber = (int) substr($lastTenant->reference_number, strlen($prefix) + 1);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
    
        return "{$prefix}-{$newNumber}";
    }
}