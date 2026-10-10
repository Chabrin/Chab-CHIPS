<?php

namespace App\Jobs;

use App\Models\EmployeeAttendance;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Create a job that imports the uploaded attendance file for the given user.
     */
    public function __construct($filePath, $userId) {
        $this->filePath = $filePath;
        $this->userId   = $userId;
    }

    /**
     * Parse the uploaded CSV/XLSX and create attendance records, skipping invalid and duplicate rows.
     */
    public function handle() {
        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($this->filePath)) {
            Log::error('ProcessAttendanceUpload: file not found: ' . $this->filePath);
            self::storeResult($this->userId, 0, 0, ['The uploaded file could not be found.']);
            return;
        }

        // Works for both .csv and .xlsx
        $sheets = Excel::toArray(new \stdClass, $this->filePath, $disk);
        $rows   = $sheets[0] ?? [];

        if (count($rows) < 2) {
            Log::warning('ProcessAttendanceUpload: file has no data rows.');
            Storage::disk($disk)->delete($this->filePath);
            self::storeResult($this->userId, 0, 0, ['The file has no data rows.']);
            return;
        }

        // Normalise headers: trim, lowercase, strip BOM
        $header = array_map(
            fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
            array_shift($rows)
        );

        // staff_number is zero-padded text ("004") but Excel/the integer employee_id column drop the padding, so compare normalised
        $knownStaff = Staff::pluck('staff_number')->map(fn($n) => $this->normaliseStaffNumber($n))->flip();

        $imported = 0;
        $skipped  = 0;
        $reasons  = [];

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
                $reasons[] = "Line {$line}: missing staff ID or date";
                $skipped++;
                continue;
            }

            $employeeId = $this->normaliseStaffNumber($employeeId);

            if (!$knownStaff->has($employeeId)) {
                Log::warning("ProcessAttendanceUpload: skipped line {$line} (unknown staff number {$employeeId}).");
                $reasons[] = "Line {$line}: unknown staff number {$employeeId}";
                $skipped++;
                continue;
            }

            $alreadyImported = EmployeeAttendance::where('employee_id', $employeeId)
                ->whereHas('employee_attendance_timed', fn($t) => $t->whereDate('date', $date))
                ->exists();
            if ($alreadyImported) {
                Log::warning("ProcessAttendanceUpload: skipped line {$line} (attendance for {$employeeId} on {$date} already exists).");
                $reasons[] = "Line {$line}: attendance for staff {$employeeId} on {$date} already exists";
                $skipped++;
                continue;
            }

            $timeIn  = $this->toDateTime($row['time in'] ?? null, $date);
            $timeOut = $this->toDateTime($row['time out'] ?? null, $date);

            // Overnight shift: time out falls on the next day
            if ($timeIn && $timeOut && strtotime($timeOut) < strtotime($timeIn)) {
                $timeOut = date('Y-m-d H:i:s', strtotime($timeOut . ' +1 day'));
            }

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
                $reasons[] = "Line {$line}: could not be saved";
                Log::error("ProcessAttendanceUpload: line {$line} failed: " . $e->getMessage());
            }
        }

        Log::info("ProcessAttendanceUpload: done. Imported {$imported}, skipped {$skipped}.");
        Storage::disk($disk)->delete($this->filePath);
        self::storeResult($this->userId, $imported, $skipped, $reasons);
    }

    /**
     * Strip leading zeros from all-digit staff numbers so "004" and "4" compare equal.
     */
    private function normaliseStaffNumber($value): string {
        $value = trim((string) $value);
        return ctype_digit($value) ? (ltrim($value, '0') ?: '0') : $value;
    }

    /**
     * Log the failure when the queued job exhausts its attempts.
     */
    public function failed(\Throwable $e) {
        Log::error('ProcessAttendanceUpload failed: ' . $e->getMessage());
        self::storeResult($this->userId, 0, 0, ['The upload failed unexpectedly. Please try again or contact support.']);
    }

    /**
     * Cache key holding the latest upload summary for the given user.
     */
    public static function resultKey($userId): string {
        return 'attendance_upload_result:' . $userId;
    }

    /**
     * Save the upload summary so the attendance page can show what was imported and skipped.
     */
    private static function storeResult($userId, int $imported, int $skipped, array $reasons): void {
        Cache::put(self::resultKey($userId), [
            'imported' => $imported,
            'skipped' => $skipped,
            'reasons' => array_slice($reasons, 0, 20),
            'more' => max(0, count($reasons) - 20),
        ], now()->addHour());
    }

    /**
     * Convert an Excel serial or date string to Y-m-d, or null if unparseable.
     */
    private function toDate($value): ?string {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * Convert an Excel serial, time-only or datetime string to Y-m-d H:i:s, attaching the row's date to time-only values.
     */
    private function toDateTime($value, string $fallbackDate): ?string {
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
