<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfirmationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class PropertyConfirmationController extends Controller
{
    private $workflowStatuses = [
        1 => 'Pending',
        2 => 'Paid',
        3 => 'Cancelled',
        4 => 'Suspended',
        5 => 'Approved',
        6 => 'Posted',
        7 => 'Converted',
        8 => 'Partially Cleared',
        9 => 'Cleared',
        10 => 'N/A',
        11 => 'Unallocated',
        12 => 'Partially Allocated',
        13 => 'Fully Allocated',
        15 => 'Reconciled',
        16 => 'Unreconciled',
        17 => 'Processed',
        18 => 'Unmatched',
        19 => 'Completed',
        20 => 'Not Posted',
        21 => 'Renewed',
        22 => 'Expired',
        23 => 'Partially Posted',
        24 => 'Partially Approved',
        25 => 'Sent',
        26 => 'Failed',
        27 => 'Offsetted',
        28 => 'Terminated',
        29 => 'Withdrawn',
        30 => 'Blacklisted',
        31 => 'Vacant',
        32 => "Owner's Account",
        33 => 'Maintenance',
        34 => 'Paid Vacant',
        35 => 'Sat Deposit',
        36 => 'Absconded',
        37 => 'Paid Owner',
        38 => 'Occupied',
        39 => 'Unreconciled Amount',
        40 => 'Solved',
        41 => 'Rejected',
        42 => 'Closed',
        46 => 'Deleted',
        47 => 'Exported',
        48 => 'Vacation Request',
        49 => 'Active',
        50 => 'On Leave',
        60 => 'Read',
        61 => 'Unread',
        62 => 'Returns Cleared',
        70 => 'Topped Up',
        71 => 'Utilized',
        72 => 'Cleared Voucher',
        75 => 'FO Approved',
        77 => 'ZM Approved',
        78 => 'PM Approved',
        79 => 'Clerical Approved',
        81 => 'Paid Arrears',
        82 => 'SFO Approved',
        83 => 'Internal Audit Approved',
        84 => 'Received',
        85 => 'Checked',
        87 => 'Verified',
        88 => 'Reset',
        89 => 'Deleted',
        91 => 'Rescheduled',
        94 => 'Reinstated',
    ];

    public function index()
    {
        $approvalSections = [
            ['permission' => 'update_clients', 'label' => 'Clients'],
            ['permission' => 'update_properties', 'label' => 'Properties'],
            ['permission' => 'update_units', 'label' => 'Units'],
            ['permission' => 'update_tenants', 'label' => 'Tenants'],
            ['permission' => 'update_service_agreements', 'label' => 'Service Agreements'],
            ['permission' => 'update_recoveries', 'label' => 'Recoveries'],
            ['permission' => 'update_lease_agreements', 'label' => 'Lease Agreements'],
            ['permission' => 'update_inspections', 'label' => 'Inspections'],
            ['permission' => 'update_tenant_movements', 'label' => 'Tenant Movements'],
            ['permission' => 'update_tenant_blacklist', 'label' => 'Tenant Blacklist'],
            ['permission' => 'update_repair_requests', 'label' => 'Repairs & Maintenance'],
        ];


        return view('property.confirmations.index', compact('approvalSections'));
    }

    private function logConfirmation($section, $action, $ids, $approvalStage = null, $metadata = null)
    {
        ConfirmationLog::create([
            'section' => $section,
            'model' => $this->getModelClass($section),
            'action' => $action,
            'ids' => is_array($ids) ? $ids : [$ids],
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
            'approval_stage' => $approvalStage,
            'metadata' => $metadata,
        ]);
    }

    private function getModelClass($section)
    {
        $map = [
            'Clients' => \App\Models\Client::class,
            'Properties' => \App\Models\Property::class,
            'Units' => \App\Models\Unit::class,
            'Tenants' => \App\Models\Client::class,
            'Service Agreements' => \App\Models\ServiceAgreement::class,
            'Recoveries' => \App\Models\Recovery::class,
            'Lease Agreements' => \App\Models\LeaseAgreement::class,
            'Inspections' => \App\Models\Inspection::class,
            'Tenant Movements' => \App\Models\TenantMovement::class,
            'Tenant Blacklist' => \App\Models\TenantBlacklist::class,
            'Repairs & Maintenance' => \App\Models\RepairRequest::class,
        ];
        return $map[$section] ?? '';
    }

    public function approvalsList(Request $request)
    {
        $section = $request->get('section');
        $records = [];

        switch ($section) {
            case 'Clients':
                $records = \App\Models\Client::where('current_status_id', 1) // Pending
                    ->where('reference_number', 'LIKE', 'LAN%') // Only landlords/clients
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($client) {
                        return [
                            'id' => $client->id,
                            'encrypted_id' => Crypt::encrypt($client->id),
                            'reference_number' => $client->reference_number ?? '-',
                            'names' => $client->names ?? '-',
                            'email' => $client->email_address ?? '-',
                            'phone_number' => $client->mobile_number ?? '-',
                            'client_type' => 'Landlord',
                            'zone' => '-',
                            'created_at' => $client->date_time ? $client->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $client->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Properties':
                $records = \App\Models\Property::where('current_status_id', 1) // Pending
                    ->with(['client', 'zone'])
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($property) {
                        return [
                            'id' => $property->id,
                            'encrypted_id' => Crypt::encrypt($property->id),
                            'reference_number' => $property->reference_number ?? '-',
                            'property_name' => $property->property_name ?? '-',
                            'client' => $property->client->names ?? '-',
                            'zone' => $property->zone->zone_name ?? '-',
                            'property_type' => '-',
                            'created_at' => $property->date_time ? $property->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $property->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Units':
                $records = \App\Models\Unit::where('current_status_id', 1) // Pending
                    ->with(['property', 'client'])
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($unit) {
                        return [
                            'id' => $unit->id,
                            'encrypted_id' => Crypt::encrypt($unit->id),
                            'unit_code' => $unit->unit_code ?? '-',
                            'unit_number' => $unit->unit_number ?? '-',
                            'property' => $unit->property->property_name ?? '-',
                            'client' => $unit->client->names ?? '-',
                            'unit_type' => '-',
                            'occupancy_status' => $this->workflowStatuses[$unit->occupancy_status_id] ?? '-',
                            'created_at' => $unit->date_time ? $unit->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $unit->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenants':
                $records = \App\Models\Client::where('current_status_id', 1) // Pending
                    ->where('reference_number', 'LIKE', 'TEN%') // Only tenants
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($tenant) {
                        return [
                            'id' => $tenant->id,
                            'encrypted_id' => Crypt::encrypt($tenant->id),
                            'reference_number' => $tenant->reference_number ?? '-',
                            'names' => $tenant->names ?? '-',
                            'email' => $tenant->email_address ?? '-',
                            'phone_number' => $tenant->mobile_number ?? '-',
                            'id_number' => $tenant->national_id ?? '-',
                            'created_at' => $tenant->date_time ? $tenant->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $tenant->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Service Agreements':
                $records = \App\Models\ServiceAgreement::where('current_status_id', 1) // Pending
                    ->with(['property', 'client'])
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($sla) {
                        return [
                            'id' => $sla->id,
                            'encrypted_id' => Crypt::encrypt($sla->id),
                            'ref_number' => $sla->reference_number ?? '-',
                            'property' => $sla->property->property_name ?? '-',
                            'client' => $sla->client->names ?? '-',
                            'date_start' => $sla->date_start ? \Carbon\Carbon::parse($sla->date_start)->format('d-m-Y') : '-',
                            'date_end' => $sla->date_end ? \Carbon\Carbon::parse($sla->date_end)->format('d-m-Y') : '-',
                            'duration_years' => $sla->years ?? '-',
                            'created_at' => $sla->date_time ? $sla->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $sla->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Recoveries':
                $records = \App\Models\Recovery::where('current_status_id', 1) // Pending
                    ->with(['client', 'property', 'zone'])
                    ->orderBy('recovery_date', 'desc')
                    ->get()
                    ->map(function ($recovery) {
                        return [
                            'id' => $recovery->id,
                            'encrypted_id' => Crypt::encrypt($recovery->id),
                            'ref_number' => $recovery->ref_number ?? '-',
                            'recovery_date' => $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-',
                            'amount' => number_format($recovery->amount ?? 0, 2),
                            'client' => $recovery->client->names ?? '-',
                            'property' => $recovery->property->property_name ?? '-',
                            'zone' => $recovery->zone->zone_name ?? '-',
                            'commisionable' => $recovery->commisionable ?? 'No',
                            'created_at' => $recovery->date_time ? $recovery->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $recovery->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Lease Agreements':
                $records = \App\Models\LeaseAgreement::where('current_status_id', 1) // Pending
                    ->with(['tenant', 'property', 'unit'])
                    ->orderBy('date_start', 'desc')
                    ->get()
                    ->map(function ($lease) {
                        return [
                            'id' => $lease->id,
                            'encrypted_id' => Crypt::encrypt($lease->id),
                            'ref_number' => $lease->ref_number ?? '-',
                            'tenant' => $lease->tenant->names ?? '-',
                            'property' => $lease->property->property_name ?? '-',
                            'unit' => $lease->unit->unit_number ?? '-',
                            'date_start' => $lease->date_start ? \Carbon\Carbon::parse($lease->date_start)->format('d-m-Y') : '-',
                            'date_end' => $lease->date_end ? \Carbon\Carbon::parse($lease->date_end)->format('d-m-Y') : '-',
                            'rent_amount' => '-',
                            'created_at' => $lease->date_time ? $lease->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => '-',
                        ];
                    });
                break;

            case 'Inspections':
                $records = \App\Models\Inspection::where('current_status_id', 1) // Pending
                    ->with(['zone', 'property', 'createdBy'])
                    ->orderBy('visited_date_time', 'desc')
                    ->get()
                    ->map(function ($inspection) {
                        return [
                            'id' => $inspection->id,
                            'encrypted_id' => Crypt::encrypt($inspection->id),
                            'inspection_date' => $inspection->visited_date_time ? \Carbon\Carbon::parse($inspection->visited_date_time)->format('d-m-Y') : '-',
                            'zone' => $inspection->zone->zone_name ?? '-',
                            'property' => $inspection->property->property_name ?? '-',
                            'inspection_type' => '-',
                            'general_remarks' => '-',
                            'created_at' => $inspection->date_time ? $inspection->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $inspection->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenant Movements':
                $records = \App\Models\TenantMovement::where('current_status_id', 1) // Pending
                    ->with(['client', 'property', 'unitFrom', 'unitTo', 'lease', 'createdBy'])
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($movement) {
                        return [
                            'id' => $movement->id,
                            'encrypted_id' => Crypt::encrypt($movement->id),
                            'ref_number' => $movement->ref_number ?? '-',
                            'client' => $movement->client->names ?? '-',
                            'property' => $movement->property->property_name ?? '-',
                            'unit_from' => $movement->unitFrom->unit_code ?? '-',
                            'unit_to' => $movement->unitTo->unit_code ?? '-',
                            'lease' => $movement->lease->ref_number ?? '-',
                            'effective_date' => $movement->effective_date ? \Carbon\Carbon::parse($movement->effective_date)->format('d-m-Y') : '-',
                            'created_at' => $movement->date_time ? $movement->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $movement->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Tenant Blacklist':
                $records = \App\Models\TenantBlacklist::where('current_status_id', 1) // Pending
                    ->with(['tenant', 'client', 'property', 'unit', 'lease', 'reason', 'zone', 'createdBy'])
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($blacklist) {
                        return [
                            'id' => $blacklist->id,
                            'encrypted_id' => Crypt::encrypt($blacklist->id),
                            'blacklist_date' => $blacklist->blacklist_date ? \Carbon\Carbon::parse($blacklist->blacklist_date)->format('d-m-Y') : '-',
                            'tenant' => $blacklist->tenant->names ?? '-',
                            'client' => $blacklist->client->names ?? '-',
                            'property' => $blacklist->property->property_name ?? '-',
                            'unit' => $blacklist->unit->unit_code ?? '-',
                            'lease' => $blacklist->lease->ref_number ?? '-',
                            'reason' => $blacklist->reason->reason_name ?? '-',
                            'description' => $blacklist->description ?? '-',
                            'current_balance' => number_format($blacklist->current_balance ?? 0, 2),
                            'zone' => $blacklist->zone->zone_name ?? '-',
                            'created_at' => $blacklist->date_time ? $blacklist->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $blacklist->createdBy->name ?? '-',
                        ];
                    });
                break;

            case 'Repairs & Maintenance':
                $records = \App\Models\RepairRequest::where('current_status_id', 1) // Pending
                    ->with(['property', 'client', 'zone', 'createdBy'])
                    ->orderBy('date_time', 'desc')
                    ->get()
                    ->map(function ($repair) {
                        return [
                            'id' => $repair->id,
                            'encrypted_id' => Crypt::encrypt($repair->id),
                            'ref_number' => $repair->ref_number ?? '-',
                            'property' => $repair->property->property_name ?? '-',
                            'client' => $repair->client->names ?? '-',
                            'zone' => $repair->zone->zone_name ?? '-',
                            'visited_date' => $repair->date_time ? \Carbon\Carbon::parse($repair->date_time)->format('d-m-Y H:i') : '-',
                            'description' => $repair->description ?? '-',
                            'created_at' => $repair->date_time ? $repair->date_time->format('d-m-Y H:i') : '-',
                            'created_by_name' => $repair->createdBy->name ?? '-',
                        ];
                    });
                break;
        }

        return response()->json(['records' => $records]);
    }

    public function approve(Request $request, $id)
    {
        $section = $request->input('section');
        
        try {
            $previousStatus = null;
            $newStatus = null;
            
            switch ($section) {
                case 'Clients':
                    $record = \App\Models\Client::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Properties':
                    $record = \App\Models\Property::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 5; // Approved
                    $newStatus = 5;
                    $record->save();
                    break;

                case 'Units':
                    $record = \App\Models\Unit::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;

                case 'Tenants':
                    $record = \App\Models\Client::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;

                case 'Service Agreements':
                    $record = \App\Models\ServiceAgreement::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;

                case 'Recoveries':
                    $record = \App\Models\Recovery::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;

                case 'Lease Agreements':
                    $record = \App\Models\LeaseAgreement::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;

                case 'Inspections':
                    $record = \App\Models\Inspection::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;

                case 'Tenant Movements':
                    $record = \App\Models\TenantMovement::findOrFail($id);
                    $record->current_status_id = 5; // Confirmed
                    $record->save();
                    break;

                case 'Tenant Blacklist':
                    $record = \App\Models\TenantBlacklist::findOrFail($id);
                    $record->current_status_id = 17; // Processed
                    $record->save();
                    break;

                case 'Repairs & Maintenance':
                    $record = \App\Models\RepairRequest::findOrFail($id);
                    $record->current_status_id = 5; // Approved
                    $record->save();
                    break;
            }

            $this->logConfirmation($section, 'approve', $id, null, [
                'previous_status_id' => $previousStatus,
                'new_status_id' => $newStatus,
                'action_type' => 'approval'
            ]);

            return response()->json(['success' => true, 'message' => 'Record approved successfully']);
        } catch (\Exception $e) {
            Log::error('Error approving record: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to approve record'], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $section = $request->input('section');
        $rejectionReason = $request->input('rejection_reason', null);
        
        try {
            $previousStatus = null;
            $newStatus = null;
            
            switch ($section) {
                case 'Clients':
                    $record = \App\Models\Client::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Properties':
                    $record = \App\Models\Property::findOrFail($id);
                    $previousStatus = $record->current_status_id;
                    $record->current_status_id = 41; // Rejected
                    $newStatus = 41;
                    $record->save();
                    break;

                case 'Units':
                    $record = \App\Models\Unit::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Tenants':
                    $record = \App\Models\Client::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Service Agreements':
                    $record = \App\Models\ServiceAgreement::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Recoveries':
                    $record = \App\Models\Recovery::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Lease Agreements':
                    $record = \App\Models\LeaseAgreement::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Inspections':
                    $record = \App\Models\Inspection::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Tenant Movements':
                    $record = \App\Models\TenantMovement::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Tenant Blacklist':
                    $record = \App\Models\TenantBlacklist::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;

                case 'Repairs & Maintenance':
                    $record = \App\Models\RepairRequest::findOrFail($id);
                    $record->current_status_id = 41; // Rejected
                    $record->save();
                    break;
            }

            $this->logConfirmation($section, 'reject', $id, null, [
                'rejection_reason' => $rejectionReason,
                'previous_status_id' => $previousStatus,
                'new_status_id' => $newStatus,
                'action_type' => 'rejection'
            ]);

            return response()->json(['success' => true, 'message' => 'Record rejected successfully']);
        } catch (\Exception $e) {
            Log::error('Error rejecting record: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to reject record'], 500);
        }
    }

    public function bulkApprove(Request $request)
    {
        $ids = $request->input('ids', []);
        $section = $request->input('section');
        
        try {
            switch ($section) {
                case 'Clients':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Properties':
                    \App\Models\Property::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Units':
                    \App\Models\Unit::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Tenants':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Service Agreements':
                    \App\Models\ServiceAgreement::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Recoveries':
                    \App\Models\Recovery::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Lease Agreements':
                    \App\Models\LeaseAgreement::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Inspections':
                    \App\Models\Inspection::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Tenant Movements':
                    \App\Models\TenantMovement::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;

                case 'Tenant Blacklist':
                    \App\Models\TenantBlacklist::whereIn('id', $ids)->update(['current_status_id' => 17]);
                    break;

                case 'Repairs & Maintenance':
                    \App\Models\RepairRequest::whereIn('id', $ids)->update(['current_status_id' => 5]);
                    break;
            }

            $this->logConfirmation($section, 'bulk approve', $ids, null, [
                'records_count' => count($ids),
                'new_status_id' => 5,
                'action_type' => 'bulk_approval'
            ]);

            return response()->json(['success' => true, 'message' => 'Records approved successfully']);
        } catch (\Exception $e) {
            Log::error('Error bulk approving records: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to approve records'], 500);
        }
    }

    public function bulkReject(Request $request)
    {
        $ids = $request->input('ids', []);
        $section = $request->input('section');
        $rejectionReason = $request->input('rejection_reason', null);
        
        try {
            switch ($section) {
                case 'Clients':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Properties':
                    \App\Models\Property::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Units':
                    \App\Models\Unit::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenants':
                    \App\Models\Client::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Service Agreements':
                    \App\Models\ServiceAgreement::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Recoveries':
                    \App\Models\Recovery::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Lease Agreements':
                    \App\Models\LeaseAgreement::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Inspections':
                    \App\Models\Inspection::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenant Movements':
                    \App\Models\TenantMovement::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Tenant Blacklist':
                    \App\Models\TenantBlacklist::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;

                case 'Repairs & Maintenance':
                    \App\Models\RepairRequest::whereIn('id', $ids)->update(['current_status_id' => 41]);
                    break;
            }

            $this->logConfirmation($section, 'bulk reject', $ids, null, [
                'rejection_reason' => $rejectionReason,
                'records_count' => count($ids),
                'new_status_id' => 41,
                'action_type' => 'bulk_rejection'
            ]);

            return response()->json(['success' => true, 'message' => 'Records rejected successfully']);
        } catch (\Exception $e) {
            Log::error('Error bulk rejecting records: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to reject records'], 500);
        }
    }
}
