<?php

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\LeaveType;
use App\Models\Staff;
use Carbon\Carbon;

class LeaveOverviewService
{
    public function __construct(private LeavePlanService $plans, private LeaveCalendarService $calendar) {}

    /** Read-only: balances follow the same source as leave applications; daily discrepancies stay visible. */
    public function forEmployee(Staff $staff, int $year): array
    {
        $categories = [];
        $details = [];
        $warnings = [];
        $requests = LeaveRequest::where('staff_id', $staff->id)
            ->whereDate('start_date', '<=', "{$year}-12-31")
            ->whereDate('end_date', '>=', "{$year}-01-01")
            ->whereIn('current_status_id', [1, 24, 5, 49])->orderBy('start_date')->get();
        $storedDays = LeaveRequestDaysTaken::whereIn('parent_id', $requests->pluck('id'))->orderBy('leave_date')->get()->groupBy('parent_id');

        foreach (LeaveType::where('current_status_id', 5)->orderBy('type_name')->get() as $type) {
            $figures = $this->plans->availableDays($staff, $type, $year);
            if ($figures['error']) $figures['reserved'] = $this->plans->reservedDays((int) $staff->id, (int) $type->id, $year);
            $approved = 0.0;
            $pending = 0.0;
            $typeWarnings = [];
            if ($figures['error']) $typeWarnings[] = $figures['error'];

            foreach ($requests->where('leave_type_id', $type->id) as $request) {
                $isApproved = in_array((int) $request->current_status_id, [5, 49], true);
                if ($isApproved) {
                    $days = $storedDays->get($request->id, collect());
                    if ($days->isEmpty()) {
                        $typeWarnings[] = "Request #{$request->id} has no daily records; its recorded total cannot be assigned to individual dates.";
                        if ((int) $request->start_date->year === $year && (int) $request->end_date->year === $year) {
                            $amount = is_numeric($request->used_days) ? (float) $request->used_days : (float) $request->days;
                            $approved += $amount;
                            $details[] = $this->row($request, $type, null, $amount, 'Approved — dates unavailable', null, null);
                        }
                        continue;
                    }
                    foreach ($days as $day) {
                        if (!$day->leave_date || (int) $day->leave_date->year !== $year) continue;
                        if ((int) $day->current_status_id === 5 && $day->getRawOriginal('day_fraction') === null) {
                            $typeWarnings[] = "Request #{$request->id} has a daily record without a day fraction. Its daily total needs review.";
                        }
                        $charged = (int) $day->current_status_id === 5 ? (float) $day->day_fraction : 0.0;
                        $approved += $charged;
                        $details[] = $this->row($request, $type, $day->leave_date->format('Y-m-d'), $charged,
                            (int) $day->current_status_id === 41 ? 'Refunded / cancelled' : ((int) $day->current_status_id === 5 ? 'Approved' : 'Not charged'),
                            $day->pay_rate, $day->tier_order);
                    }
                } else {
                    // Pending dates are a preview under the current working-day/holiday rules.
                    $start = max($request->start_date->format('Y-m-d'), "{$year}-01-01");
                    $end = min($request->end_date->format('Y-m-d'), "{$year}-12-31");
                    $rules = $this->calendar->rulesFor((int) $type->id);
                    $preview = 0.0;
                    foreach ($this->calendar->countableDates($start, $end, (int) $type->id) as $date) {
                        $fraction = $request->start_date->isSameDay($request->end_date)
                            ? (float) $request->requested_days : $this->calendar->dayWeight(Carbon::parse($date), $rules);
                        $preview += $fraction;
                        $details[] = $this->row($request, $type, $date->format('Y-m-d'), $fraction, 'Pending — preview', null, null);
                    }
                    $pending += $preview;
                }
            }

            $recordedUsed = $this->plans->usedDays((int) $staff->id, (int) $type->id, $year);
            if (abs($recordedUsed - $approved) > 0.01) {
                $typeWarnings[] = 'The daily approved total differs from the request totals used by leave applications. HR should reconcile the records.';
            }
            if (abs($figures['reserved'] - $pending) > 0.01) {
                $typeWarnings[] = 'The pending date preview differs from the reserved request total (for example, a request crosses years or rules have changed).';
            }
            $difference = $figures['balance'] !== null && $figures['entitled'] !== null
                ? round($figures['balance'] - ($figures['entitled'] - $approved), 2) : null;
            if ($difference !== null && abs($difference) > 0.01) {
                $typeWarnings[] = 'The recorded balance does not equal entitlement minus the approved breakdown. It may include adjustments or legacy differences; review the plan before relying on it.';
            }
            if ($figures['plan'] && ($figures['plan']->getRawOriginal('balance') === null || $figures['plan']->getRawOriginal('entitled_days') === null)) {
                $typeWarnings[] = 'The annual plan is missing its entitlement or balance; these figures are unavailable.';
                $figures['entitled'] = $figures['balance'] = $figures['available'] = null;
            }
            if (!$figures['plan'] && !$this->plans->isPlanned($type) && $type->getRawOriginal('entitled_days') === null) {
                $typeWarnings[] = 'This leave category has no configured entitlement; its balance is unavailable.';
                $figures['entitled'] = $figures['balance'] = $figures['available'] = null;
            }
            $typeWarnings = array_values(array_unique($typeWarnings));
            foreach ($typeWarnings as $message) $warnings[] = $type->type_name . ': ' . $message;
            $categories[] = [
                'id' => $type->id, 'name' => $type->type_name,
                'entitled' => $figures['entitled'], 'approved' => round($approved, 2),
                'pending' => $figures['reserved'], 'balance' => $figures['balance'], 'available' => $figures['available'],
                'warnings' => $typeWarnings,
            ];
        }
        usort($details, fn ($a, $b) => [$a['date'] ?? '9999', $a['request_id']] <=> [$b['date'] ?? '9999', $b['request_id']]);
        return ['categories' => $categories, 'days' => $details, 'warnings' => $warnings];
    }

    private function row(LeaveRequest $request, LeaveType $type, ?string $date, float $days, string $status, $payRate, $tier): array
    {
        return ['request_id' => $request->id, 'category_id' => $type->id, 'category' => $type->type_name,
            'date' => $date, 'days' => round($days, 2), 'status' => $status, 'pay_rate' => $payRate, 'tier' => $tier];
    }
}
