<?php
namespace App\Jobs;

use App\Models\EmployeeAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProcessAttendanceUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;

    public function __construct($filePath, $userId)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle()
    {
        $absolutePath = storage_path('app/private/' . $this->filePath);
        Log::info('ProcessAttendanceUpload: Looking for file at ' . $absolutePath);
        if (!file_exists($absolutePath)) {
            Log::error('ProcessAttendanceUpload: File not found at ' . $absolutePath);
            return;
        }
        if (($handle = fopen($absolutePath, 'r')) !== false) {
            $header = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);
                
                $attendance = EmployeeAttendance::create([
                    'employee_id'      => $row['Employee ID'] ?? null,
                    'date_time'        => now(),
                    'month_year'       => $row['Month/Year'] ?? null,
                    'current_status_id'=> 1,
                    'created_by_id'    => $this->userId,
                ]);

                $timed = $attendance->employee_attendance_timed()->create([
                    'parent_id' => $attendance->id,
                    'date'      => $row['Date'] ?? null,
                    'time_in'   => $row['Time In'] ?? null,
                    'time_out'  => $row['Time Out'] ?? null,
                    'hours'     => $row['Hours'] ?? null,
                    'current_status_id' => 1,
                ]);
            }
            fclose($handle);
        }
    }
}