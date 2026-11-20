<?php

namespace App\Services;

use App\Models\DeductionType;
use App\Models\ScheduledDeduction;
use App\Models\ScheduledDeductionEvent;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Everything done to a scheduled deduction (UAT A6): making one, the signed consent form that releases it, pausing, resuming, closing
 * it early and changing the monthly amount. Each change is written to the deduction's history with who did it and why.
 *
 * Who may do what is decided by the permissions on the controller; the rules about what is possible in each status are here.
 */
class ScheduledDeductions
{
    /** The signed consent form is kept privately, like staff documents. */
    public const DISK = 'local';

    /**
     * Make a deduction from what HR typed. It starts "on hold" and becomes active the moment a signed consent form is added (here or later).
     *
     * @param  array{staff_id: int|string, type_id: int|string, description?: ?string, total_amount: float|string, monthly_amount?: float|string|null, months?: int|string|null, end_month?: ?string, start_month: string}  $data
     */
    public function create(array $data, ?UploadedFile $consent, ?int $userId): ScheduledDeduction
    {
        $staff = Staff::find($data['staff_id']);
        if (!$staff) {
            throw ValidationException::withMessages(['staff_id' => ['Choose a staff member.']]);
        }
        $type = DeductionType::active()->find($data['type_id']);
        if (!$type) {
            throw ValidationException::withMessages(['type_id' => ['Choose a type of deduction.']]);
        }
        $plan = DeductionSchedule::build(
            (float) $data['total_amount'],
            self::amount($data['monthly_amount'] ?? null),
            self::monthStart($data['start_month'])->toDateString(),
            self::wholeNumber($data['months'] ?? null),
            $data['end_month'] ?? null,
        );
        if ($plan['problems']) {
            throw ValidationException::withMessages(['schedule' => $plan['problems']]);
        }

        return DB::transaction(function () use ($data, $consent, $userId, $plan, $staff, $type) {
            $deduction = ScheduledDeduction::create([
                'staff_id' => $staff->id,
                'type_id' => $type->id,
                'description' => ($data['description'] ?? null) ?: null,
                'total_amount' => round((float) $data['total_amount'], 2),
                'monthly_amount' => $plan['monthly'],
                'months' => $plan['months'],
                'start_month' => self::monthStart($data['start_month'])->toDateString(),
                'end_month' => $plan['end_month'] . '-01',
                'balance' => round((float) $data['total_amount'], 2),
                'status' => ScheduledDeduction::ON_HOLD,
                'created_by' => $userId,
            ]);
            $this->log($deduction, 'created', null, $userId, 'KES ' . number_format($plan['monthly'], 2) . ' a month for ' . $plan['months'] . ' months, from ' . $plan['rows'][0]['label']);

            if ($consent) {
                $this->addConsent($deduction, $consent, $userId);
            }

            return $deduction->fresh();
        });
    }

    /**
     * Keep the signed consent form (a PDF) with the deduction. The first one releases a deduction that was on hold; a later one replaces the
     * earlier file, which stays on disk and is named in the history.
     */
    public function addConsent(ScheduledDeduction $deduction, UploadedFile $file, ?int $userId): void
    {
        $this->mustBeOpen($deduction);
        $previous = $deduction->consent_path;

        DB::transaction(function () use ($deduction, $file, $userId, $previous) {
            $path = Storage::disk(self::DISK)->putFile('scheduled_deductions/' . $deduction->id, $file);
            $deduction->consent_path = $path;
            $deduction->consent_name = $file->getClientOriginalName();
            $deduction->consent_at = now();
            $deduction->consent_by = $userId;
            if ($deduction->status === ScheduledDeduction::ON_HOLD && (float) $deduction->balance > 0) {
                $deduction->status = ScheduledDeduction::ACTIVE;
            }
            $deduction->save();

            $this->log($deduction, $previous ? 'consent_replaced' : 'consent_added', null, $userId, $file->getClientOriginalName() . ($previous ? ' (earlier file kept: ' . basename($previous) . ')' : ''));
        });
    }

    /** Stop taking it for now. Only an active deduction can be paused; the months it is paused for are simply not taken. */
    public function pause(ScheduledDeduction $deduction, string $reason, ?int $userId): void
    {
        $this->mustBeOpen($deduction);
        if ($deduction->status !== ScheduledDeduction::ACTIVE) {
            throw ValidationException::withMessages(['reason' => ['Only a deduction that is being taken can be paused.']]);
        }
        $reason = $this->requireReason($reason);

        DB::transaction(function () use ($deduction, $reason, $userId) {
            $deduction->status = ScheduledDeduction::PAUSED;
            $deduction->paused_at = now();
            $deduction->pause_reason = $reason;
            $deduction->save();
            $this->log($deduction, 'paused', $reason, $userId);
        });
    }

