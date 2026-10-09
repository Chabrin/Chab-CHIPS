<?php

namespace App\Exports;

use App\Models\Staff;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StaffExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Eager-load relationships to avoid N+1 queries.
     */
    public function collection()
    {
        return Staff::with([
            'position',
            'zone:id,zone_name',
            'zonesManaged:id,zone_name,zone_manager_id',
            'zonesSupervised:id,zone_name,zone_supervisor_id'
        ])
        ->orderBy('full_names', 'asc')
        ->get();
    }

    /**
     * Excel Column Headers
     */
    public function headings(): array
    {
        return [
            'Staff No',
            'Employee Name',
            'Position',
            'Gender',
            'Contact',
            'National ID',
            'Zone Roles',
            'Status',
        ];
    }

    /**
     * Map each staff row to the matching column
     */
    public function map($member): array
    {
        // Consolidate any managed or supervised zone roles into a comma-separated string
        $zoneRoles = collect();

        if ($member->zonesManaged->isNotEmpty()) {
            $zoneRoles->push('Manager: ' . $member->zonesManaged->pluck('zone_name')->implode(', '));
        }

        if ($member->zonesSupervised->isNotEmpty()) {
            $zoneRoles->push('Supervisor: ' . $member->zonesSupervised->pluck('zone_name')->implode(', '));
        }

        if ($zoneRoles->isEmpty() && $member->zone) {
            $zoneRoles->push($member->zone->zone_name);
        }

        return [
            $member->staff_number ?? '—',
            $member->full_names ?? '—',
            $member->position->position_name ?? ($member->position->title ?? '—'),
            ucfirst($member->gender ?? '—'),
            $member->mobile_number ?? '—',
            $member->national_id ?? '—',
            $zoneRoles->isNotEmpty() ? $zoneRoles->implode(' | ') : '—',
            $member->current_status_id == 1 ? 'Active' : 'Inactive', // Adjust status mapping logic if using a relation/enum
        ];
    }
}