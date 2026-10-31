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
        $requestGroups = $requests->groupBy(fn (LeaveRequest $request) => (int) $request->leave_type_id);
        $types = LeaveType::where(function ($query) use ($requestGroups) {
            $query->where('current_status_id', 5)->orWhereIn('id', $requestGroups->keys());
        })->orderBy('type_name')->get()->keyBy('id');
        foreach ($requestGroups->keys() as $typeId) {
            if ($types->has($typeId)) continue;
            $missing = new LeaveType();
            $missing->setRawAttributes(['id' => $typeId, 'type_name' => $typeId ? "Unconfigured leave category #{$typeId}" : 'Unassigned leave category']);
            $types->put($typeId, $missing);
        }

        foreach ($types as $type) {
            $configured = $type->exists && (int) $type->current_status_id === 5;
            $typeRequests = $requestGroups->get((int) $type->id, collect());
            $figures = $configured ? $this->plans->availableDays($staff, $type, $year)
                : ['entitled' => null, 'balance' => null, 'available' => null, 'plan' => null, 'error' => null,
                    'reserved' => (float) $typeRequests->whereIn('current_status_id', [1, 24])
                        ->filter(fn ($request) => (int) $request->start_date->year === $year)->sum('requested_days')];
            if ($configured && $figures['error']) $figures['reserved'] = $this->plans->reservedDays((int) $staff->id, (int) $type->id, $year);
            $approved = 0.0;
            $approvedComplete = true;
            $pending = 0.0;
            $typeWarnings = [];
            if (!$configured) {
                $reason = $type->exists ? 'This leave category is inactive or not approved.' : 'This leave category is missing from HR settings.';
                $typeWarnings[] = $reason . ' Its recorded requests are shown, but entitlement and balance are unavailable until HR confirms the category. No days have been reassigned to another category.';
            }
            if ($figures['error']) $typeWarnings[] = $figures['error'];

            foreach ($typeRequests as $request) {
                $isApproved = in_array((int) $request->current_status_id, [5, 49], true);
                if ($isApproved) {
                    $days = $storedDays->get($request->id, collect());
                    if ($days->isEmpty()) {
                        $typeWarnings[] = "Request #{$request->id} has no daily records; its recorded total cannot be assigned to individual dates.";
                        if ((int) $request->start_date->year === $year && (int) $request->end_date->year === $year) {
                            $amount = is_numeric($request->used_days) ? (float) $request->used_days : (float) $request->days;
                            $approved += $amount;
                            $details[] = $this->row($request, $type, null, $amount, 'Approved — dates unavailable', null, null);
                        } else {
                            $approvedComplete = false;
                            $details[] = $this->row($request, $type, null, null, 'Approved — year allocation unavailable', null, null);
                        }
                        continue;
                    }
                    foreach ($days as $day) {
                        if (!$day->leave_date || (int) $day->leave_date->year !== $year) continue;
                        if ((int) $day->current_status_id === 5 && $day->getRawOriginal('day_fraction') === null) {
                            $approvedComplete = false;
                            $typeWarnings[] = "Request #{$request->id} has a daily record without a day fraction. Its daily total needs review.";
                        }
                        $charged = (int) $day->current_status_id === 5
                            ? ($day->getRawOriginal('day_fraction') === null ? null : (float) $day->day_fraction) : 0.0;
                        if ($charged !== null) $approved += $charged;
                        $details[] = $this->row($request, $type, $day->leave_date->format('Y-m-d'), $charged,
                            (int) $day->current_status_id === 41 ? 'Refunded / cancelled' : ((int) $day->current_status_id === 5 ? 'Approved' : 'Not charged'),
                            $day->pay_rate, $day->tier_order);
                    }
                } else {
                    if (!$configured) {
                        $sameYear = (int) $request->start_date->year === $year && (int) $request->end_date->year === $year;
                        $date = $request->start_date->isSameDay($request->end_date) ? $request->start_date->format('Y-m-d') : null;
                        $days = $sameYear ? (float) $request->requested_days : null;
                        $status = $sameYear ? 'Pending — recorded request total; category rules unavailable'
                            : 'Pending — spans years; daily/year allocation unavailable';
                        $details[] = $this->row($request, $type, $date, $days, $status, null, null);
                        if (!$sameYear) {
                            $figures['reserved'] = null;
                            $typeWarnings[] = "Request #{$request->id} spans years. Its requested total cannot be allocated to individual years without the missing category rules.";
                        }
                        continue;
                    }
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

            $recordedUsed = $configured ? $this->plans->usedDays((int) $staff->id, (int) $type->id, $year)
                : (float) $typeRequests->whereIn('current_status_id', [5, 49])
                    ->filter(fn ($request) => (int) $request->start_date->year === $year)->sum('days');
            if ($approvedComplete && abs($recordedUsed - $approved) > 0.01) {
                $typeWarnings[] = 'The daily approved total differs from the request totals used by leave applications. HR should reconcile the records.';
            }
            if ($configured && abs($figures['reserved'] - $pending) > 0.01) {
                $typeWarnings[] = 'The pending date preview differs from the reserved request total (for example, a request crosses years or rules have changed).';
            }
            $difference = $approvedComplete && $figures['balance'] !== null && $figures['entitled'] !== null
                ? round($figures['balance'] - ($figures['entitled'] - $approved), 2) : null;
            if ($difference !== null && abs($difference) > 0.01) {
                $typeWarnings[] = 'The recorded balance does not equal entitlement minus the approved breakdown. It may include adjustments or legacy differences; review the plan before relying on it.';
            }
            if ($figures['plan'] && ($figures['plan']->getRawOriginal('balance') === null || $figures['plan']->getRawOriginal('entitled_days') === null)) {
                $typeWarnings[] = 'The annual plan is missing its entitlement or balance; these figures are unavailable.';
                $figures['entitled'] = $figures['balance'] = $figures['available'] = null;
            }
            if ($configured && !$figures['plan'] && !$this->plans->isPlanned($type) && $type->getRawOriginal('entitled_days') === null) {
                $typeWarnings[] = 'This leave category has no configured entitlement; its balance is unavailable.';
                $figures['entitled'] = $figures['balance'] = $figures['available'] = null;
            }
            $typeWarnings = array_values(array_unique($typeWarnings));
            foreach ($typeWarnings as $message) $warnings[] = $this->categoryName($type) . ': ' . $message;
            $categories[] = [
                'id' => $type->id, 'name' => $this->categoryName($type),
                'setup_status' => !$type->exists ? 'missing' : ($configured ? 'active' : 'inactive'),
                'entitled' => $figures['entitled'], 'approved' => $approvedComplete ? round($approved, 2) : null,
                'pending' => $figures['reserved'], 'balance' => $figures['balance'], 'available' => $figures['available'],
                'warnings' => $typeWarnings,
            ];
        }
        usort($details, fn ($a, $b) => [$a['date'] ?? '9999', $a['request_id']] <=> [$b['date'] ?? '9999', $b['request_id']]);
        return ['categories' => $categories, 'days' => $details, 'warnings' => $warnings];
    }

    private function row(LeaveRequest $request, LeaveType $type, ?string $date, ?float $days, string $status, $payRate, $tier): array
    {
        return ['request_id' => $request->id, 'category_id' => $type->id, 'category' => $this->categoryName($type),
            'date' => $date, 'days' => $days === null ? null : round($days, 2), 'status' => $status, 'pay_rate' => $payRate, 'tier' => $tier,
            'request_start' => $request->start_date?->format('Y-m-d'), 'request_end' => $request->end_date?->format('Y-m-d')];
    }

    private function categoryName(LeaveType $type): string
    {
        return $type->type_name . ($type->exists && (int) $type->current_status_id !== 5 ? ' (inactive)' : '');
    }
}
