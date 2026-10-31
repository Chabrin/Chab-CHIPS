<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Department;
use App\Models\WorkDay;
use Carbon\Carbon;
use App\Models\PublicHoliday;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;
use App\Models\ConfirmationLog;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\LeaveCalendarPlan;
use App\Models\LeaveCalendarSlot;
use App\Services\ErrorDispatcher;
use App\Services\Leave\LeaveApprovalRouter;
use App\Services\Leave\LeaveApprovalService;
use App\Services\Leave\LeaveCalendarService;
use App\Services\Leave\LeavePlanService;
use App\Services\Leave\LeaveTierService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Barryvdh\DomPDF\Facade\Pdf;


class LeaveManagementController extends Controller
{
    public function __construct(private LeaveCalendarService $calendar, private LeaveApprovalService $approvals, private LeaveTierService $tiers, private LeavePlanService $plans, private LeaveApprovalRouter $router) {
    }

    public function leaveCalendarPlan()
    {
        return view('leave_management.leave_calendar_plan');
    }

    /**
     * AJAX endpoint to calculate working days for a proposed date block.
     * Excludes weekends and public holidays using LeaveCalendarService.
     */
    public function calculateSlotDays(Request $request)
    {
        $validated = $request->validate([
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'leave_type_id' => 'required|integer',
        ]);

        $days = $this->calendar->countDeductibleDays(
            $validated['start_date'],
            $validated['end_date'],
            (int) $validated['leave_type_id']
        );

        return response()->json([
            'success' => true,
            'days'    => $days,
        ]);
    }

    /**
     * Fetch existing scheduled slots for a given plan.
     * Accessible by the employee who owns the plan or HR/Admins.
     */

