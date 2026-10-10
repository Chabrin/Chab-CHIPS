<?php

namespace App\Exports;

use App\Models\EmployeeAttendance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    /**
     * Optionally restrict the export to one date and/or approval status.
     */
    public function __construct(private ?string $date = null, private ?string $status = null) {}

    /**
     * Excel column headers.
     */
    public function headings(): array {
        return ['Staff Number', 'Employee Name', 'Date', 'Time In', 'Time Out', 'Hours', 'Status', 'Created By', 'Uploaded At'];
    }

    /**
     * Flatten attendance records into one row per timed entry.
     */
    public function collection(): Collection {
        $records = EmployeeAttendance::with(['staff', 'employee_attendance_timed', 'createdBy'])
            ->when($this->status, fn($q, $s) => $q->where('current_status_id', $s))
            ->when($this->date, fn($q, $d) => $q->whereHas(
                'employee_attendance_timed',
                fn($t) => $t->whereDate('date', $d)
            ))
            ->orderBy('date_time', 'desc')
            ->get();

        $statuses = [1 => 'Pending', 2 => 'Approved'];
        $rows = collect();

        foreach ($records as $att) {
            foreach ($att->employee_attendance_timed as $t) {
                if ($this->date && date('Y-m-d', strtotime($t->date)) !== $this->date) {
                    continue;
                }
                $rows->push([
                    $att->employee_id,
                    $att->staff->full_names ?? '',
                    $t->date ? date('Y-m-d', strtotime($t->date)) : '',
                    $t->time_in,
                    $t->time_out,
                    $t->hours,
                    $statuses[$att->current_status_id] ?? $att->current_status_id,
                    $att->createdBy->name ?? '',
                    $att->date_time ? $att->date_time->format('Y-m-d H:i') : '',
                ]);
            }
        }

        return $rows;
    }
}