    /** Start taking a paused deduction again. Without a signed consent form it goes back on hold instead. */
    public function resume(ScheduledDeduction $deduction, ?int $userId): void
    {
        if ($deduction->status !== ScheduledDeduction::PAUSED) {
            throw ValidationException::withMessages(['reason' => ['Only a paused deduction can be resumed.']]);
        }

        DB::transaction(function () use ($deduction, $userId) {
            $deduction->status = $deduction->hasConsent() ? ScheduledDeduction::ACTIVE : ScheduledDeduction::ON_HOLD;
            $deduction->paused_at = null;
            $deduction->pause_reason = null;
            $deduction->save();
            $this->refreshEnd($deduction);
            $this->log($deduction, 'resumed', null, $userId);
        });
    }

    /** Stop it for good before it is paid off. What is still owed stays on the record. */
    public function close(ScheduledDeduction $deduction, string $reason, ?int $userId): void
    {
        $this->mustBeOpen($deduction);
        $reason = $this->requireReason($reason);

        DB::transaction(function () use ($deduction, $reason, $userId) {
            $deduction->status = ScheduledDeduction::CLOSED;
            $deduction->closed_at = now();
            $deduction->closed_by = $userId;
            $deduction->close_reason = $reason;
            $deduction->save();
            $this->log($deduction, 'closed', $reason, $userId, 'KES ' . number_format((float) $deduction->balance, 2) . ' was still owed');
        });
    }

    /** Take a different amount a month from now on. The months left are worked out again from what is still owed. */
    public function changeMonthly(ScheduledDeduction $deduction, float $monthly, string $reason, ?int $userId): void
    {
        $this->mustBeOpen($deduction);
        $reason = $this->requireReason($reason);
        if ($monthly <= 0) {
            throw ValidationException::withMessages(['monthly_amount' => ['The monthly amount must be more than zero.']]);
        }
        $old = (float) $deduction->monthly_amount;

        DB::transaction(function () use ($deduction, $monthly, $reason, $userId, $old) {
            $deduction->monthly_amount = round($monthly, 2);
            $deduction->save();
            $this->refreshEnd($deduction);
            $this->log($deduction, 'instalment_changed', $reason, $userId, 'From KES ' . number_format($old, 2) . ' to KES ' . number_format($monthly, 2));
        });
    }

    /**
     * What is left to take, month by month: the months taken already (from payroll), then the months still to come from what is owed.
     * A month that has gone by without being taken (paused, or not started) is not owed again, so the months to come begin this month
     * or the month after the last one taken, whichever is later.
     *
     * @return array{taken: \Illuminate\Support\Collection, rows: array<int, array>, months_left: int, end_month: ?string, problems: string[]}
     */
    public function schedule(ScheduledDeduction $deduction): array
    {
        $taken = $deduction->repeats()->orderBy('deduction_date')->orderBy('id')->get();
        $balance = (float) $deduction->balance;
        if (!$deduction->isOpen() || $balance <= 0) {
            return ['taken' => $taken, 'rows' => [], 'months_left' => 0, 'end_month' => null, 'problems' => []];
        }

        $next = CarbonImmutable::parse($deduction->start_month)->startOfMonth();
        $thisMonth = CarbonImmutable::now()->startOfMonth();
        if ($next->lessThan($thisMonth)) {
            $next = $thisMonth;
        }
        $last = $taken->last();
        if ($last) {
            $afterLast = CarbonImmutable::parse($last->deduction_date)->startOfMonth()->addMonth();
            if ($afterLast->greaterThan($next)) {
                $next = $afterLast;
            }
        }

        $plan = DeductionSchedule::build($balance, (float) $deduction->monthly_amount, $next->toDateString());

        return ['taken' => $taken, 'rows' => $plan['rows'], 'months_left' => $plan['months'], 'end_month' => $plan['end_month'], 'problems' => $plan['problems']];
    }

    /**
     * What payroll takes from one person for a period: every active deduction whose first month has come, one instalment each, and never
     * more than what is still owed (the last month takes only what is left). Deductions on hold, paused, closed or paid off are not taken.
     *
     * @return array<int, array{id: int, name: string, description: ?string, amount: float, balance_after: float, kind: string}>
     */
    public function dueFor(int $staffId, string $periodEnd): array
    {
        return ScheduledDeduction::with('type')
            ->where('staff_id', $staffId)
            ->where('status', ScheduledDeduction::ACTIVE)
            ->where('balance', '>', 0)
            ->where('monthly_amount', '>', 0)
            ->whereDate('start_month', '<=', $periodEnd)
            ->orderBy('id')
            ->get()
            ->map(function (ScheduledDeduction $deduction) {
                $amount = min((float) $deduction->monthly_amount, (float) $deduction->balance);

                return [
                    'id' => (int) $deduction->id,
                    'name' => $deduction->type?->name ?? 'Scheduled deduction',
                    'description' => $deduction->description,
                    'amount' => round($amount, 2),
                    'balance_after' => round((float) $deduction->balance - $amount, 2),
                    'kind' => $deduction->type?->limitKind() ?? 'other',
                ];
            })
            ->all();
    }