    public function getPlanSlots($planId)
    {
        try {
            $plan = LeaveCalendarPlan::with(['slots', 'leaveType', 'employee'])->findOrFail($planId);

            if (!$this->canManagePlan($plan)) {
                return response()->json(['success' => false, 'error' => 'Unauthorized access.'], 403);
            }

            $planYear = (int) \Carbon\Carbon::parse($plan->start_date)->year;

            // Every public holiday day in the plan's year (multi-day and recurring holidays included)
            $holidayDates = [];
            for ($day = \Carbon\Carbon::create($planYear, 1, 1); $day->year === $planYear; $day->addDay()) {
                if ($this->calendar->holidayOn($day->copy())) {
                    $holidayDates[] = $day->format('Y-m-d');
                }
            }

            $slots = $plan->slots->map(function ($slot) use ($plan) {
                $start = \Carbon\Carbon::parse($slot->start_date)->startOfDay();
                $end   = \Carbon\Carbon::parse($slot->end_date)->startOfDay();

                // Counted with the same rules saveAnnualSchedule uses (recurring holidays, working-day overrides, Saturday half day).
                // This is a read: the stored days_count is only changed when the schedule is saved.
                $calculatedDays = $this->calendar->countDeductibleDays($start->format('Y-m-d'), $end->format('Y-m-d'), (int) $plan->leave_type_id);

                return [
                    'id'         => $slot->id,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date'   => $end->format('Y-m-d'),
                    'days_count' => $calculatedDays,
                    'notes'      => $slot->notes,
                    'status'     => $slot->current_status_id,
                ];
            });

            return response()->json([
                'success'         => true,
                'plan_id'         => $plan->id,
                'employee_name'   => $plan->employee->full_names ?? $plan->employee->name ?? 'Staff',
                'entitled_days'   => $plan->entitled_days,
                'leave_type_id'   => $plan->leave_type_id,
                'public_holidays' => $holidayDates,
                'slots'           => $slots,
            ]);
        } catch (\Throwable $e) {
            Log::error('getPlanSlots error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load leave slots'], 500);
        }
    }

    /**
     * Save the employee's scheduled leave blocks for the year.
     * Enforces the dynamic quota rule (21 days full year, or prorated for mid-year hires).
     */
    public function saveAnnualSchedule(Request $request)
    {
        $validated = $request->validate([
            'plan_id'              => 'required|integer|exists:sys_hr_leave_calendar,id',
            'slots'                => 'required|array|min:1',
            'slots.*.start_date'   => 'required|date',
            'slots.*.end_date'     => 'required|date|after_or_equal:slots.*.start_date',
            'slots.*.notes'        => 'nullable|string|max:255',
        ]);

        $plan = LeaveCalendarPlan::with('leaveType')->findOrFail($validated['plan_id']);

        if (!$this->canManagePlan($plan)) {
            return response()->json(['success' => false, 'error' => 'You can only schedule your own annual leave.'], 403);
        }

        if ($plan->slots()->exists()) {
            return response()->json(['success' => false, 'error' => 'This annual leave schedule has already been submitted and cannot be rescheduled.'], 422);
        }

        $totalDays = 0;
        $slotsToCreate = [];
        $ranges = [];

        foreach ($validated['slots'] as $slot) {
            $slotStart = Carbon::parse($slot['start_date'])->toDateString();
            $slotEnd = Carbon::parse($slot['end_date'])->toDateString();
            foreach ($ranges as [$existingStart, $existingEnd]) {
                if ($slotStart <= $existingEnd && $slotEnd >= $existingStart) {
                    return response()->json(['success' => false, 'error' => 'Scheduled leave blocks cannot use the same date. Adjust the overlapping blocks.'], 422);
                }
            }
            $ranges[] = [$slotStart, $slotEnd];

            if ($this->overlapsActiveLeave((int) $plan->employee_id, $slotStart . ' 00:00:00', $slotEnd . ' 23:59:59')
                || $this->overlapsScheduledAnnualLeave((int) $plan->employee_id, $slotStart, $slotEnd, null)) {
                return response()->json(['success' => false, 'error' => 'These dates overlap an existing leave application or scheduled leave block. Choose different dates.'], 422);
            }

            $deductibleDays = $this->calendar->countDeductibleDays(
                $slot['start_date'],
                $slot['end_date'],
                $plan->leave_type_id
            );

            if ($deductibleDays <= 0) {
                return response()->json([
                    'success' => false,
                    'error'   => "The slot from {$slot['start_date']} to {$slot['end_date']} contains 0 working days (falls entirely on weekends/holidays).",
                ], 422);
            }

            $totalDays += $deductibleDays;
            $slotsToCreate[] = [
                'start_date'        => $slot['start_date'],
                'end_date'          => $slot['end_date'],
                'days_count'        => $deductibleDays,
                'notes'             => $slot['notes'] ?? null,
                'current_status_id' => 1, // Pending
            ];
        }

        // Validate allocation matches the staff member's entitled days (pro-rata support). Saturdays count half,
        // so schedules are made in half days and a fractional entitlement (5.25) is scheduled down to the half day (5).
        // $requiredDays = floor((float) $plan->entitled_days , 2);
        // $requiredDays = (float) $plan->entitled_days;
        // Nearest lower half-day
$requiredDays = floor((float) $plan->entitled_days * 2) / 2;
        if (abs($totalDays - $requiredDays) > 0.001) {
            return response()->json([
                'success' => false,
                'error'   => "Total scheduled days must equal exactly {$requiredDays} working days. Your current selection totals {$totalDays} days.",
            ], 422);
        }

        $saved = DB::transaction(function () use ($plan, $slotsToCreate) {
            $lockedPlan = LeaveCalendarPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($lockedPlan->slots()->exists()) {
                return false;
            }
            foreach ($slotsToCreate as $slotData) {
                $lockedPlan->slots()->create($slotData);
            }
            return true;
        });

        if (!$saved) {
            return response()->json(['success' => false, 'error' => 'This annual leave schedule has already been submitted and cannot be rescheduled.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Annual leave schedule saved successfully and submitted for approval.',
        ]);
    }

    public function leaveCalendarPlanList()
    {
        try {
            $currentYear = date('Y');
            
            $plans = LeaveCalendarPlan::with(['createdBy', 'employee', 'leaveType'])
                ->withCount('slots')
                ->whereYear('start_date', $currentYear)
                ->orderBy('id', 'desc')
                ->get()
                ->filter(fn($plan) => $this->calendar->isAnnualLeaveType($plan->leaveType?->type_name));
            
            $user = Auth::user();
            $canUpdate = $user ? $user->isAbleTo('update_leave_calendar_plans') : true;
            $canDelete = $user ? $user->isAbleTo('delete_leave_calendar_plans') : true;

            $groupedByEmployee = $plans->groupBy('employee_id')->map(function ($employeePlans, $employeeId) use ($canUpdate, $canDelete) {
                $firstPlan = $employeePlans->first();
                
                $leaveTypes = $employeePlans->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'leave_type_id' => $plan->leave_type_id,
                        'leave_type' => $plan->leaveType ? [
                            'id' => $plan->leaveType->id,
                            'type_name' => $plan->leaveType->type_name,
                        ] : null,
                        'start_date' => $plan->start_date ? \Carbon\Carbon::parse($plan->start_date)->format('Y-m-d') : null,
                        'end_date' => $plan->end_date ? \Carbon\Carbon::parse($plan->end_date)->format('Y-m-d') : null,
                        'entitled_days' => round((float) ($plan->entitled_days ?? 0), 2),
                        'balance' => round((float) ($plan->balance ?? 0), 2),
                        'current_status_id' => $plan->current_status_id,
                        'has_schedule' => (int) $plan->slots_count > 0,
                    ];
                })->values();
                
                $empName = '-';
                if ($firstPlan && $firstPlan->employee) {
                    $empName = $firstPlan->employee->full_names 
                        ?? $firstPlan->employee->name 
                        ?? ($firstPlan->employee->first_name . ' ' . $firstPlan->employee->last_name);
                }
                
                return [
                    'employee_id' => $employeeId,
                    'employee' => [
                        'id' => $employeeId,
                        'name' => $empName,
                    ],
                    'leave_types' => $leaveTypes,
                    'total_leave_types' => $leaveTypes->count(),
                    'date_time' => $firstPlan->date_time ?? null,
                    'created_by' => $firstPlan->createdBy ? [
                        'id' => $firstPlan->createdBy->id,
                        'name' => $firstPlan->createdBy->name ?? 'System',
                    ] : null,
                    'can_update' => $canUpdate,
                    'can_delete' => $canDelete,
                ];
            })->values();
        
            return response()->json(['leave_calendar_plans' => $groupedByEmployee]);
        } catch (\Throwable $e) {
            Log::error('leaveCalendarPlanList error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'leave_calendar_plans' => [],
                'error' => 'Failed to load calendar plans: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all public holidays for a given year to display in the initialization modal.
     */
    /**
     * Fetch all public holidays for a given year to display in the initialization modal.
     */
    public function getYearHolidays(Request $request)
    {
        try {
            $year = (int) ($request->get('year') ?? date('Y'));

            $holidays = PublicHoliday::whereYear('start_date', $year)
                ->orderBy('start_date', 'asc')
                ->get()
                ->map(function ($h) {
                    return [
                        'id'           => $h->id,
                        'holiday_name' => $h->title ?? 'Holiday',
                        'holiday_date' => $h->start_date ? \Carbon\Carbon::parse($h->start_date)->format('Y-m-d') : null,
                    ];
                });

            return response()->json([
                'success'  => true,
                'year'     => $year,
                'holidays' => $holidays,
            ]);
        } catch (\Throwable $e) {
            Log::error('getYearHolidays error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Initialize the year's annual leave plans: sync the public holidays entered in the modal, then queue plan
     * generation for all active staff (annual leave only, pro-rated for mid-year hires).
     */
    public function generateAnnualPlans(Request $request) {
        try {
            $validated = $request->validate([
                'year' => 'required|integer|min:2020|max:2100',
                'override' => 'boolean',
                'leave_type_ids' => 'nullable|array',
                'leave_type_ids.*' => 'integer',
                'holidays' => 'nullable|array',
            ]);

            $year = (int) $validated['year'];
            $override = (bool) ($validated['override'] ?? false);
            $leaveTypeIds = $validated['leave_type_ids'] ?? null;

            $activeStaffCount = Staff::where('current_status_id', 1)->count();
            if ($activeStaffCount === 0) {
                return response()->json(['success' => false, 'error' => 'No active staff found'], 400);
            }

            $annualTypeIds = LeaveType::all()
                ->filter(fn($type) => $this->calendar->isAnnualLeaveType($type->type_name))
                ->pluck('id');
            if ($leaveTypeIds) {
                $annualTypeIds = $annualTypeIds->intersect($leaveTypeIds);
            }
            $leaveTypesCount = $annualTypeIds->count();
            if ($leaveTypesCount === 0) {
                return response()->json(['success' => false, 'error' => 'Annual leave type not configured in HR settings.'], 400);
            }

            // Without 'override' this only creates the plans that are missing; existing plans are left exactly as they are
            $existingPlansCount = LeaveCalendarPlan::whereYear('start_date', $year)->whereIn('leave_type_id', $annualTypeIds)->count();

            $this->syncPublicHolidays($validated['holidays'] ?? [], $year);

            \App\Jobs\GenerateAnnualLeaveCalendarPlans::dispatch($year, $leaveTypeIds, $override, Auth::id());

            // Staff with no hire date can't be pro-rated, so the generator gives them the full year
            $noHireDate = Staff::where('current_status_id', 1)->get()
                ->filter(fn ($staff) => !$this->calendar->parseHireDate($staff->getRawOriginal('hire_date')))
                ->count();

            $message = "Annual leave plan generation for {$year} started in the background for {$activeStaffCount} staff member(s).";
            if ($existingPlansCount > 0 && !$override) {
                $message .= " {$existingPlansCount} existing plan(s) are left untouched; only missing plans are created.";
            }
            if ($noHireDate > 0) {
                $message .= " {$noHireDate} staff member(s) have no hire date and get the full year's days.";
            }

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('generateAnnualPlans error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['success' => false, 'error' => 'Failed to start annual plan generation'], 500);
        }
    }

  /**
 * Create or refresh the public holidays entered when initializing a year,
 * removing any holidays for that year that were omitted.
 */
private function syncPublicHolidays(array $holidays, int $year): void {
    $syncedDates = [];

    foreach ($holidays as $h) {
        if (empty($h['name']) || empty($h['date'])) {
            continue;
        }

        $date = trim($h['date']);
        if (str_contains($date, '/')) {
            try {
                $date = \Carbon\Carbon::createFromFormat('d/m/Y', explode(' ', $date)[0])->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }

        $startDateTime = $date . ' 00:00:00';
        $syncedDates[] = $startDateTime;

        PublicHoliday::updateOrCreate(
            ['start_date' => $startDateTime],
            [
                'title'             => $h['name'],
                'end_date'          => $date . ' 23:59:59',
                'current_status_id' => 5,
                'date_time'         => now(),
            ]
        );
    }

    // An empty list (e.g. the holiday load failed) must not wipe the year: only prune when holidays were submitted
    if (empty($syncedDates)) {
        return;
    }

    // Prune removed holidays for the given year, one by one so each deletion is audited
    PublicHoliday::whereYear('start_date', $year)
        ->whereNotIn('start_date', $syncedDates)
        ->get()
        ->each->delete();
}

    /**
     * Whether the logged-in user may view or change a leave calendar plan: its owner, or HR with plan permission.
     */
    private function canManagePlan(LeaveCalendarPlan $plan): bool {
        $user = Auth::user();

        return $user->isAbleTo('update_leave_calendar_plans') || $user->linkedStaff()?->id === (int) $plan->employee_id;
    }

    /**
     * Preview how a leave application's days fall across a leave type's pay tiers (e.g. sick leave full/half/no pay).
     */
    public function evaluateApplication(Request $request) {
        $validated = $request->validate([
            'staff_id' => 'required|integer',
            'leave_type_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'requested_days' => 'nullable|numeric|min:0.5|max:366',
        ]);

        $user = Auth::user();
        if (!$user->isAbleTo('read_leave_applications') && $user->linkedStaff()?->id !== (int) $validated['staff_id']) {
            return response()->json(['success' => false, 'error' => 'Unauthorized access.'], 403);
        }

        $typeId = (int) $validated['leave_type_id'];
        $endDate = $validated['end_date'] ?? null;

        // Work out the real dates so a Saturday inside the range counts as half a day
        if (!empty($validated['requested_days'])) {
            $endDate = $this->calendar->calculateEndDate($validated['start_date'], (float) $validated['requested_days'], $typeId);
        }

        $weights = $endDate ? $this->calendar->dayWeightsBetween($validated['start_date'], $endDate, $typeId) : [1.0];
        $breakdown = $this->tiers->breakdown((int) $validated['staff_id'], $typeId, \Carbon\Carbon::parse($validated['start_date'])->year, $weights);

        return response()->json([
            'success' => true,
            'sick_breakdown' => $breakdown,
            'end_date' => $endDate,
            'charged_days' => array_sum($weights),
        ]);
    }

    /**
     * Whether the logged-in user may file, edit, read or delete leave for this staff member: their own, or
     * anyone's when they hold the given permission (HR acting on someone's behalf).
     *
     * @param int $staffId
     * @param string $permission
     * @return bool
     */
    private function canManageLeaveOf(int $staffId, string $permission): bool {
        $user = Auth::user();

        return $user->isAbleTo($permission) || $user->linkedStaff()?->id === $staffId;
    }

    /**
     * Work out what a request is measured against and refuse it when it can't be covered. Annual leave needs the
     * year's plan, which is created now if this is the first time (an existing plan is always used as it is);
     * every leave type without pay tiers (sick leave has them) must fit in the days still available. People who
     * may file leave for others (HR) can go over the balance and get a warning instead.
     *
     * @param LeaveRequest $requestModel
     * @param Staff $staff
     * @param LeaveType|null $leaveType
     * @param int $year
     * @return array{error: \Illuminate\Http\JsonResponse|null, warning: string|null}
     */
    private function applyBalance(LeaveRequest $requestModel, Staff $staff, ?LeaveType $leaveType, int $year): array {
        $refuse = fn (string $message) => ['error' => response()->json(['success' => false, 'error' => $message], 422), 'warning' => null];

        if (!$leaveType) {
            return $refuse('Leave type not found.');
        }

        if ($this->plans->isPlanned($leaveType)) {
            $ensured = $this->plans->ensurePlan($staff, $leaveType, $year, Auth::id());
            if ($ensured['error']) {
                return $refuse($ensured['error']);
            }
        }

        $figures = $this->plans->availableDays($staff, $leaveType, $year, $requestModel->id);
        if ($figures['error']) {
            return $refuse($figures['error']);
        }

        $requested = (float) $requestModel->requested_days;
        $warning = null;

        if (!$this->tiers->hasTiers((int) $leaveType->id) && $requested > $figures['available'] + 0.001) {
            $message = 'You asked for ' . rtrim(rtrim(number_format($requested, 2), '0'), '.') . ' day(s) but only '
                . rtrim(rtrim(number_format($figures['available'], 2), '0'), '.') . ' are available.';

            if (!Auth::user()->isAbleTo('create_leave_applications')) {
                return $refuse($message);
            }

            $warning = $message;
        }

        $requestModel->entitled_days = $figures['entitled'];
        $requestModel->days_balance = $figures['balance'] - $requested;

        return ['error' => null, 'warning' => $warning];
    }

    /**
     * Whether the staff member already has a pending, partially approved or approved leave request that overlaps
     * the given period (compared down to the time, so two partial-day requests on one day can still sit side by side).
     *
     * @param int $staffId
     * @param mixed $start
     * @param mixed $end
     * @param int|null $ignoreId a request being edited, which doesn't count against itself
     * @return bool
     */
    private function overlapsActiveLeave(int $staffId, $start, $end, ?int $ignoreId = null): bool {
        return LeaveRequest::where('staff_id', $staffId)
            ->whereIn('current_status_id', [1, 5, 24])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('start_date', '<', \Carbon\Carbon::parse($end))
            ->where('end_date', '>', \Carbon\Carbon::parse($start))
            ->exists();
    }

    /** A scheduled annual block reserves those calendar dates for this employee across leave types. */
    private function overlapsScheduledAnnualLeave(int $staffId, $start, $end, ?LeaveType $leaveType, ?float $requestedDays = null): bool {
        if (!Schema::hasTable('sys_hr_leave_calendar_slots')) {
            return false;
        }

        $startDate = \Carbon\Carbon::parse($start)->toDateString();
        $endDate = \Carbon\Carbon::parse($end)->toDateString();

        $annualPlanIds = LeaveCalendarPlan::with('leaveType')
            ->where('employee_id', $staffId)
            ->get()
            ->filter(fn ($plan) => $this->calendar->isAnnualLeaveType($plan->leaveType?->type_name))
            ->pluck('id');

        if ($annualPlanIds->isEmpty()) {
            return false;
        }

        $reserved = LeaveCalendarSlot::query()
            ->whereIn('leave_calendar_plan_id', $annualPlanIds)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->get(['start_date', 'end_date', 'days_count']);

        if ($reserved->isEmpty()) {
            return false;
        }

        // Annual applications use the scheduled block picker. Accept only the chosen block's start,
        // and reject any annual request that spills beyond its scheduled dates.
        if ($leaveType && $this->calendar->isAnnualLeaveType($leaveType->type_name)) {
            foreach ($reserved as $slot) {
                if ($startDate === \Carbon\Carbon::parse($slot->start_date)->toDateString()
                    && $endDate <= \Carbon\Carbon::parse($slot->end_date)->toDateString()
                    && ($requestedDays === null || abs((float) $slot->days_count - $requestedDays) < 0.001)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Days a leave request really charges between its start and end dates (a Saturday is half a day).
     * Falls back to the requested days when the dates charge nothing.
     */
    private function chargedDays(string $startDate, string $endDate, float $requestedDays, int $leaveTypeId): float {
        $charged = $this->calendar->countDeductibleDays($startDate, $endDate, $leaveTypeId);

        return $charged > 0 ? $charged : $requestedDays;
    }

    private function calculateEndDate($start_date, $requested_days, $leave_type_id, $available_days)
    {
        return [
            'balance'  => $available_days - $requested_days,
            'end_date' => $this->calendar->calculateEndDate($start_date, $requested_days, (int) $leave_type_id),
        ];
    }

    private function getLeaveUsedDays($year, $leave_type_id, $employee_id)
    {
        return LeaveRequest::whereYear('start_date', $year)
            ->where('leave_type_id', $leave_type_id)
            ->where('staff_id', $employee_id)
            ->whereIn('current_status_id', [5, 49])
            ->sum('days');
    }

    public function leaveCalendarPlanStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id'   => 'required|integer',
                'leave_type_id' => 'required|integer',
                'start_date'    => 'required|date',
                'entitled_days' => 'required|numeric|min:0',
            ]);

            $result = $this->calculateEndDate($validated['start_date'], $validated['entitled_days'], $validated['leave_type_id'], $validated['entitled_days']);
            $end_date = $result['end_date'];

            $used_days = $this->getLeaveUsedDays(date('Y', strtotime($validated['start_date'])), $validated['leave_type_id'], $validated['employee_id']);
            $balance = $validated['entitled_days'] - $used_days;

            $exists = LeaveCalendarPlan::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('start_date', $validated['start_date'])
                ->exists();
            
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'Leave calendar plan already exists for this employee, leave type and start date'], 409);
            }
    
            $plan = new LeaveCalendarPlan();
            $plan->employee_id       = $validated['employee_id'];
            $plan->leave_type_id     = $validated['leave_type_id'];
            $plan->start_date        = $validated['start_date'];
            $plan->end_date          = $end_date;
            $plan->entitled_days     = $validated['entitled_days'];
            $plan->balance           = $balance;
            $plan->current_status_id = 5; // HR sets plans up directly, as the year generator does; there is no approval screen for them
            $plan->created_by        = Auth::id();
            $plan->date_time         = now();
            $plan->save();
    
            return response()->json(['success' => true, 'leave_calendar_plan' => $plan]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to create leave calendar plan'], 500);
        }
    }
    
    public function pendingApprovalsView()
    {
        return view('leave_management.pending_approvals');
    }

    public function pendingApprovalsList()
    {
        $user = Auth::user();
        
        $isDirector = $user->hasRole(['directors', 'superadministrator']);
        $isHR = $user->hasRole(['hr_manager']);
        
        $staff = $user->linkedStaff();
        $departmentId = $staff?->department_id;

        $query = LeaveRequest::with(['employee.department', 'leaveType', 'position'])
            ->whereIn('current_status_id', [1, 24]);

        // If not Director or HR, scope by the manager's department or linked permissions
        if (!$isDirector && !$isHR) {
            if ($departmentId) {
                $query->whereHas('employee', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            } else {
                return response()->json(['leave_applications' => []]);
            }
        }

        $applications = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'leave_applications' => $applications->map(function ($app) use ($isDirector, $isHR) {
                return [
                    'id' => $app->id,
                    'encrypted_leave_application_id' => \Illuminate\Support\Facades\Crypt::encrypt($app->id),
                    'employee' => $app->employee,
                    'department' => $app->employee?->department?->department_name ?? '-',
                    'position' => $app->position ?? $app->employee?->position,
                    'leave_type' => $app->leaveType,
                    'start_date' => $app->start_date ? \Carbon\Carbon::parse($app->start_date)->format('Y-m-d') : '-',
                    'end_date' => $app->end_date ? \Carbon\Carbon::parse($app->end_date)->format('Y-m-d') : '-',
                    'requested_days' => $app->requested_days ?? $app->days ?? 0,
                    'current_status_id' => (int) $app->current_status_id,
                    'status_label' => $app->status_label,
                    'can_approve_hod' => (int) $app->current_status_id === 1,
                    'can_approve_hr' => (int) $app->current_status_id === 24 && ($isHR || $isDirector),
                    'can_reject' => true,
                ];
            })
        ]);
    }

public function updateApplicationStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'id'     => 'required|integer|exists:sys_hr_leave_requests,id',
                'status' => 'required|integer',
            ]);

            $leaveRequest = LeaveRequest::findOrFail($validated['id']);
            $newStatus = (int) $validated['status'];
            $user = Auth::user();

            $isDirector = $user->hasRole(['directors', 'superadministrator']);
            $isHR = $user->hasRole(['hr_manager']);

            // Authorization checks based on stage transition
            if ($newStatus === 24) {
                if ((int) $leaveRequest->current_status_id !== 1) {
                    return response()->json(['success' => false, 'error' => 'Application is not at the supervisor review stage.'], 422);
                }
            } elseif ($newStatus === 5) {
                if (!$isHR && !$isDirector) {
                    return response()->json(['success' => false, 'error' => 'Unauthorized. Only HR or Directors can grant final approval.'], 403);
                }
                if ((int) $leaveRequest->current_status_id !== 24) {
                    return response()->json(['success' => false, 'error' => 'Application must be recommended by HOD before final approval.'], 422);
                }
            } elseif ($newStatus === 41) {
                if ((int) $leaveRequest->current_status_id === 5) {
                    return response()->json(['success' => false, 'error' => 'Approved leave cannot be rejected.'], 422);
                }
            }

            // Update the status and log who changed it
            $leaveRequest->current_status_id = $newStatus;
            $leaveRequest->status_changed_by = $user->id;
            $leaveRequest->save();

            return response()->json([
                'success' => true,
                'message' => 'Leave application status updated successfully.',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('updateApplicationStatus error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update application status: ' . $e->getMessage()], 500);
        }
    }

    public function leaveCalendarPlanUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'employee_id'   => 'required|integer',
                'leave_type_id' => 'required|integer',
                'start_date'    => 'required|date',
                'entitled_days' => 'required|numeric|min:0',
            ]);

            $result = $this->calculateEndDate($validated['start_date'], $validated['entitled_days'], $validated['leave_type_id'], $validated['entitled_days']);
            $end_date = $result['end_date'];
            
            $used_days = $this->getLeaveUsedDays(date('Y', strtotime($validated['start_date'])), $validated['leave_type_id'], $validated['employee_id']);
            $balance = $validated['entitled_days'] - $used_days;
    
            $plan = LeaveCalendarPlan::findOrFail($id);
            $plan->employee_id       = $validated['employee_id'];
            $plan->leave_type_id     = $validated['leave_type_id'];
            $plan->start_date        = $validated['start_date'];
            $plan->end_date          = $end_date;
            $plan->entitled_days     = $validated['entitled_days'];
            $plan->balance           = $balance;
            $plan->current_status_id = 5; // HR sets plans up directly, as the year generator does; there is no approval screen for them
            $plan->save();
    
            return response()->json(['success' => true, 'leave_calendar_plan' => $plan]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update leave calendar plan'], 500);
        }
    }
    
    public function leaveCalendarPlanDelete($id)
    {
        try {
            $plan = LeaveCalendarPlan::findOrFail($id);
            $plan->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete leave calendar plan'], 500);
        }
    }

    public function calenderPlanAvailableLeaveDays($staffId, $leaveTypeId)
    {
        try {
            $leaveType = LeaveType::find($leaveTypeId);
            
            if (!$leaveType) {
                return response()->json(['success' => false, 'error' => 'Leave type not found'], 404);
            }
            
            $currentYear = date('Y');
            $usedDays = \App\Models\LeaveRequestDaysTaken::whereHas('leaveRequest', function($query) use ($staffId, $leaveTypeId, $currentYear) {
                $query->where('staff_id', $staffId)
                    ->where('leave_type_id', $leaveTypeId)
                    ->whereYear('start_date', $currentYear)
                    ->whereIn('current_status_id', [5, 49]);
            })
            ->where('current_status_id', 5)
            ->sum('day_fraction');
            
            $entitledDays = $leaveType->entitled_days;
            $availableDays = $entitledDays - $usedDays;
            
            return response()->json([
                'success'        => true, 
                'entitled_days'  => $entitledDays,
                'used_days'      => $usedDays,
                'available_days' => $availableDays
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch available leave days'], 500);
        }
    }

    public function validateStartDate(Request $request)
    {
        try {
            $startDate = $request->input('start_date');
            
            if (!$startDate) {
                return response()->json(['success' => false, 'error' => 'Start date is required'], 422);
            }
            
            $isPublicHoliday = $this->calendar->holidayOn(\Carbon\Carbon::parse($startDate));

            if ($isPublicHoliday) {
                return response()->json([
                    'success'       => false, 
                    'is_holiday'    => true,
                    'holiday_title' => $isPublicHoliday->title,
                    'error'         => 'The selected date (' . date('d M Y', strtotime($startDate)) . ') is a public holiday: ' . $isPublicHoliday->title
                ]);
            }
            
            return response()->json(['success' => true, 'is_holiday' => false]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to validate start date'], 500);
        }
    }

    public function leaveTypesList()
    {
        try {
            $leaveTypes = LeaveType::select('id', 'type_name', 'entitled_days', 'salary_rate')->get();
            return response()->json(['success' => true, 'leave_types' => $leaveTypes]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch leave types'], 500);
        }
    }

    public function leaveApplications()
    {
        return view('leave_management.leave_applications', [
            'departments' => Department::orderBy('department_name')->get(['id', 'department_name']),
        ]);
    }

    public function leaveApplicationsList(?Request $request = null)
    {
        $request ??= request();
        $validated = $request->validate([
            'status' => 'nullable|integer|in:1,24,5,41',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'department_id' => 'nullable|integer|exists:sys_set_departments,id',
        ]);
        if (!empty($validated['date_from']) && !empty($validated['date_to']) && $validated['date_to'] < $validated['date_from']) {
            return response()->json(['error' => 'The end date must be on or after the start date.'], 422);
        }

        try {
            // Everyone's requests for those who can read them; everyone else only sees their own
            $canReadAll = Auth::user()->isAbleTo('read_leave_applications');
            $ownStaffId = Auth::user()->linkedStaff()?->id;

            $requests = LeaveRequest::with('createdBy', 'employee.department', 'leaveType', 'position')
                ->when(!$canReadAll, fn ($query) => $query->where('staff_id', $ownStaffId ?? 0))
                ->when(isset($validated['status']), fn ($query) => $query->where('current_status_id', $validated['status']))
                ->when(isset($validated['department_id']), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $validated['department_id'])))
                ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('end_date', '>=', $validated['date_from']))
                ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('start_date', '<=', $validated['date_to']))
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($request) {
                    $encrypted_leave_application_id = Crypt::encrypt($request->id);
                    
                    return [
                        'id'                             => $request->id,
                        'staff_id'                       => $request->staff_id,
                        'encrypted_leave_application_id' => $encrypted_leave_application_id,
                        'position_id'                    => $request->position_id,
                        'start_date'                     => $request->start_date ? $request->start_date->format('Y-m-d H:i') : null,
                        'end_date'                       => $request->end_date ? $request->end_date->format('Y-m-d H:i') : null,
                        'is_partial_day'                 => $request->isPartialDay(),
                        'hours_requested'                => $request->getHoursRequested(),
                        'days'                           => $request->days,
                        'display_days'                   => $request->getDisplayDuration(),
                        'entitled_days'                  => $request->entitled_days,
                        'days_balance'                   => $request->days_balance,
                        'used_days'                      => $request->used_days,
                        'requested_days'                 => $request->requested_days,
                        'amount'                         => $request->amount,
                        'current_status_id'              => $request->current_status_id,
                        'waiting_on'                     => $this->router->waitingOn($request),
                        'allowance_type_id'              => $request->allowance_type_id,
                        'created_by'                     => $request->createdBy ? [
                            'id'   => $request->createdBy->id,
                            'name' => $request->createdBy->name,
                        ] : null,
                        'employee'                       => $request->employee ? [
                            'id'   => $request->employee->id,
                            'name' => $request->employee->full_names,
                            'department_id' => $request->employee->department_id,
                            'department_name' => $request->employee->department?->department_name,
                        ] : null,
                        'recalled_at'                    => $request->recalled_at,
                        'recall_effective_date'           => $request->recall_effective_date,
                        'recall_reason'                   => $request->recall_reason,
                        'recalled_days'                   => $request->recalled_days,
                        'leave_type'                     => $request->leaveType ? [
                            'id'        => $request->leaveType->id,
                            'type_name' => $request->leaveType->type_name,
                        ] : null,
                        'position'                       => $request->position ? [
                            'id'            => $request->position->id,
                            'position_name' => $request->position->position_name,
                        ] : null,
                        'date_time'                      => $request->date_time,
                        'can_update'                     => Auth::user()->isAbleTo('update_leave_applications'),
                        'can_delete'                     => Auth::user()->isAbleTo('delete_leave_applications'),
                        'can_view'                       => Auth::user()->isAbleTo('read_leave_applications'),
                    ];
                });
    
            $selectedDate = $validated['date_from'] ?? $validated['date_to'] ?? now()->toDateString();
            $rangeStart = $validated['date_from'] ?? $selectedDate;
            $rangeEnd = $validated['date_to'] ?? $selectedDate;
            $staffOnLeave = LeaveRequest::with(['employee.department', 'employee.position'])
                ->where('current_status_id', LeaveRequest::STATUS_APPROVED)
                ->where(fn ($query) => $query->whereNull('recalled_at')->orWhereDate('recall_effective_date', '>', $rangeStart))
                ->whereDate('start_date', '<=', $rangeEnd)
                ->whereDate('end_date', '>=', $rangeStart)
                ->when(!$canReadAll, fn ($query) => $query->where('staff_id', $ownStaffId ?? 0))
                ->when(isset($validated['department_id']), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $validated['department_id'])))
                ->get()
                ->unique('staff_id')
                ->map(fn ($leave) => [
                    'staff_id' => $leave->staff_id,
                    'staff_name' => $leave->employee?->full_names ?? 'Unknown staff',
                    'staff_number' => $leave->employee?->staff_number,
                    'department_name' => $leave->employee?->department?->department_name ?? 'Unassigned',
                    'position_name' => $leave->employee?->position?->position_name,
                    'start_date' => $leave->start_date?->format('Y-m-d'),
                    'end_date' => $leave->end_date?->format('Y-m-d'),
                ])->values();
            $departmentRecallThreshold = isset($validated['department_id'])
                ? $this->departmentLeaveThreshold((int) $validated['department_id'], now()->toDateString())
                : null;

            return response()->json([
                'leave_applications' => $requests,
                'staff_on_leave' => $staffOnLeave,
                'staff_on_leave_period' => ['from' => $rangeStart, 'to' => $rangeEnd],
                'department_recall_threshold' => $departmentRecallThreshold,
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load leave applications'], 500);
        }
    }

    public function leaveApplicationsByStaff($encryptedLeaveApplicationId)
    {
        try {
            $leaveApplicationId = Crypt::decrypt($encryptedLeaveApplicationId);

            $requests = LeaveRequest::with('createdBy', 'employee.department', 'leaveType', 'position')
                ->where('id', $leaveApplicationId)
                ->orderBy('date_time', 'desc')
                ->get();

            foreach ($requests as $request) {
                if (!$this->canManageLeaveOf((int) $request->staff_id, 'read_leave_applications')) {
                    return response('You are not allowed to view this leave application.', 403);
                }
            }

            $application = $requests->first();
            $staffingDate = request()->query('staffing_date', optional($application?->start_date)->format('Y-m-d') ?? now()->toDateString());
            if (!is_string($staffingDate) || !Carbon::hasFormat($staffingDate, 'Y-m-d')) $staffingDate = optional($application?->start_date)->format('Y-m-d') ?? now()->toDateString();
            $department = $application?->employee?->department;
            $departmentStaff = collect();
            if ($department) {
                $departmentStaff = Staff::where('department_id', $department->id)
                    ->whereIn('current_status_id', [1, 4])
                    ->with('position')
                    ->orderBy('full_names')
                    ->get()
                    ->unique('id')
                    ->values();
            }
            $onLeaveStaffIds = $departmentStaff->isEmpty() ? collect() : LeaveRequest::where('current_status_id', LeaveRequest::STATUS_APPROVED)
                ->where(fn ($query) => $query->whereNull('recalled_at')->orWhereDate('recall_effective_date', '>', $staffingDate))
                ->whereDate('start_date', '<=', $staffingDate)
                ->whereDate('end_date', '>=', $staffingDate)
                ->whereHas('employee', fn ($query) => $query->where('department_id', $department->id)->whereIn('current_status_id', [1, 4]))
                ->pluck('staff_id')->unique();
            $staffingThreshold = $department ? $this->departmentLeaveThreshold((int) $department->id, $staffingDate) : null;
            $todayThreshold = $department ? $this->departmentLeaveThreshold((int) $department->id, now()->toDateString()) : null;

            return view('leave_management.leave_application_details', [
                'leave_applications' => $requests,
                'staffingDate' => $staffingDate,
                'departmentStaff' => $departmentStaff,
                'onLeaveStaffIds' => $onLeaveStaffIds,
                'staffingThreshold' => $staffingThreshold,
                'departmentStaffingHealthy' => (bool) ($todayThreshold['met'] ?? false),
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load leave applications for the specified staff'], 500);
        }
    }

    /** Department is healthy when at least 80% of active employees are available on the given date. */
    private function departmentLeaveThreshold(int $departmentId, string $date): array
    {
        $staffTotal = Staff::where('department_id', $departmentId)
            ->whereIn('current_status_id', [1, 4])
            ->count();

        $onLeave = LeaveRequest::where('current_status_id', LeaveRequest::STATUS_APPROVED)
            ->where(fn ($query) => $query->whereNull('recalled_at')->orWhereDate('recall_effective_date', '>', $date))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereHas('employee', fn ($query) => $query->where('department_id', $departmentId)->whereIn('current_status_id', [1, 4]))
            ->distinct('staff_id')
            ->count('staff_id');

        $activeStaff = max(0, $staffTotal - $onLeave);
        $requiredActive = $staffTotal > 0 ? (int) ceil($staffTotal * 0.8) : 0;
        return [
            'staff_total' => $staffTotal,
            'on_leave' => $onLeave,
            'active_staff' => $activeStaff,
            'required_active' => $requiredActive,
            'percentage' => $staffTotal > 0 ? round(($onLeave / $staffTotal) * 100, 1) : 0.0,
            'active_percentage' => $staffTotal > 0 ? round(($activeStaff / $staffTotal) * 100, 1) : 0.0,
            'met' => $staffTotal > 0 && $activeStaff >= $requiredActive,
        ];
    }

    public function recallApplication(Request $request, int $id)
    {
        if (!Auth::user()->isAbleTo('update_leave_applications')) {
            return response()->json(['success' => false, 'error' => 'You are not allowed to recall leave applications.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
            'effective_date' => 'required|date|after_or_equal:today',
        ]);

        try {
            $result = DB::transaction(function () use ($id, $validated) {
                $leave = LeaveRequest::whereKey($id)->lockForUpdate()->firstOrFail();
                if ((int) $leave->current_status_id !== LeaveRequest::STATUS_APPROVED) {
                    throw new \DomainException('Only approved leave can be recalled.');
                }
                if ($leave->recalled_at) {
                    throw new \DomainException('This leave has already been recalled.');
                }
                $today = now()->toDateString();
                if (Carbon::parse($leave->start_date)->toDateString() > $today || Carbon::parse($leave->end_date)->toDateString() < $today) {
                    throw new \DomainException('Recall is available only while the employee is within the approved leave period.');
                }
                if ($validated['effective_date'] > Carbon::parse($leave->end_date)->toDateString()) {
                    throw new \DomainException('The effective recall date must fall within the approved leave period.');
                }

                $staff = Staff::find($leave->staff_id);
                if (!$staff?->department_id) {
                    throw new \DomainException('The employee must be assigned to a department before recall eligibility can be checked.');
                }
                $threshold = $this->departmentLeaveThreshold((int) $staff->department_id, $today);
                if ($threshold['met']) {
                    throw new \DomainException('Recall is available only when active staffing falls below 80% in this department. Current level: ' . $threshold['active_staff'] . ' of ' . $threshold['staff_total'] . ' active (' . $threshold['active_percentage'] . '%).');
                }

                $dayRows = LeaveRequestDaysTaken::where('parent_id', $leave->id)
                    ->where('current_status_id', 5)
                    ->whereDate('leave_date', '>=', $validated['effective_date'])
                    ->lockForUpdate()
                    ->get();
                if ($dayRows->isEmpty()) {
                    throw new \DomainException('There are no unused approved leave days to refund from that date.');
                }
                $refunded = round((float) $dayRows->sum('day_fraction'), 2);
                foreach ($dayRows as $day) $day->update(['current_status_id' => 41]);

                $leave->used_days = max(0, (float) $leave->used_days - $refunded);
                $leave->days_balance = (float) $leave->days_balance + $refunded;
                $leave->recalled_at = now();
                $leave->recall_effective_date = $validated['effective_date'];
                $leave->recall_reason = $validated['reason'];
                $leave->recalled_by = Auth::id();
                $leave->recalled_days = $refunded;
                $leave->save();

                $isAnnual = $this->calendar->isAnnualLeaveType(LeaveType::whereKey($leave->leave_type_id)->value('type_name'));
                if ($isAnnual) {
                    $plan = LeaveCalendarPlan::where('employee_id', $leave->staff_id)
                        ->where('leave_type_id', $leave->leave_type_id)
                        ->whereYear('start_date', Carbon::parse($leave->start_date)->year)
                        ->where('current_status_id', 5)
                        ->lockForUpdate()->first();
                    if ($plan) {
                        $plan->balance = (float) $plan->balance + $refunded;
                        $plan->save();
                    }
                }

                return ['leave' => $leave, 'refunded' => $refunded];
            });

            $staffUser = User::where('staff_id', $result['leave']->staff_id)->first()
                ?? User::whereHas('staffLink', fn ($query) => $query->where('staff_id', $result['leave']->staff_id))->first();
            if ($staffUser) {
                Notification::create([
                    'type' => 'LeaveRecall',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $staffUser->id,
                    'data' => ['leave_request_id' => $id, 'effective_date' => $validated['effective_date'], 'reason' => $validated['reason']],
                    'title' => 'Leave recall notice',
                    'message' => 'Your approved leave has been recalled effective ' . Carbon::parse($validated['effective_date'])->format('d M Y') . '. Reason: ' . $validated['reason'],
                    'action_url' => route('leave-management.leave-applications.by-staff', Crypt::encrypt($id)),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Employee recalled and ' . $result['refunded'] . ' leave day(s) refunded.', 'refunded_days' => $result['refunded']]);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Leave recall failed', ['leave_request_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to recall leave.'], 500);
        }
    }
    
    public function leaveApplicationsStore(Request $request)
    {
        try {
            $durationType = $request->input('leave_duration_type', 'full_day');
            
            $rules = [
                'staff_id'            => 'required|integer',
                'leave_type_id'       => 'required|integer',
                'available_days'      => 'required|numeric|min:0',
                'allowance_type'      => 'nullable|integer|min:0',
                'amount'              => 'nullable|numeric|min:0',
                'leave_duration_type' => 'required|in:full_day,partial_day,days_and_hours',
            ];
            
            if ($durationType === 'full_day') {
                $rules['start_date'] = 'required|date';
                $rules['requested_days'] = 'required|numeric|min:0.5|max:366';
            } else {
                $rules['start_date'] = 'required|date';
                $rules['end_date']   = 'required|date';
            }
            
            $validated = $request->validate($rules);

            if (!$this->canManageLeaveOf((int) $validated['staff_id'], 'create_leave_applications')) {
                return response()->json(['success' => false, 'error' => 'You can only apply for leave for yourself.'], 403);
            }

            $staff = Staff::find($validated['staff_id']);
            if (!$staff) {
                return response()->json(['success' => false, 'error' => 'Staff member not found.'], 422);
            }
            
            $requestModel = new LeaveRequest();
            $requestModel->staff_id    = $validated['staff_id'];
            $requestModel->leave_type_id = $validated['leave_type_id'];
            $requestModel->position_id = $staff->position_id;
            
            if ($durationType === 'full_day') {
                $result = $this->calculateEndDate(
                    $validated['start_date'], 
                    $validated['requested_days'], 
                    $validated['leave_type_id'], 
                    $validated['available_days']
                );
                
                $requestModel->start_date     = $validated['start_date'] . ' 08:00:00';
                $requestModel->end_date       = $result['end_date'] . ' 17:00:00';
                // Record what the dates really charge: a request that runs into a Saturday can cost half a day more
                $charged = $this->chargedDays($validated['start_date'], $result['end_date'], (float) $validated['requested_days'], (int) $validated['leave_type_id']);
                $requestModel->days           = $charged;
                $requestModel->requested_days = $charged;
            } else {
                $requestModel->start_date = $validated['start_date'];
                $requestModel->end_date   = $validated['end_date'];
                
                $start = new \DateTime($validated['start_date']);
                $end   = new \DateTime($validated['end_date']);
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $days  = round($hours / 8, 2);
                
                $requestModel->days           = $days;
                $requestModel->requested_days = $days;
            }

            $currentYear = (int) date('Y', strtotime($validated['start_date']));

            $targetLeaveType = LeaveType::find($validated['leave_type_id']);

            if ($this->overlapsScheduledAnnualLeave((int) $validated['staff_id'], $requestModel->start_date, $requestModel->end_date, $targetLeaveType, (float) $requestModel->days)) {
                return response()->json(['success' => false, 'error' => 'These dates are reserved in the employee\'s annual leave schedule. Choose a different date.'], 422);
            }

            // Prevent overlapping applications for the employee
            if ($this->overlapsActiveLeave((int) $validated['staff_id'], $requestModel->start_date, $requestModel->end_date)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'An active leave application already overlaps these dates.'
                ], 422);
            }

            // Annual leave needs the year's plan (created now if it is the first time), and the days must fit the balance
            ['error' => $balanceError, 'warning' => $balanceWarning] = $this->applyBalance($requestModel, $staff, $targetLeaveType, $currentYear);
            if ($balanceError) {
                return $balanceError;
            }

            $requestModel->used_days         = 0;
            $requestModel->amount            = $validated['amount'] ?? 0;
            $requestModel->allowance_type_id = $validated['allowance_type'] ?? null;
            $requestModel->current_status_id = 1;
            $requestModel->created_by_id     = Auth::id();
            $requestModel->date_time         = now();
            $requestModel->save();

            // Decide who approves it: the supervisor, straight to HR, or automatic for the HR approver
            $routing = $this->approvals->submit($requestModel, (int) Auth::id());

            return response()->json(['success' => true, 'leave_application' => $requestModel->fresh(), 'routing' => $routing, 'warning' => $balanceWarning]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to create leave application'], 500);
        }
    }


public function approveApplication(Request $request)
{
    try {
        $applicationId = $request->input('id');

        $application = DB::table('sys_hr_leave_requests')->where('id', $applicationId)->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'Leave application not found.'], 404);
        }

        // If it's currently at Stage 1 (status 1), advance it to HR Final (status 24)
        $nextStatus = $application->current_status_id == 1 ? 24 : 3; 

        DB::table('sys_hr_leave_requests')
            ->where('id', $applicationId)
            ->update([
                'current_status_id' => $nextStatus,
                'status_changed_by' => Auth::id()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave application successfully recommended/approved.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

public function rejectApplication(Request $request)
{
    try {
        $applicationId = $request->input('id');

        DB::table('sys_hr_leave_requests')
            ->where('id', $applicationId)
            ->update([
                'current_status_id' => 0, // Set to your rejected status ID
                'status_changed_by' => Auth::id()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave application rejected.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}
    
    public function leaveApplicationsUpdate(Request $request, $id)
    {
        try {
            $durationType = $request->input('leave_duration_type', 'full_day');
            
            $rules = [
                'staff_id'            => 'required|integer',
                'leave_type_id'       => 'required|integer',
                'available_days'      => 'required|numeric|min:0',
                'allowance_type'      => 'nullable|integer|min:0',
                'amount'              => 'nullable|numeric|min:0',
                'leave_duration_type' => 'required|in:full_day,partial_day,days_and_hours',
            ];
            
            if ($durationType === 'full_day') {
                $rules['start_date'] = 'required|date';
                $rules['requested_days'] = 'required|numeric|min:0.5|max:366';
            } else {
                $rules['start_date'] = 'required|date';
                $rules['end_date']   = 'required|date';
            }
            
            $validated = $request->validate($rules);

            $requestModel = LeaveRequest::findOrFail($id);

            // Both the person the request belongs to now and the one it is being changed to must be allowed
            if (!$this->canManageLeaveOf((int) $requestModel->staff_id, 'update_leave_applications') || !$this->canManageLeaveOf((int) $validated['staff_id'], 'update_leave_applications')) {
                return response()->json(['success' => false, 'error' => 'You can only edit your own leave application.'], 403);
            }

            $staff = Staff::find($validated['staff_id']);
            if (!$staff) {
                return response()->json(['success' => false, 'error' => 'Staff member not found.'], 422);
            }
            
            // Only a request still waiting for approval can be edited; editing resets it to the start of the approval route
            if (!in_array((int) $requestModel->current_status_id, [1, 24], true)) {
                return response()->json(['success' => false, 'error' => 'Only a pending leave application can be edited.'], 422);
            }

            $requestModel->staff_id      = $validated['staff_id'];
            $requestModel->leave_type_id = $validated['leave_type_id'];
            $requestModel->position_id   = $staff->position_id;
            
            if ($durationType === 'full_day') {
                $result = $this->calculateEndDate(
                    $validated['start_date'], 
                    $validated['requested_days'], 
                    $validated['leave_type_id'], 
                    $validated['available_days']
                );
                
                $requestModel->start_date     = $validated['start_date'] . ' 08:00:00';
                $requestModel->end_date       = $result['end_date'] . ' 17:00:00';
                // Record what the dates really charge: a request that runs into a Saturday can cost half a day more
                $charged = $this->chargedDays($validated['start_date'], $result['end_date'], (float) $validated['requested_days'], (int) $validated['leave_type_id']);
                $requestModel->days           = $charged;
                $requestModel->requested_days = $charged;
            } else {
                $requestModel->start_date = $validated['start_date'];
                $requestModel->end_date   = $validated['end_date'];
                
                $start = new \DateTime($validated['start_date']);
                $end   = new \DateTime($validated['end_date']);
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $days  = round($hours / 8, 2);
                
                $requestModel->days           = $days;
                $requestModel->requested_days = $days;
            }

            if ($this->overlapsScheduledAnnualLeave((int) $validated['staff_id'], $requestModel->start_date, $requestModel->end_date, LeaveType::find($validated['leave_type_id']), (float) $requestModel->days)) {
                return response()->json(['success' => false, 'error' => 'These dates are reserved in the employee\'s annual leave schedule. Choose a different date.'], 422);
            }
    
            if ($this->overlapsActiveLeave((int) $validated['staff_id'], $requestModel->start_date, $requestModel->end_date, (int) $requestModel->id)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'An active leave application already overlaps these dates.'
                ], 422);
            }

            // Same rule as filing: the year's plan (created on first use) and the days must fit the balance
            ['error' => $balanceError, 'warning' => $balanceWarning] = $this->applyBalance($requestModel, $staff, LeaveType::find($validated['leave_type_id']), (int) date('Y', strtotime($validated['start_date'])));
            if ($balanceError) {
                return $balanceError;
            }

            $requestModel->used_days         = 0;
            $requestModel->amount            = $validated['amount'] ?? 0;
            $requestModel->allowance_type_id = $validated['allowance_type'] ?? null;
            $requestModel->current_status_id = 1;
            $requestModel->status_changed_by = null;
            $requestModel->created_by_id     = Auth::id();
            $requestModel->date_time         = now();
            $requestModel->save();

            $routing = $this->approvals->submit($requestModel, (int) Auth::id());

            return response()->json(['success' => true, 'leave_application' => $requestModel->fresh(), 'routing' => $routing, 'warning' => $balanceWarning]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update leave application'], 500);
        }
    }

    public function leaveApplicationsDelete($id)
    {
        try {
            $requestModel = LeaveRequest::findOrFail($id);

            if (!$this->canManageLeaveOf((int) $requestModel->staff_id, 'delete_leave_applications')) {
                return response()->json(['success' => false, 'error' => 'You can only delete your own leave application.'], 403);
            }

            // Approved leave has days deducted from the balance, so it can't just be deleted: it is adjusted instead
            if (!in_array((int) $requestModel->current_status_id, [1, 24, 41], true)) {
                return response()->json(['success' => false, 'error' => 'Approved leave cannot be deleted. Use Leave Adjustments to return the days.'], 422);
            }

            $requestModel->delete();
            return response()->json(['success' => true]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Leave application not found'], 404);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete leave application'], 500);
        }
    }

/**
     * Entitled days for a leave type when the staff member has no calendar plan: annual leave is pro-rated
     * from the hire date, every other type uses its configured days.
     */
    private function entitlementWithoutPlan(int $staffId, ?LeaveType $leaveType, int $year): float {
        if ($leaveType && $this->calendar->isAnnualLeaveType($leaveType->type_name)) {
            $hireDate = Staff::find($staffId)?->getRawOriginal('hire_date');

            return $this->calendar->calculateAnnualEntitlement($hireDate, $year);
        }

        return (float) ($leaveType->entitled_days ?? 0);
    }

    public function availableLeaveDays($staffId, $leaveTypeId)
    {
        try {
            $currentYear = (int) date('Y');

            $leaveType = LeaveType::find($leaveTypeId);
            $staff = Staff::find($staffId);
            if (!$leaveType || !$staff) {
                return response()->json(['success' => false, 'error' => 'Leave type not found'], 404);
            }

            // Everyone else may only look at their own balance
            if (!$this->canManageLeaveOf((int) $staff->id, 'read_leave_applications')) {
                return response()->json(['success' => false, 'error' => 'You can only see your own leave balance.'], 403);
            }

            $figures = $this->plans->availableDays($staff, $leaveType, $currentYear);
            if ($figures['error']) {
                return response()->json(['success' => false, 'error' => $figures['error']]);
            }

            $plan = $figures['plan'];

            return response()->json([
                'success'        => true,
                'available_days' => $figures['available'],
                'start_date'     => $plan && $plan->start_date ? \Carbon\Carbon::parse($plan->start_date)->format('Y-m-d') : "{$currentYear}-01-01",
                'end_date'       => $plan && $plan->end_date ? \Carbon\Carbon::parse($plan->end_date)->format('Y-m-d') : "{$currentYear}-12-31",
            ]);
        } catch (\Throwable $e) {
            Log::error('availableLeaveDays error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to fetch available leave days'], 500);
        }
    }

    public function getStaffAnnualSlots(Request $request)
    {
        try {
            $staffId = (int) ($request->get('staff_id') ?? Auth::user()->linkedStaff()?->id);
            $year    = (int) ($request->get('year') ?? date('Y'));

            // 1. Find the plan for this employee for the given year (either Normal or Annual)
            $plan = LeaveCalendarPlan::where('employee_id', $staffId)
                ->whereYear('start_date', $year)
                ->where(function ($query) {
                    $query->whereHas('leaveType', function ($q) {
                        $q->where('type_name', 'ILIKE', '%annual%')
                          ->orWhere('type_name', 'ILIKE', '%normal%');
                    })
                    ->orWhereNotNull('entitled_days');
                })
                ->orderBy('id', 'desc')
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'No annual leave calendar plan found for this employee in ' . $year,
                    'slots'   => [],
                ]);
            }

            // 2. Fetch slots via Eloquent relationship safely
            $slots = $plan->slots()->orderBy('start_date', 'asc')->get();

            // 3. Find dates of existing active requests for this staff member
            // (1 = Pending, 24 = Partially Approved, 5 = Approved)
            $activeRequestRanges = LeaveRequest::where('staff_id', $staffId)
                ->whereIn('current_status_id', [1, 5, 24])
                ->get(['start_date', 'end_date'])
                ->map(fn ($request) => [
                    'start' => \Carbon\Carbon::parse($request->start_date)->toDateString(),
                    'end' => \Carbon\Carbon::parse($request->end_date)->toDateString(),
                ])
                ->toArray();

            $formattedSlots = $slots->map(function ($slot) use ($activeRequestRanges) {
                $start = \Carbon\Carbon::parse($slot->start_date)->format('Y-m-d');
                $end   = \Carbon\Carbon::parse($slot->end_date)->format('Y-m-d');
                $displayStart = \Carbon\Carbon::parse($slot->start_date)->format('d M Y');
                $displayEnd   = \Carbon\Carbon::parse($slot->end_date)->format('d M Y');

                // Check if this slot has already been applied for
                $isBooked = collect($activeRequestRanges)->contains(fn ($range) => $start <= $range['end'] && $end >= $range['start']);
                $label = "{$displayStart} to {$displayEnd} ({$slot->days_count} Working Days)";
                if ($isBooked) {
                    $label .= ' — [Already Applied]';
                }

                return [
                    'id'          => $slot->id,
                    'start_date'  => $start,
                    'end_date'    => $end,
                    'display'     => $label,
                    'days'        => (float) $slot->days_count,
                    'is_disabled' => $isBooked,
                ];
            });

            return response()->json([
                'success' => true,
                'plan_id' => $plan->id,
                'slots'   => $formattedSlots,
            ]);

        } catch (\Throwable $e) {
            Log::error('getStaffAnnualSlots error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'slots'   => []
            ], 500);
        }
    }

    public function downloadLeavePass($encryptedLeaveApplicationId)
    {
        try {
            $leaveApplicationId = Crypt::decrypt($encryptedLeaveApplicationId);

            $leaveRequest = LeaveRequest::with(['employee.department', 'employee.position', 'leaveType', 'createdBy'])
                ->findOrFail($leaveApplicationId);

            // Only the employee the leave belongs to can download their own pass
            if (Auth::user()->linkedStaff()?->id !== (int) $leaveRequest->staff_id) {
                return response()->json(['success' => false, 'error' => 'You can only download your own leave pass.'], 403);
            }

            // Gate access: allow download only when approved (status 5)
            if ((int) $leaveRequest->current_status_id !== 5) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Leave pass is only available for approved leave applications.'
                ], 403);
            }

            // When the HR (final) approval was given, from the approvals log
            $approvedAt = ConfirmationLog::where('section', 'Leave Application')
                ->where('approval_stage', 2)
                ->whereJsonContains('ids', $leaveRequest->id)
                ->orderByDesc('confirmed_at')
                ->value('confirmed_at');

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('leave_management.pdf.leave_pass', [
                'leave' => $leaveRequest,
                'staff' => $leaveRequest->employee,
                'approvedAt' => $approvedAt,
            ])->setPaper('a5', 'portrait');

            $fileName = 'Leave_Pass_' . ($leaveRequest->employee->staff_number ?? $leaveRequest->id) . '.pdf';

            return $pdf->download($fileName);

        } catch (\Illuminate\Contracts\Encryption\DecryptException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Leave pass not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('downloadLeavePass error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['success' => false, 'error' => 'Failed to generate leave pass'], 500);
        }
    }

    public function publicHolidaysList()
    {
        try {
            $holidays = PublicHoliday::where('current_status_id', 5)
                ->orderBy('start_date')
                ->get()
                ->map(function ($holiday) {
                    return [
                        'id'         => $holiday->id,
                        'title'      => $holiday->title,
                        'start_date' => $holiday->start_date ? date('Y-m-d', strtotime($holiday->start_date)) : null,
                        'end_date'   => $holiday->end_date ? date('Y-m-d', strtotime($holiday->end_date)) : null,
                    ];
                });
    
            return response()->json(['success' => true, 'public_holidays' => $holidays]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch public holidays'], 500);
        }
    }

    public function leaveBalanceReport()
    {
        return view('leave_management.leave_balance_report');
    }
    
    public function leaveBalanceReportData(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $staffId = $request->input('staff_id');
            
            if (!$staffId) {
                return response()->json(['success' => false, 'error' => 'Staff ID is required'], 400);
            }
            
            // Active (1) or On Leave (4); suspended and terminated staff have no balance report
            $employee = Staff::where('id', $staffId)
                ->whereIn('current_status_id', [1, 4])
                ->with(['department', 'position'])
                ->first();
            
            if (!$employee) {
                return response()->json(['success' => false, 'error' => 'Staff member not found or inactive'], 404);
            }
            
            $leaveTypes = LeaveType::where('current_status_id', 5)->orderBy('type_name')->get();
            $leaveBalances = [];
            
            foreach ($leaveTypes as $leaveType) {
                // Only annual leave has a plan; the other types use their configured days (annual with no plan is pro-rated)
                $plan = !$this->calendar->isAnnualLeaveType($leaveType->type_name) ? null : LeaveCalendarPlan::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->whereYear('start_date', $year)
                    ->where('current_status_id', 5)
                    ->first();

                $daysTaken = LeaveRequest::where('staff_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->whereYear('start_date', $year)
                    ->whereIn('current_status_id', [5, 19, 49])
                    ->sum(DB::raw("CAST(NULLIF(used_days, '') AS NUMERIC)")); // used_days is a text column

                $daysPending = LeaveRequest::where('staff_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->whereYear('start_date', $year)
                    ->whereIn('current_status_id', [1, 24])
                    ->sum('requested_days');

                $entitled = $plan ? (float) $plan->entitled_days : $this->entitlementWithoutPlan((int) $employee->id, $leaveType, (int) $year);
                $balance = $plan ? (float) $plan->balance : $entitled - (float) $daysTaken;

                $leaveBalances[] = [
                    'leave_type_id'     => $leaveType->id,
                    'leave_type_name'   => $leaveType->type_name,
                    'plan_start_date'   => $plan && $plan->start_date ? date('Y-m-d', strtotime($plan->start_date)) : '-',
                    'plan_end_date'     => $plan && $plan->end_date ? date('Y-m-d', strtotime($plan->end_date)) : '-',
                    'entitled_days'     => round($entitled, 2),
                    'days_taken'        => round($daysTaken, 2),
                    'days_pending'      => round($daysPending, 2),
                    'current_balance'   => round($balance, 2),
                    'available_balance' => round($balance - $daysPending, 2),
                    'has_plan'          => (bool) $plan,
                ];
            }
            
            $reportData = [
                'staff_id'       => $employee->id,
                'staff_number'   => $employee->staff_number ?? '-',
                'full_names'     => $employee->full_names,
                'department'     => $employee->department ? $employee->department->department_name : '-',
                'position'       => $employee->position ? $employee->position->position_name : '-',
                'email'          => $employee->email_address ?? '-',
                'leave_balances' => $leaveBalances,
            ];
            
            return response()->json([
                'success' => true,
                'data'    => [$reportData],
                'year'    => $year
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to generate leave balance report'], 500);
        }
    }
    
    public function exportLeaveBalanceReport(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $staffId = $request->input('staff_id');
            
            $response = $this->leaveBalanceReportData($request);
            $responseData = json_decode($response->content(), true);
            
            if (!$responseData['success']) {
                return response()->json(['success' => false, 'error' => 'Failed to get report data'], 500);
            }
            
            $data = $responseData['data'];
            $filename = "leave_balance_report_{$year}_" . date('YmdHis') . ".csv";
            $handle = fopen('php://temp', 'r+');
            
            $headers = [
                'Staff Number', 'Full Names', 'Department', 'Position', 'Email',
                'Leave Type', 'Plan Start', 'Plan End', 'Entitled Days', 
                'Days Taken', 'Days Pending', 'Current Balance', 'Available Balance'
            ];
            fputcsv($handle, $headers);
            
            foreach ($data as $staff) {
                foreach ($staff['leave_balances'] as $balance) {
                    if ($balance['has_plan']) {
                        $row = [
                            $staff['staff_number'],
                            $staff['full_names'],
                            $staff['department'],
                            $staff['position'],
                            $staff['email'],
                            $balance['leave_type_name'],
                            $balance['plan_start_date'],
                            $balance['plan_end_date'],
                            $balance['entitled_days'],
                            $balance['days_taken'],
                            $balance['days_pending'],
                            $balance['current_balance'],
                            $balance['available_balance'],
                        ];
                        fputcsv($handle, $row);
                    }
                }
            }
            
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to export report'], 500);
        }
    }

    public function leaveAdjustments()
    {
        return view('leave_management.leave_adjustment');
    }
    
    public function leaveAdjustmentsList()
    {
        try {
            $requests = LeaveRequest::with(['employee', 'position', 'leaveType'])
                ->whereIn('current_status_id', [5])
                ->get()
                ->map(function($request) {
                    $daysTaken = (float) \App\Models\LeaveRequestDaysTaken::where('parent_id', $request->id)
                        ->where('current_status_id', 5)
                        ->sum('day_fraction');
                    
                    $daysRefunded = (float) \App\Models\LeaveRequestDaysTaken::where('parent_id', $request->id)
                        ->where('current_status_id', 41)
                        ->sum('day_fraction');
                    
                    $adjustmentCount = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)->count();

                    $leaveAdjustmentCurrentStatus = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)
                        ->orderBy('requested_at', 'desc')
                        ->first();
                    
                    $hasPendingAdjustment = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)
                        ->whereIn('current_status_id', [1, 24])
                        ->exists();
                    
                    return [
                        'id'                                 => $request->id,
                        'employee'                           => $request->employee,
                        'position'                           => $request->position,
                        'leave_type'                         => $request->leaveType,
                        'start_date'                         => $request->start_date,
                        'end_date'                           => $request->end_date,
                        'days_taken_count'                   => $daysTaken,
                        'days_refunded_count'                => $daysRefunded,
                        'current_status_id'                  => $request->current_status_id,
                        'leave_adjustment_current_status_id' => $leaveAdjustmentCurrentStatus->current_status_id ?? null,
                        'adjustment_count'                   => $adjustmentCount,
                        'has_pending_adjustment'             => $hasPendingAdjustment,
                        'is_partial_day'                     => $request->isPartialDay(),
                        'hours_requested'                    => $request->getHoursRequested(),
                        'can_update'                         => Auth::user()->isAbleTo('update_leave_adjustments'),
                        'can_view'                           => Auth::user()->isAbleTo('read_leave_adjustments'),
                    ];
                });
            
            return response()->json(['success' => true, 'leave_requests' => $requests]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {}
            return response()->json(['success' => false, 'error' => 'Failed to load leave adjustments'], 500);
        }
    }
    
    public function leaveAdjustmentDetails($id)
    {
        try {
            $leaveRequest = LeaveRequest::with(['employee', 'position', 'leaveType'])->findOrFail($id);
            
            $daysTaken = \App\Models\LeaveRequestDaysTaken::where('parent_id', $id)
                ->orderBy('leave_date')
                ->get();
            
            return response()->json([
                'success'         => true,
                'leave_request'   => $leaveRequest,
                'days_taken'      => $daysTaken,
                'is_partial_day'  => $leaveRequest->isPartialDay(),
                'hours_requested' => $leaveRequest->getHoursRequested(),
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {}
            return response()->json(['success' => false, 'error' => 'Failed to load leave details'], 500);
        }
    }
    
    public function adjustLeaveDays(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'day_ids'         => 'required|array|min:1',
                'reason'          => 'required|string',
                'is_partial_day'  => 'required',
                'hours_to_refund' => 'nullable|numeric|min:0',
            ]);

            $leaveRequest = LeaveRequest::findOrFail($id);
            if ($leaveRequest->current_status_id != 5) {
                return response()->json([
                    'success' => false, 
                    'error'   => 'Leave adjustment can only be requested for approved leave requests'
                ], 422);
            }

            $isPartialDay = $validated['is_partial_day'] ?? false;
            $hoursToRefund = $validated['hours_to_refund'] ?? 0;
            
            // Check if there's already a pending adjustment
            $existingPending = \App\Models\LeaveAdjustment::where('leave_request_id', $id)
                ->whereIn('current_status_id', [1, 24])
                ->exists();
            
            if ($existingPending) {
                return response()->json([
                    'success' => false, 
                    'error'   => 'There is already a pending adjustment for this leave request'
                ], 422);
            }
            
            // Calculate days to refund
            $daysToRefund = ($isPartialDay == 'true' || $isPartialDay === true) ? round($hoursToRefund / 8, 2) : (float) \App\Models\LeaveRequestDaysTaken::where('parent_id', $id)->whereIn('id', $validated['day_ids'])->sum('day_fraction');
            
            // Create adjustment request (pending approval)
            $adjustment = \App\Models\LeaveAdjustment::create([
                'leave_request_id'  => $id,
                'days_refunded'     => $daysToRefund,
                'reason'            => $validated['reason'],
                'requested_by'      => Auth::id(),
                'requested_at'      => now(),
                'current_status_id' => 1, // Pending
                'day_ids'           => $validated['day_ids'],
            ]);
            
            return response()->json([
                'success'        => true,
                'message'        => 'Leave adjustment request submitted for approval',
                'adjustment_id'  => $adjustment->id,
                'days_to_refund' => $daysToRefund,
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to adjust leave days'], 500);
        }
    }
    
    public function leaveAdjustmentHistory($id)
    {
        try {
            $history = \App\Models\LeaveAdjustment::with(['requestedBy', 'approvedBy', 'confirmedBy'])
                ->where('leave_request_id', $id)
                ->orderBy('requested_at', 'desc')
                ->get()
                ->map(function($adj) {
                    return [
                        'id'               => $adj->id,
                        'days_refunded'    => $adj->days_refunded,
                        'reason'           => $adj->reason,
                        'requested_by'     => $adj->requestedBy,
                        'requested_at'     => $adj->requested_at,
                        'approved_by'      => $adj->approvedBy,
                        'approved_at'      => $adj->approved_at,
                        'confirmed_by'     => $adj->confirmedBy,
                        'confirmed_at'     => $adj->confirmed_at,
                        'current_status_id'=> $adj->current_status_id,
                        'rejection_reason' => $adj->rejection_reason,
                    ];
                });
            
            return response()->json(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch leave history'], 500);
        }
    }
}

//     public function leaveAdjustments()
//     {
//         return view('leave_management.leave_adjustment');
//     }
    
//     public function leaveAdjustmentsList()
//     {
//         try {
//             $requests = LeaveRequest::with(['employee', 'position', 'leaveType'])
//                 ->whereIn('current_status_id', [5]) // Approved or Completed
//                 ->get()
//                 ->map(function($request) {
//                     // Count days taken and refunded (only approved adjustments)
//                     $daysTaken = \App\Models\LeaveRequestDaysTaken::where('parent_id', $request->id)
//                         ->where('current_status_id', 5)
//                         ->count();
                    
//                     $daysRefunded = \App\Models\LeaveRequestDaysTaken::where('parent_id', $request->id)
//                         ->where('current_status_id', 41) // Refunded status
//                         ->count();
                    
//                     // Count adjustment records (including pending)
//                     $adjustmentCount = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)->count();

//                     $leaveAdjustmentCurrentStatus = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)
//                         ->orderBy('requested_at', 'desc')
//                         ->first();
                    
//                     // Check for pending adjustments
//                     $hasPendingAdjustment = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)
//                         ->whereIn('current_status_id', [1, 24]) // Pending or Partially Approved
//                         ->exists();
                    
//                     return [
//                         'id' => $request->id,
//                         'employee' => $request->employee,
//                         'position' => $request->position,
//                         'leave_type' => $request->leaveType,
//                         'start_date' => $request->start_date,
//                         'end_date' => $request->end_date,
//                         'days_taken_count' => $daysTaken,
//                         'days_refunded_count' => $daysRefunded,
//                         'current_status_id' => $request->current_status_id,
//                         'leave_adjustment_current_status_id' => $leaveAdjustmentCurrentStatus->current_status_id ?? null,
//                         'adjustment_count' => $adjustmentCount,
//                         'has_pending_adjustment' => $hasPendingAdjustment,
//                         'is_partial_day' => $request->isPartialDay(),
//                         'hours_requested' => $request->getHoursRequested(),
//                         'can_update' => Auth::user()->isAbleTo('update_leave_adjustments'),
//                         'can_view' => Auth::user()->isAbleTo('read_leave_adjustments'),
//                     ];
//                 });
            
//             return response()->json(['success' => true, 'leave_requests' => $requests]);
//         } catch (\Exception $e) {
//             try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {}
//             return response()->json(['success' => false, 'error' => 'Failed to load leave adjustments'], 500);
//         }
//     }
    
//     public function leaveAdjustmentDetails($id)
//     {
//         try {
//             $leaveRequest = LeaveRequest::with(['employee', 'position', 'leaveType'])->findOrFail($id);
            
//             $daysTaken = \App\Models\LeaveRequestDaysTaken::where('parent_id', $id)
//                 ->orderBy('leave_date')
//                 ->get();
            
//             return response()->json([
//                 'success' => true,
//                 'leave_request' => $leaveRequest,
//                 'days_taken' => $daysTaken,
//                 'is_partial_day' => $leaveRequest->isPartialDay(),
//                 'hours_requested' => $leaveRequest->getHoursRequested(),
//             ]);
//         } catch (\Exception $e) {
//             try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {}
//             return response()->json(['success' => false, 'error' => 'Failed to load leave details'], 500);
//         }
//     }
    
//     public function adjustLeaveDays(Request $request, $id)
//     {
//         try {
//             $validated = $request->validate([
//                 'day_ids' => 'required|array|min:1',
//                 'reason' => 'required|string',
//                 'is_partial_day' => 'required',
//                 'hours_to_refund' => 'nullable|numeric|min:0',
//             ]);

//             Log::info('Leave Request ID for Adjustment: ' . $id);
//             $leaveRequest = LeaveRequest::findOrFail($id);
//             if($leaveRequest->current_status_id != 5) {
//                 return response()->json([
//                     'success' => false, 
//                     'error' => 'Leave adjustment can only be requested for approved leave requests'
//                 ], 422);
//             }

//             $isPartialDay = $validated['is_partial_day'] ?? false;
//             $hoursToRefund = $validated['hours_to_refund'] ?? 0;
//             Log::info('Leave Adjustment Request: is_partial_day=' . ($isPartialDay ? 'true' : 'false') . ', hours_to_refund=' . $hoursToRefund);
            
//             // Check if there's already a pending adjustment
//             $existingPending = \App\Models\LeaveAdjustment::where('leave_request_id', $id)
//                 ->whereIn('current_status_id', [1, 24]) // Pending or Partially Approved
//                 ->exists();
            
//             if ($existingPending) {
//                 return response()->json([
//                     'success' => false, 
//                     'error' => 'There is already a pending adjustment for this leave request'
//                 ], 422);
//             }
            
//             // Calculate days to refund
//             $daysToRefund = ($isPartialDay == 'true' || $isPartialDay === true) ? round($hoursToRefund / 8, 2) : count($validated['day_ids']);
            
//             // Create adjustment request (pending approval)
//             $adjustment = \App\Models\LeaveAdjustment::create([
//                 'leave_request_id' => $id,
//                 'days_refunded' => $daysToRefund,
//                 'reason' => $validated['reason'],
//                 'requested_by' => Auth::id(),
//                 'requested_at' => now(),
//                 'current_status_id' => 1, // Pending
//                 'day_ids' => $validated['day_ids'],
//             ]);
            
//             return response()->json([
//                 'success' => true,
//                 'message' => 'Leave adjustment request submitted for approval',
//                 'adjustment_id' => $adjustment->id,
//                 'days_to_refund' => $daysToRefund,
//             ]);
//         } catch (\Exception $e) {
//             try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
//                 Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
//             }
//             return response()->json(['success' => false, 'error' => 'Failed to fetch leave types'], 500);
//         }
//     }
    
//     public function leaveAdjustmentHistory($id)
//     {
//         try {
//             $history = \App\Models\LeaveAdjustment::with(['requestedBy', 'approvedBy', 'confirmedBy'])
//                 ->where('leave_request_id', $id)
//                 ->orderBy('requested_at', 'desc')
//                 ->get()
//                 ->map(function($adj) {
//                     return [
//                         'id' => $adj->id,
//                         'days_refunded' => $adj->days_refunded,
//                         'reason' => $adj->reason,
//                         'requested_by' => $adj->requestedBy,
//                         'requested_at' => $adj->requested_at,
//                         'approved_by' => $adj->approvedBy,
//                         'approved_at' => $adj->approved_at,
//                         'confirmed_by' => $adj->confirmedBy,
//                         'confirmed_at' => $adj->confirmed_at,
//                         'current_status_id' => $adj->current_status_id,
//                         'rejection_reason' => $adj->rejection_reason,
//                     ];
//                 });
            
//             return response()->json(['success' => true, 'history' => $history]);
//          } catch (\Exception $e) {
//             try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
//                 Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
//             }
//             return response()->json(['success' => false, 'error' => 'Failed to fetch leave types'], 500);
//         }
//     }
   
// }
