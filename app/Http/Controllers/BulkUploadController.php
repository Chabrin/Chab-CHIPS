<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Zone;
use App\Models\Client;
use App\Models\Property;
use App\Models\ZoneArea;
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
            
            if ($type === 'properties') {
                return $this->generatePropertiesSample($clientId);
            } elseif ($type === 'units') {
                return $this->generateUnitsSample($clientId);
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
     * Generate units sample file
     */
    private function generateUnitsSample($clientId)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Get client properties for reference
        $properties = Property::where('client_id', $clientId)
            ->select('reference_number', 'property_name')
            ->get();
        
        // Set headers
        $headers = [
            'Property Reference',
            'Unit Number',
            'Unit Name',
            'Unit Type',
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
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
        
        // Add sample data
        $propertyRef = $properties->first() ? $properties->first()->reference_number : '1A';
        $sampleData = [
            [$propertyRef, 'Shop 1', 'Unit A1', 'Commercial', 'Commercial', 'Good', '1 Bedroom', 'Standard', '50000', 'Ground floor shop', '0', '0'],
            [$propertyRef, 'Apt 101', 'Apartment 101', 'Residential', 'Residential', 'Excellent', '2 Bedroom', 'Premium', '35000', '2 bedroom apartment', '0', '100'],
        ];
        
        $sheet->fromArray($sampleData, null, 'A2');
        
        // Add available properties as reference
        if ($properties->count() > 0) {
            $sheet->setCellValue('L1', 'Available Properties:');
            $sheet->getStyle('L1')->getFont()->setBold(true);
            $row = 2;
            foreach ($properties as $property) {
                $sheet->setCellValue('L' . $row, $property->reference_number . ' - ' . $property->property_name);
                $row++;
            }
        }
        
        // Auto-size columns
        foreach (range('A', 'L') as $col) {
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
            ]);
            
            $uploadType = $request->upload_type;
            $clientId = $request->client_id;
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
                $result = $this->processUnits($data, $clientId);
            } elseif ($uploadType === 'tenants') {
                $result = $this->processTenants($data, $clientId);
            }
            
            DB::commit();
            
            return redirect()->route('property.clients.show', Crypt::encrypt($clientId))
                ->with('success', "Bulk upload successful! Created {$result['success']} records. {$result['failed']} failed.");
            
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
                $zone = Zone::where('zone_name', $row[1])->first();
                if (!$zone) {
                    $errors[] = "Row {$rowNum}: Zone '{$row[1]}' not found";
                    Log::error("Row {$rowNum}: Zone not found - " . json_encode($row));
                    $failed++;
                    continue;
                }
                
                // Find zone area by name
                $zoneArea = ZoneArea::where('zone_area_name', $row[2])
                    ->where('zone_id', $zone->id)
                    ->first();
                if (!$zoneArea) {
                    $errors[] = "Row {$rowNum}: Zone area '{$row[2]}' not found in zone '{$row[1]}'";
                    Log::error("Row {$rowNum}: Zone area not found - " . json_encode($row));
                    $failed++;
                    continue;
                }
                
                // Find zone manager by name (optional)
                $zoneManagerId = null;
                if (!empty($row[3])) {
                    $zoneManager = DB::table('sys_hrm_employees')
                        ->whereRaw('LOWER(full_names) = ?', [strtolower($row[3])])
                        ->first();
                    if ($zoneManager) {
                        $zoneManagerId = $zoneManager->id;
                    } else {
                        Log::warning("Row {$rowNum}: Zone manager '{$row[3]}' not found, proceeding without");
                    }
                }
                
                // Find zone supervisor by name (optional)
                $zoneSupervisorId = null;
                if (!empty($row[4])) {
                    $zoneSupervisor = DB::table('sys_hrm_employees')
                        ->whereRaw('LOWER(full_names) = ?', [strtolower($row[4])])
                        ->first();
                    if ($zoneSupervisor) {
                        $zoneSupervisorId = $zoneSupervisor->id;
                    } else {
                        Log::warning("Row {$rowNum}: Zone supervisor '{$row[4]}' not found, proceeding without");
                    }
                }
                
                // Find field officer by name (optional)
                $fieldOfficerId = null;
                if (!empty($row[5])) {
                    $fieldOfficer = DB::table('sys_hrm_employees')
                        ->whereRaw('LOWER(full_names) = ?', [strtolower($row[5])])
                        ->first();
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
                        ->where('account_number', $row[6])
                        ->first();
                    if ($bankAccount) {
                        $bankAccountId = $bankAccount->id;
                    } else {
                        Log::warning("Row {$rowNum}: Settlement account '{$row[6]}' not found for this client, proceeding without");
                    }
                }
                
                // Generate reference number
                $zoneName = strtoupper($zone->zone_name);
                $lastProperty = Property::where('reference_number', 'LIKE', "%{$zoneName}")
                    ->orderByRaw('CAST(SUBSTRING(reference_number, 1, LENGTH(reference_number) - LENGTH("' . $zoneName . '")) AS UNSIGNED) DESC')
                    ->first();
                
                if ($lastProperty) {
                    $lastNumber = intval(preg_replace('/[^0-9]/', '', substr($lastProperty->reference_number, 0, -strlen($zoneName))));
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }
                
                $referenceNumber = $newNumber . $zoneName;
                
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
                    'acquisition_date' => !empty($row[9]) ? date('Y-m-d', strtotime($row[9])) : null,
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
     * Process units bulk upload
     */
    private function processUnits($data, $clientId)
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($data as $index => $row) {
            try {
                // Skip empty rows
                if (empty($row[0])) continue;
                
                $rowNum = $index + 2; // +2 because Excel is 1-indexed and has header row
                
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
                
                // Find topology by name (optional)
                $topologyId = null;
                if (!empty($row[6])) {
                    $topology = DB::table('sys_classifications')
                        ->where('classification_name', 'Unit Topology')
                        ->whereRaw('LOWER(name) = ?', [strtolower($row[6])])
                        ->first();
                    if ($topology) {
                        $topologyId = $topology->id;
                    } else {
                        Log::warning("Row {$rowNum}: Topology '{$row[6]}' not found, proceeding without");
                    }
                }
                
                // Find category by name (optional)
                $categoryId = null;
                if (!empty($row[7])) {
                    $category = DB::table('sys_classifications')
                        ->where('classification_name', 'Unit Category')
                        ->whereRaw('LOWER(name) = ?', [strtolower($row[7])])
                        ->first();
                    if ($category) {
                        $categoryId = $category->id;
                    } else {
                        Log::warning("Row {$rowNum}: Category '{$row[7]}' not found, proceeding without");
                    }
                }
                
                // Generate unit code
                $lastUnit = Unit::where('property_id', $property->id)
                    ->orderBy('unit_code', 'desc')
                    ->first();
                
                if ($lastUnit && preg_match('/(\d+)$/', $lastUnit->unit_code, $matches)) {
                    $newNumber = str_pad(intval($matches[1]) + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = '001';
                }
                $unitCode = $property->reference_number . '-U' . $newNumber;
                
                // Map types (simplified - adjust based on your actual type IDs)
                $unitTypeMap = ['commercial' => 1, 'residential' => 2, 'hybrid' => 3];
                $unitType = $unitTypeMap[strtolower($row[3])] ?? 1;
                $usageType = $unitTypeMap[strtolower($row[4])] ?? 1;
                
                Unit::create([
                    'unit_code' => $unitCode,
                    'property_id' => $property->id,
                    'client_id' => $clientId,
                    'zone_id' => $property->zone_id,
                    'unit_number' => $row[1],
                    'unit_name' => $row[2] ?? null,
                    'unit_type_id' => $unitType,
                    'usage_type_id' => $usageType,
                    'unit_condition_id' => $row[5] ?? 'Good',
                    'topology_id' => $topologyId,
                    'category_id' => $categoryId,
                    'rent_amount' => $row[8] ?? 0,
                    'description' => $row[9] ?? null,
                    'vat_able' => $row[10] ?? 0,
                    'initial_water_meter_reading' => $row[11] ?? 0,
                    'date_time' => now(),
                    'created_by_id' => Auth::id(),
                    'occupancy_status_id' => 31,
                    'current_status_id' => 31,
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