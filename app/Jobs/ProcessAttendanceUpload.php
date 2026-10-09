<?php

namespace App\Jobs;

use App\Models\EmployeeAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ProcessAttendanceUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 600;

    protected $filePath;
    protected $userId;

    public function __construct($filePath, $userId)
    {
        $this->filePath = $filePath;
        $this->userId   = $userId;
    }

    public function handle()
    {
        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($this->filePath)) {
            Log::error('ProcessAttendanceUpload: file not found: ' . $this->filePath);
            return;
        }

        // Works for both .csv and .xlsx
        $sheets = Excel::toArray(new \stdClass, $this->filePath, $disk);
        $rows   = $sheets[0] ?? [];

        if (count($rows) < 2) {
            Log::warning('ProcessAttendanceUpload: file has no data rows.');
            Storage::disk($disk)->delete($this->filePath);
            return;
        }

        // Normalise headers: trim, lowercase, strip BOM
        $header = array_map(
            fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
            array_shift($rows)
        );

        $imported = 0;
        $skipped  = 0;

        foreach ($rows as $i => $data) {
            $line = $i + 2; // human line number (header = line 1)

            // Skip fully blank rows
            if (!array_filter($data, fn($v) => $v !== null && trim((string) $v) !== '')) {
                continue;
            }

            // Pad/trim so combine never throws
            $data = array_slice(array_pad($data, count($header), null), 0, count($header));
            $row  = array_combine($header, $data);

           $employeeId = trim((string) (
                $row['staff id'] ?? $row['employee id'] ?? $row['staff number'] ??
                $row['staff no'] ?? $row['staff_id'] ?? $row['employee_id'] ?? ''
            ));
            $date = $this->toDate($row['date'] ?? $row['attendance date'] ?? null);

            if ($employeeId === '' || !$date) {
                Log::warning("ProcessAttendanceUpload: skipped line {$line} (missing staff id or date).");
                $skipped++;
                continue;
            }

            $timeIn  = $this->toDateTime($row['time in'] ?? null, $date);
            $timeOut = $this->toDateTime($row['time out'] ?? null, $date);

            $hours = $row['hours'] ?? null;
            if (($hours === null || $hours === '') && $timeIn && $timeOut) {
                $hours = round((strtotime($timeOut) - strtotime($timeIn)) / 3600, 2);
            }

            try {
                DB::transaction(function () use ($employeeId, $row, $date, $timeIn, $timeOut, $hours) {
                    $attendance = EmployeeAttendance::create([
                        'employee_id'       => $employeeId,
                        'date_time'         => now(),
                        'month_year'        => $row['month/year'] ?? date('m/Y', strtotime($date)),
                        'current_status_id' => 1,
                        'created_by_id'     => $this->userId,
                    ]);

                    $attendance->employee_attendance_timed()->create([
                        'date'              => $date,
                        'time_in'           => $timeIn,
                        'time_out'          => $timeOut,
                        'hours'             => $hours,
                        'current_status_id' => 1,
                    ]);
                });
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                Log::error("ProcessAttendanceUpload: line {$line} failed: " . $e->getMessage());
            }
        }

        Log::info("ProcessAttendanceUpload: done. Imported {$imported}, skipped {$skipped}.");
        Storage::disk($disk)->delete($this->filePath);
    }

    public function failed(\Throwable $e)
    {
        Log::error('ProcessAttendanceUpload failed: ' . $e->getMessage());
    }

    private function toDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function toDateTime($value, string $fallbackDate): ?string
    {
        if ($value === null || $value === '') return null;

        if (is_numeric($value)) {
            $dt = ExcelDate::excelToDateTimeObject((float) $value);
            // Time-only Excel cell (fraction of a day) -> attach the row's date
            if ((float) $value < 1) {
                return $fallbackDate . ' ' . $dt->format('H:i:s');
            }
            return $dt->format('Y-m-d H:i:s');
        }

        $str = trim((string) $value);
        // "08:00" only -> attach the row's date
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str)) {
            return $fallbackDate . ' ' . $str;
        }
        $ts = strtotime($str);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}