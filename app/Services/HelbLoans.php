<?php

namespace App\Services;

use App\Models\HelbDeduction;
use App\Models\HelbDeductionEvent;
use App\Models\HelbDeductionRepeat;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * What is done to a HELB loan (UAT A6). HELB is deducted by law, so there is no consent form; what is kept is why: every pause, change and
 * closing has a written reason and is recorded with who did it, and a loan is closed with the clearance HELB sends when it is paid in full.
 *
 * Who may do what is decided by the permissions on the controller; the rules about what is possible in each state are here.
 */
class HelbLoans
{
    /** HELB's clearance is kept privately, like staff documents. */
    public const DISK = 'local';

    /** The certificate HR asks for when a staff member is added; the profile and the HELB screen read it the same way. */
    public const CERTIFICATE = 'HELB Compliance Certificate';

    public function log(HelbDeduction $loan, string $event, ?string $reason, ?int $userId, ?string $details = null): void
    {
        HelbDeductionEvent::create([
            'deduction_id' => $loan->id,
            'event' => $event,
            'reason' => $reason,
            'details' => $details,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Stop taking it for now (for example during unpaid leave). The months paused are not made up: the loan simply finishes later. */
    public function pause(HelbDeduction $loan, string $reason, ?int $userId): void
    {
        $this->mustBeOpen($loan);
        if ($loan->suspended) {
            throw ValidationException::withMessages(['reason' => ['This loan is already paused.']]);
        }
        $reason = $this->requireReason($reason);

        DB::transaction(function () use ($loan, $reason, $userId) {
            $loan->suspended = 1;
            $loan->suspended_reason = $reason;
            $loan->suspended_at = now();
            $loan->save();
            $this->log($loan, 'paused', $reason, $userId);
        });
    }

    public function resume(HelbDeduction $loan, ?int $userId): void
    {
        if ($loan->cleared || !$loan->suspended) {
            throw ValidationException::withMessages(['reason' => ['Only a paused loan can be resumed.']]);
        }

        DB::transaction(function () use ($loan, $userId) {
            $loan->suspended = 0;
            $loan->suspended_reason = null;
            $loan->suspended_at = null;
            $loan->save();
            $this->log($loan, 'resumed', null, $userId);
        });
    }

    /**
     * Stop the deduction because HELB has sent its clearance (the loan is paid in full). The clearance PDF is kept. What the system
     * still showed as owed stays on the record, so a difference with HELB's figures can be seen.
     */
    public function clear(HelbDeduction $loan, UploadedFile $clearance, ?string $note, ?int $userId): void
    {
        $this->mustBeOpen($loan);
        $note = trim((string) $note);

        DB::transaction(function () use ($loan, $clearance, $note, $userId) {
            $path = Storage::disk(self::DISK)->putFile('helb_clearances/' . $loan->id, $clearance);
            $owed = (float) $loan->loan_balance;
            $loan->cleared = 1;
            $loan->suspended = 0;
            $loan->suspended_reason = null;
            $loan->suspended_at = null;
            $loan->cleared_at = now();
            $loan->cleared_by = $userId;
            $loan->cleared_reason = $note !== '' ? $note : null;
            $loan->clearance_path = $path;
            $loan->clearance_name = $clearance->getClientOriginalName();
            $loan->save();
            $this->log($loan, 'cleared', $note !== '' ? $note : null, $userId, $clearance->getClientOriginalName() . ($owed > 0 ? '. KES ' . number_format($owed, 2) . ' was still shown as owed' : ''));
        });
    }

    /** A loan closed by mistake (or one HELB says is not yet paid) is opened again. Needs a reason. */
    public function reopen(HelbDeduction $loan, string $reason, ?int $userId): void
    {
        if (!$loan->cleared) {
            throw ValidationException::withMessages(['reason' => ['Only a closed loan can be reopened.']]);
        }
        $reason = $this->requireReason($reason);

        DB::transaction(function () use ($loan, $reason, $userId) {
            $loan->cleared = 0;
            $loan->cleared_at = null;
            $loan->cleared_by = null;
            $loan->cleared_reason = null;
            $loan->save();
            $this->log($loan, 'reopened', $reason, $userId, $loan->clearance_name ? 'The clearance ' . $loan->clearance_name . ' is kept on file' : null);
        });
    }

    /**
     * Change what is owed, the monthly amount (HELB changes the rate it gives the employer) or the start date. A reason is needed, and
     * what it was before and after is written to the history. Nothing is written when nothing changed.
     *
     * @return bool whether anything was changed
     */
    public function changeTerms(HelbDeduction $loan, float $balance, float $monthly, string $startDate, ?string $reason, ?int $userId): bool
    {
        $oldStart = $loan->start_deduction_date ? CarbonImmutable::parse($loan->start_deduction_date)->toDateString() : null;
        $newStart = CarbonImmutable::parse($startDate)->toDateString();
        $changes = [];
        if (round((float) $loan->loan_balance, 2) !== round($balance, 2)) {
            $changes[] = 'Balance KES ' . number_format((float) $loan->loan_balance, 2) . ' to KES ' . number_format($balance, 2);
        }
        if (round((float) $loan->monthly_deduction, 2) !== round($monthly, 2)) {
            $changes[] = 'Monthly amount KES ' . number_format((float) $loan->monthly_deduction, 2) . ' to KES ' . number_format($monthly, 2);
        }
        if ($oldStart !== $newStart) {
            $changes[] = 'Start date ' . ($oldStart ? CarbonImmutable::parse($oldStart)->format('d M Y') : 'none') . ' to ' . CarbonImmutable::parse($newStart)->format('d M Y');
        }
        if (!$changes) {
            return false;
        }
        if ($loan->cleared) {
            throw ValidationException::withMessages(['reason' => ['A closed loan cannot be changed. Reopen it first.']]);
        }
        $reason = $this->requireReason((string) $reason);

        DB::transaction(function () use ($loan, $balance, $monthly, $newStart, $reason, $userId, $changes) {
            $loan->loan_balance = round($balance, 2);
            $loan->monthly_deduction = round($monthly, 2);
            $loan->start_deduction_date = $newStart;
            $loan->save();
            $this->log($loan, 'changed', $reason, $userId, implode('. ', $changes));
        });

        return true;
    }

    /**
     * The months still to come: from what is owed and the monthly amount, beginning this month (or the month after the last one taken,
     * or the start month, whichever is latest). A month that has gone by without being taken is not made up.
     *
     * @return array{rows: array<int, array>, months_left: int, end_month: ?string, problems: string[]}
     */
    public function schedule(HelbDeduction $loan): array
    {
        return $this->scheduleFrom($loan, HelbDeductionRepeat::where('parent_id', $loan->id)->max('deduction_date'));
    }

    /** schedule(), when the date of the last instalment taken is already known (the list reads it for every loan in one go). */
    public function scheduleFrom(HelbDeduction $loan, $lastTaken): array
    {
        $empty = ['rows' => [], 'months_left' => 0, 'end_month' => null, 'problems' => []];
        $balance = (float) $loan->loan_balance;
        $monthly = (float) $loan->monthly_deduction;
        if ($loan->cleared || $balance <= 0 || $monthly <= 0) {
            return $empty;
        }

        $next = CarbonImmutable::now()->startOfMonth();
        if ($loan->start_deduction_date) {
            $start = CarbonImmutable::parse($loan->start_deduction_date)->startOfMonth();
            if ($start->greaterThan($next)) {
                $next = $start;
            }
        }
        if ($lastTaken) {
            $afterLast = CarbonImmutable::parse($lastTaken)->startOfMonth()->addMonth();
            if ($afterLast->greaterThan($next)) {
                $next = $afterLast;
            }
        }

        $plan = DeductionSchedule::build($balance, $monthly, $next->toDateString());

        return ['rows' => $plan['rows'], 'months_left' => $plan['months'], 'end_month' => $plan['end_month'], 'problems' => $plan['problems']];
    }

    /**
     * What onboarding recorded about a staff member and HELB, shown when a loan is being added: whether they said they are a HELB loan
     * beneficiary, and whether the compliance certificate is on file (or was waived by the HR Manager, with a reason). Nothing here stops
     * a loan: it only lets HR see whether the profile agrees.
     *
     * @return array{beneficiary: ?bool, certificate: string, exception_reason: ?string, running_loan: bool}|null
     */
    public function staffStatus(int $staffId): ?array
    {
        $staff = Staff::with('employeeDocuments.documentType')->find($staffId);
        if (!$staff) {
            return null;
        }
        $hasCertificate = $staff->employeeDocuments->contains(fn ($document) => optional($document->documentType)->name === self::CERTIFICATE);

        return [
            'beneficiary' => $staff->is_helb_beneficiary,
            'certificate' => $hasCertificate ? 'on_file' : ($staff->helb_certificate_exception_at ? 'exception' : 'missing'),
            'exception_reason' => $staff->helb_certificate_exception_at ? $staff->helb_certificate_exception_reason : null,
            'running_loan' => HelbDeduction::where('staff_id', $staff->id)->where('cleared', 0)->exists(),
        ];
    }

    private function mustBeOpen(HelbDeduction $loan): void
    {
        if ($loan->cleared) {
            throw ValidationException::withMessages(['reason' => ['This loan is closed and cannot be changed. Reopen it first.']]);
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => ['Write the reason (at least 5 characters).']]);
        }

        return $reason;
    }
}