    /**
     * Payroll took an instalment (written when the run is approved): lower what is owed, keep the month in the schedule and, when nothing
     * is left, mark the deduction paid off. Taking the same month twice for one deduction is ignored.
     */
    public function recordTaken(int $deductionId, float $amount, ?int $periodId, string $date): void
    {
        DB::transaction(function () use ($deductionId, $amount, $periodId, $date) {
            $deduction = ScheduledDeduction::lockForUpdate()->find($deductionId);
            if (!$deduction || $amount <= 0) {
                return;
            }
            if ($periodId !== null && $deduction->repeats()->where('period_id', $periodId)->exists()) {
                return;
            }

            $balance = max(0.0, round((float) $deduction->balance - $amount, 2));
            $deduction->repeats()->create(['period_id' => $periodId, 'deduction_date' => $date, 'amount' => round($amount, 2), 'balance' => $balance]);
            $deduction->balance = $balance;
            if ($balance <= 0) {
                if ($deduction->status === ScheduledDeduction::ACTIVE) {
                    $deduction->status = ScheduledDeduction::COMPLETED;
                    $this->log($deduction, 'completed', null, null, 'The last instalment was taken in ' . CarbonImmutable::parse($date)->format('M Y'));
                }
                $deduction->save();
            } else {
                $deduction->save();
                $this->refreshEnd($deduction);
            }
        });
    }

    /** A payroll run was reversed: put the instalment it took back, so the balance and the schedule are as they were. */
    public function reverseTaken(int $deductionId, ?int $periodId): void
    {
        DB::transaction(function () use ($deductionId, $periodId) {
            $deduction = ScheduledDeduction::lockForUpdate()->find($deductionId);
            $repeat = $deduction && $periodId !== null ? $deduction->repeats()->where('period_id', $periodId)->first() : null;
            if (!$repeat) {
                return;
            }

            $amount = (float) $repeat->amount;
            $deduction->balance = round((float) $deduction->balance + $amount, 2);
            if ($deduction->status === ScheduledDeduction::COMPLETED) {
                $deduction->status = ScheduledDeduction::ACTIVE;
            }
            $repeat->delete();
            $deduction->save();
            $this->refreshEnd($deduction);
            $this->log($deduction, 'reversed', null, null, 'KES ' . number_format($amount, 2) . ' put back because the ' . CarbonImmutable::parse($repeat->deduction_date)->format('M Y') . ' payroll was reversed');
        });
    }

    /**
     * A payroll was run over the legal limit on deductions with a written reason: say so on each deduction that was part of it.
     *
     * @param  array{messages: string[], items: array<int, array{id: int}>}  $breach  one person's breach, as PayrollController builds it
     */
    public function recordLimitOverride(array $breach, string $reason, ?int $userId, string $periodLabel): void
    {
        foreach ($breach['items'] as $item) {
            $deduction = ScheduledDeduction::find($item['id']);
            if ($deduction) {
                $this->log($deduction, 'limit_override', $reason, $userId, $periodLabel . ' payroll: ' . implode(' ', $breach['messages']));
            }
        }
    }

    /** Keep the stored last month in step with what is left to take. */
    public function refreshEnd(ScheduledDeduction $deduction): void
    {
        $schedule = $this->schedule($deduction);
        if ($schedule['end_month']) {
            $deduction->end_month = $schedule['end_month'] . '-01';
            $deduction->months = $schedule['taken']->count() + $schedule['months_left'];
            $deduction->save();
        }
    }

    /** A paid-off or closed deduction cannot be changed any more. */
    private function mustBeOpen(ScheduledDeduction $deduction): void
    {
        if (!$deduction->isOpen()) {
            throw ValidationException::withMessages(['reason' => ['This deduction is ' . strtolower($deduction->status_label) . ' and cannot be changed.']]);
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

    public function log(ScheduledDeduction $deduction, string $event, ?string $reason, ?int $userId, ?string $details = null): void
    {
        ScheduledDeductionEvent::create([
            'deduction_id' => $deduction->id,
            'event' => $event,
            'reason' => $reason,
            'details' => $details,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** The first day of the month a date (or Y-m) falls in. */
    public static function monthStart(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse(strlen($value) === 7 ? $value . '-01' : $value)->startOfMonth();
    }

    private static function amount(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private static function wholeNumber(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
