<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\WorkDay;
use Carbon\Carbon;
use App\Models\PublicHoliday;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;
use App\Models\ConfirmationLog;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use App\Models\LeaveCalendarPlan;
use App\Services\ErrorDispatcher;
use App\Services\Leave\LeaveApprovalRouter;
use App\Services\Leave\LeaveApprovalService;
use App\Services\Leave\LeaveApplicationNotifications;
use App\Services\Leave\LeaveCalendarService;
use App\Services\Leave\LeavePlanService;
use App\Services\Leave\LeaveTierService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
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

        $totalDays = 0;
        $slotsToCreate = [];

        foreach ($validated['slots'] as $slot) {
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

        DB::transaction(function () use ($plan, $slotsToCreate) {
            $plan->slots()->delete();
            foreach ($slotsToCreate as $slotData) {
                $plan->slots()->create($slotData);
            }
        });

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
        $applications = $this->router->pendingVisibleFor(Auth::user());
        if ($applications->isNotEmpty()) {
            $applications->load(['employee.department', 'position', 'handoverEmployee:id,full_names']);
        }

        return response()->json(['leave_applications' => $applications->map(function ($app) {
            $canAct = $this->router->canAct(Auth::user(), $app) === null;
            $waitingOn = $this->router->decisionStage(Auth::user(), $app);
            return [
                'id' => $app->id,
                'encrypted_leave_application_id' => Crypt::encrypt($app->id),
                'employee' => ['id' => $app->staff_id, 'full_names' => $app->employee?->full_names],
                'department' => $app->employee?->department?->department_name ?? '-',
                'position' => $app->position ?? $app->employee?->position,
                'leave_type' => $app->leaveType,
                'start_date' => $app->start_date?->format('Y-m-d'),
                'end_date' => $app->end_date?->format('Y-m-d'),
                'requested_days' => $app->requested_days ?? $app->days ?? 0,
                'current_status_id' => (int) $app->current_status_id,
                'stage_label' => $this->router->stageLabel($app),
                'handover_employee_name' => $app->handoverEmployee?->full_names,
                'handover_notes' => $app->handover_notes,
                'handover_acknowledged_at' => $app->handover_acknowledged_at?->toIso8601String(),
                'handover_review_url' => route('leave-management.leave-applications.by-staff', Crypt::encrypt($app->id)),
                'waiting_description' => $this->router->waitingDescription($app),
                'can_approve_hod' => $canAct && $waitingOn === 'supervisor',
                'can_approve_hr' => $canAct && $waitingOn === 'hr',
                'can_reject' => $canAct,
            ];
        })->values()]);
    }

    public function updateApplicationStatus(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'status' => 'required|integer|in:24,5,41',
        ]);
        $application = LeaveRequest::findOrFail($validated['id']);
        $nextStatus = $this->router->decisionStage(Auth::user(), $application) === 'supervisor' ? 24 : 5;
        if ((int) $validated['status'] !== 41 && (int) $validated['status'] !== $nextStatus) {
            return response()->json(['success' => false, 'error' => 'The approval stage has changed. Please reload.'], 422);
        }

        return $this->applicationDecision($request, (int) $validated['status'] === 41);
    }

    private function handoverRules(Request $request): array
    {
        return [
            'handover_staff_id' => ['required', 'integer', 'different:staff_id',
                Rule::exists('sys_staff', 'id')->where('current_status_id', 1),
                function ($attribute, $value, $fail) use ($request) {
                    $applicant = Staff::find((int) $request->input('staff_id'));
                    if (!$applicant || !app(LeaveApplicationNotifications::class)->handoverStaff($applicant)->contains('id', (int) $value)) {
                        $fail('Choose an active colleague in the applicant\'s department with a linked, activated system account and an assigned role.');
                    }
                }],
            'handover_notes' => ['required', 'string', 'max:5000'],
            'handover_documents' => ['nullable', 'array', 'max:5'],
            'handover_documents.*' => ['required', 'file', 'max:5120'],
        ];
    }

    private function applicationDecision(Request $request, bool $reject = false)
    {
        $validated = $request->validate(['id' => 'required|integer']);
        $application = LeaveRequest::findOrFail($validated['id']);
        $result = $reject
            ? $this->approvals->reject($application, Auth::user())
            : $this->approvals->approve($application, Auth::user());

        return response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 422));
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
        return view('leave_management.leave_applications', ['handoverStaff' => app(LeaveApplicationNotifications::class)->handoverStaff()]);
    }

    public function handoverDetails(int $id)
    {
        $application = LeaveRequest::with(['employee:id,full_names', 'handoverEmployee:id,full_names'])->findOrFail($id);
        $user = Auth::user();
        abort_unless(app(\App\Services\Leave\LeaveHandoverService::class)->isRecipient($user, $application), 403,
            'This handover is no longer assigned to you or you do not have access.');

        // Give the colleague the handover only, not balances, salary/allowances, leave category, or approval actions.
        return view('self-service.leave-handover', ['handover' => [
            'employee_name' => $application->employee?->full_names ?? 'Employee',
            'recipient_name' => $application->handoverEmployee?->full_names ?? 'You',
            'start_date' => $application->start_date?->format('d M Y'),
            'end_date' => $application->end_date?->format('d M Y'),
            'status' => (int) $application->current_status_id,
            'notes' => $application->handover_notes,
            'id' => $application->id,
            'revision' => app(\App\Services\Leave\LeaveHandoverService::class)->revision($application),
            'attachments' => app(\App\Services\Leave\LeaveHandoverService::class)->attachments($application),
            'acknowledged_at' => $application->handover_acknowledged_at?->format('d M Y H:i'),
            'acknowledgement_note' => $application->handover_acknowledgement_note,
        ]]);
    }

    public function leaveApplicationsList()
    {
        try {
            $requests = $this->router->visibleFor(Auth::user())
                ->with('createdBy', 'employee', 'leaveType', 'position', 'handoverEmployee:id,full_names')
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($request) {
                    $encrypted_leave_application_id = Crypt::encrypt($request->id);
                    
                    return [
                        'id'                             => $request->id,
                        'staff_id'                       => $request->staff_id,
                        'handover_staff_id'              => $request->handover_staff_id,
                        'handover_notes'                 => $request->handover_notes,
                        'handover_files' => app(\App\Services\Leave\LeaveHandoverService::class)->attachments($request),
                        'handover_employee_name'         => $request->handoverEmployee?->full_names,
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
                        'stage_label'                    => $this->router->stageLabel($request),
                        'allowance_type_id'              => $request->allowance_type_id,
                        'created_by'                     => $request->createdBy ? [
                            'id'   => $request->createdBy->id,
                            'name' => $request->createdBy->name,
                        ] : null,
                        'employee'                       => $request->employee ? [
                            'id'   => $request->employee->id,
                            'name' => $request->employee->full_names,
                        ] : null,
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
                        'can_view'                       => true,
                    ];
                });
    
            return response()->json(['leave_applications' => $requests]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load leave applications'], 500);
        }
    }

    /** Short notification URLs still use the existing owner/supervisor/HR access checks. */
    public function notificationApplication(int $id)
    {
        $application = LeaveRequest::findOrFail($id);
        return $this->leaveApplicationsByStaff(Crypt::encrypt($application->id));
    }

    public function leaveApplicationsByStaff($encryptedLeaveApplicationId)
    {
        try {
            $leaveApplicationId = Crypt::decrypt($encryptedLeaveApplicationId);

            $requests = LeaveRequest::with('createdBy', 'employee', 'leaveType', 'position', 'handoverEmployee:id,full_names')
                ->where('id', $leaveApplicationId)
                ->orderBy('date_time', 'desc')
                ->get();

            foreach ($requests as $request) {
                if (!$this->canManageLeaveOf((int) $request->staff_id, 'read_leave_applications') && !$this->router->canReview(Auth::user(), $request)) {
                    return response('You are not allowed to view this leave application.', 403);
                }
            }

            return view('leave_management.leave_application_details', [
                'leave_applications' => $requests,
                'approvalRouter' => $this->router,
                'actionableIds' => $requests->filter(fn ($application) => $this->router->canAct(Auth::user(), $application) === null)->modelKeys(),
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load leave applications for the specified staff'], 500);
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
            
            $validated = $request->validate(array_merge($rules, $this->handoverRules($request)));

            if (!$this->canManageLeaveOf((int) $validated['staff_id'], 'create_leave_applications')) {
                return response()->json(['success' => false, 'error' => 'You can only apply for leave for yourself.'], 403);
            }

            $staff = Staff::find($validated['staff_id']);
            if (!$staff) {
                return response()->json(['success' => false, 'error' => 'Staff member not found.'], 422);
            }
            
            $requestModel = new LeaveRequest();
            $requestModel->staff_id    = $validated['staff_id'];
            foreach (['handover_staff_id', 'handover_notes'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $requestModel->{$field} = $validated[$field];
                }
            }
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
            $routing = app(\App\Services\Leave\LeaveHandoverService::class)->saveAndSubmit(
                $requestModel, $request, fn ($leave) => $this->approvals->submit($leave, (int) Auth::id())
            );

            return response()->json(['success' => true, 'leave_application' => $requestModel->fresh(), 'routing' => $routing, 'warning' => $balanceWarning]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('leaveApplicationsStore failed', ['exception' => $e]);
            return response()->json(['success' => false, 'error' => 'Failed to create leave application'], 500);
        }
    }


    public function approveApplication(Request $request)
    {
        return $this->applicationDecision($request);
    }

    public function rejectApplication(Request $request)
    {
        return $this->applicationDecision($request, true);
    }

    public function leaveApplicationsUpdate(Request $request, $id)
    {
        try {
            $requestModel = LeaveRequest::findOrFail($id);
            $request->mergeIfMissing([
                'handover_staff_id' => $requestModel->handover_staff_id,
                'handover_notes' => $requestModel->handover_notes,
            ]);
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
            
            $validated = $request->validate(array_merge($rules, $this->handoverRules($request)));

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
            foreach (['handover_staff_id', 'handover_notes'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $requestModel->{$field} = $validated[$field];
                }
            }
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
            $routing = app(\App\Services\Leave\LeaveHandoverService::class)->saveAndSubmit(
                $requestModel, $request, fn ($leave) => $this->approvals->submit($leave, (int) Auth::id())
            );

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
            $activeRequestStartDates = LeaveRequest::where('staff_id', $staffId)
                ->whereIn('current_status_id', [1, 5, 24])
                ->pluck('start_date')
                ->map(fn($date) => \Carbon\Carbon::parse($date)->format('Y-m-d'))
                ->toArray();

            $formattedSlots = $slots->map(function ($slot) use ($activeRequestStartDates) {
                $start = \Carbon\Carbon::parse($slot->start_date)->format('Y-m-d');
                $end   = \Carbon\Carbon::parse($slot->end_date)->format('Y-m-d');
                $displayStart = \Carbon\Carbon::parse($slot->start_date)->format('d M Y');
                $displayEnd   = \Carbon\Carbon::parse($slot->end_date)->format('d M Y');

                // Check if this slot has already been applied for
                $isBooked = in_array($start, $activeRequestStartDates);
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
