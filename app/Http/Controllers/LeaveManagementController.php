<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\WorkDay;
use App\Models\PublicHoliday;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use App\Models\LeaveCalendarPlan;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;


class LeaveManagementController extends Controller
{

    public function leaveCalendarPlan()
    {
        return view('leave_management.leave_calendar_plan');
    }

    public function leaveCalendarPlanList()
    {
        $currentYear = date('Y');
        
        // Get all plans for the current year
        $plans = LeaveCalendarPlan::with('createdBy', 'employee', 'leaveType')
            ->whereYear('start_date', $currentYear)
            ->orderBy('date_time', 'desc')
            ->get();
        
        // Group by employee
        $groupedByEmployee = $plans->groupBy('employee_id')->map(function ($employeePlans, $employeeId) {
            $firstPlan = $employeePlans->first();
            
            // Map leave types for this employee
            $leaveTypes = $employeePlans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'leave_type_id' => $plan->leave_type_id,
                    'leave_type' => $plan->leaveType ? [
                        'id' => $plan->leaveType->id,
                        'type_name' => $plan->leaveType->type_name,
                    ] : null,
                    'start_date' => $plan->start_date ? substr($plan->start_date, 0, 10) : null,
                    'end_date' => $plan->end_date ? substr($plan->end_date, 0, 10) : null,
                    'entitled_days' => $plan->entitled_days,
                    'balance' => $plan->balance,
                    'current_status_id' => $plan->current_status_id,
                ];
            })->values();
            
            return [
                'employee_id' => $employeeId,
                'employee' => $firstPlan->employee ? [
                    'id' => $firstPlan->employee->id,
                    'name' => $firstPlan->employee->full_names,
                ] : null,
                'leave_types' => $leaveTypes,
                'total_leave_types' => $leaveTypes->count(),
                'date_time' => $firstPlan->date_time,
                'created_by' => $firstPlan->createdBy ? [
                    'id' => $firstPlan->createdBy->id,
                    'name' => $firstPlan->createdBy->name,
                ] : null,
                'can_update' => Auth::user()->isAbleTo('update_leave_calendar_plans'),
                'can_delete' => Auth::user()->isAbleTo('delete_leave_calendar_plans'),
            ];
        })->values();
    
        return response()->json(['leave_calendar_plans' => $groupedByEmployee]);
    }

    // private function calculateEndDate($start_date, $requested_days, $leave_type_id, $available_days)
    // {
    //     $begin_date = $start_date;
    //     $non_working_days = 0;

    //     $details = array('balance'=>0,'end_date'=>$start_date);
        
    //     if ($leave_type_id != 5) {
    //         for ($i = 1; $i < $requested_days; $i++) {

    //             $day = date('N',strtotime($begin_date));

    //             $work_day = WorkDay::where('week_day', $day)->first();
                
    //             if($work_day){
                    
    //                 if($work_day->work_type_id == 3){//Non working day
    //                     ++$non_working_days;						
    //                 }
                    
    //             }
    //             $begin_date = date('Y-m-d', strtotime($begin_date. " + 1 days"));
                
    //             $details['balance'] = $available_days - $requested_days;
               
    //         }
    //     }

    //     $requested_days += $non_working_days;
    //     $requested_days -=1;//Deduct the first day
    //     $details['end_date'] = date('Y-m-d', strtotime($start_date. " + $requested_days days"));
        
    //     return $details;
       
    // }

    private function calculateEndDate($start_date, $requested_days, $leave_type_id, $available_days)
    {
        $begin_date = $start_date;
        $skip_days = 0; // Total days to skip (non-working + holidays)
    
        $details = array('balance' => $available_days - $requested_days, 'end_date' => $start_date);
        
        Log::info("=== calculateEndDate START ===");
        Log::info("Start Date: {$start_date}");
        Log::info("Requested Days: {$requested_days}");
        Log::info("Leave Type ID: {$leave_type_id}");
        Log::info("Available Days: {$available_days}");
        
        // Check if this is maternity leave and whether to exclude holidays
        $isMaternityLeave = ($leave_type_id == 3);
        $maternityExcludeHolidays = env('MATERNITY_EXCLUDE_HOLIDAYS', false);
        
        Log::info("Is Maternity Leave: " . ($isMaternityLeave ? 'Yes' : 'No'));
        if ($isMaternityLeave) {
            Log::info("Maternity Exclude Holidays Setting: " . ($maternityExcludeHolidays ? 'Enabled' : 'Disabled'));
        }
        
        // Apply holiday/non-working day logic if:
        // 1. NOT maternity leave, OR
        // 2. IS maternity leave AND exclude holidays is enabled
        if (!$isMaternityLeave || ($isMaternityLeave && $maternityExcludeHolidays)) {
            // Get all active public holidays once
            $publicHolidays = PublicHoliday::where('current_status_id', 5)->get();
            Log::info("Public Holidays Found: " . $publicHolidays->count());
            foreach ($publicHolidays as $holiday) {
                Log::info("  - {$holiday->title}: " . date('m-d', strtotime($holiday->start_date)) . " to " . date('m-d', strtotime($holiday->end_date)));
            }
            
            // We need to check (requested_days - 1) days ahead of start_date
            // Because start_date is day 1
            $totalIterations = $requested_days - 1 + $skip_days;
            Log::info("Initial loop target: {$totalIterations} iterations (requested_days - 1 + skip_days)");
            
            for ($i = 0; $i < ($requested_days - 1 + $skip_days); $i++) {
                // Move to next day
                $begin_date = date('Y-m-d', strtotime($start_date . " + " . ($i + 1) . " days"));
                
                $day = date('N', strtotime($begin_date)); // 1=Monday, 7=Sunday
                $dayName = date('l', strtotime($begin_date));
                
                Log::info("--- Iteration {$i}: Checking {$begin_date} ({$dayName}, day {$day}) ---");
    
                // Check if it's a non-working day (like Sunday)
                $work_day = WorkDay::where('week_day', $day)->first();
                
                if ($work_day && $work_day->work_type_id == 3) { // Non working day
                    ++$skip_days;
                    Log::info("  ✗ NON-WORKING DAY found! Skip days count: {$skip_days}");
                    continue;
                } else {
                    Log::info("  ✓ Working day");
                }
                
                // Check if current date is a public holiday (compare only month and day, ignore year)
                $currentMonth = (int) date('m', strtotime($begin_date));
                $currentDay = (int) date('d', strtotime($begin_date));
                
                $isPublicHoliday = $publicHolidays->first(function($holiday) use ($currentMonth, $currentDay) {
                    $holidayStartMonth = (int) date('m', strtotime($holiday->start_date));
                    $holidayStartDay = (int) date('d', strtotime($holiday->start_date));
                    $holidayEndMonth = (int) date('m', strtotime($holiday->end_date));
                    $holidayEndDay = (int) date('d', strtotime($holiday->end_date));
                    
                    // Same month holiday (e.g., Dec 25 to Dec 25, or Dec 25 to Dec 26)
                    if ($holidayStartMonth == $holidayEndMonth) {
                        return $currentMonth == $holidayStartMonth && 
                               $currentDay >= $holidayStartDay && 
                               $currentDay <= $holidayEndDay;
                    }
                    
                    // Multi-month holiday (e.g., Dec 31 to Jan 2)
                    // Check if current date falls in start month from start day onwards
                    if ($currentMonth == $holidayStartMonth && $currentDay >= $holidayStartDay) {
                        return true;
                    }
                    
                    // Check if current date falls in end month up to end day
                    if ($currentMonth == $holidayEndMonth && $currentDay <= $holidayEndDay) {
                        return true;
                    }
                    
                    // Check if current month is between start and end months
                    if ($holidayStartMonth < $holidayEndMonth) {
                        // Normal case: e.g., Nov to Dec
                        return $currentMonth > $holidayStartMonth && $currentMonth < $holidayEndMonth;
                    } else {
                        // Year-spanning case: e.g., Dec to Jan
                        return $currentMonth > $holidayStartMonth || $currentMonth < $holidayEndMonth;
                    }
                });
                
                if ($isPublicHoliday) {
                    ++$skip_days;
                    Log::info("  ✗ PUBLIC HOLIDAY found: {$isPublicHoliday->title}! Skip days count: {$skip_days}");
                } else {
                    Log::info("  ✓ Not a public holiday");
                }
            }
            
            Log::info("Loop completed. Total skip_days: {$skip_days}");
        } else {
            Log::info("Maternity leave (ID 3) with exclude holidays DISABLED - counting all calendar days");
        }
    
        // Final calculation: start + (requested_days - 1) + skip_days
        $daysToAdd = $requested_days - 1 + $skip_days;
        $details['end_date'] = date('Y-m-d', strtotime($start_date . " + " . $daysToAdd . " days"));
        
        Log::info("Final Calculation:");
        Log::info("  Start Date: {$start_date}");
        Log::info("  Requested Days: {$requested_days}");
        Log::info("  Skip Days: {$skip_days}");
        Log::info("  Days to Add: {$daysToAdd} (requested_days - 1 + skip_days = {$requested_days} - 1 + {$skip_days})");
        Log::info("  End Date: {$details['end_date']}");
        Log::info("=== calculateEndDate END ===\n");
        
        return $details;
    }

    private function  getLeaveUsedDays($year, $leave_type_id, $employee_id)
    {
        $used_days = LeaveRequest::whereYear('start_date', $year)
            ->where('leave_type_id', $leave_type_id)
            ->where('staff_id', $employee_id)
            ->whereIn('current_status_id', [5, 49]) // Approved or Completed
            ->sum('days');

        return $used_days;
    }

    public function leaveCalendarPlanStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|integer',
                'leave_type_id' => 'required|integer',
                'start_date' => 'required|date',
                'entitled_days' => 'required|integer|min:0',
            ]);

            $result = $this->calculateEndDate($validated['start_date'], $validated['entitled_days'], $validated['leave_type_id'], $validated['entitled_days']);
            $end_date = $result['end_date'];

            $used_days = $this->getLeaveUsedDays(date('Y', strtotime($validated['start_date'])), $validated['leave_type_id'], $validated['employee_id']);

            $balance = $validated['entitled_days'] - $used_days;

            $leave_calendear = LeaveCalendarPlan::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('start_date', $validated['start_date'])
                ->first();
            
            if($leave_calendear){
                return response()->json(['success' => false, 'error' => 'Leave calendar plan already exists for this employee, leave type and start date'], 409);
            }
    
            $plan = new LeaveCalendarPlan();
            $plan->employee_id = $validated['employee_id'];
            $plan->leave_type_id = $validated['leave_type_id'];
            $plan->start_date = $validated['start_date'];
            $plan->end_date = $end_date;
            $plan->entitled_days = $validated['entitled_days'];
            $plan->balance = $balance;
            $plan->current_status_id = 1;
            $plan->created_by = Auth::id();
            $plan->date_time = now();
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
    
    public function leaveCalendarPlanUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|integer',
                'leave_type_id' => 'required|integer',
                'start_date' => 'required|date',
                'entitled_days' => 'required|integer|min:0',
            ]);

            $result = $this->calculateEndDate($validated['start_date'], $validated['entitled_days'], $validated['leave_type_id'], $validated['entitled_days']);
            $end_date = $result['end_date'];
            
            $used_days = $this->getLeaveUsedDays(date('Y', strtotime($validated['start_date'])), $validated['leave_type_id'], $validated['employee_id']);

            $balance = $validated['entitled_days'] - $used_days;
    
            $plan = LeaveCalendarPlan::findOrFail($id);
            $plan->employee_id = $validated['employee_id'];
            $plan->leave_type_id = $validated['leave_type_id'];
            $plan->start_date = $validated['start_date'];
            $plan->end_date = $end_date;
            $plan->entitled_days = $validated['entitled_days'];
            $plan->balance = $balance;
            $plan->current_status_id = 1;
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
            // Get the leave type to get base entitled days
            $leaveType = LeaveType::find($leaveTypeId);
            
            if (!$leaveType) {
                return response()->json(['success' => false, 'error' => 'Leave type not found'], 404);
            }
            
            // Get the current year
            $currentYear = date('Y');
            
            // Calculate used days from LeaveRequestDaysTaken to account for adjustments
            // Count only approved/taken days (status 5) excluding refunded days (status 41)
            $usedDays = \App\Models\LeaveRequestDaysTaken::whereHas('leaveRequest', function($query) use ($staffId, $leaveTypeId, $currentYear) {
                $query->where('staff_id', $staffId)
                    ->where('leave_type_id', $leaveTypeId)
                    ->whereYear('start_date', $currentYear)
                    ->whereIn('current_status_id', [5, 49]); // 5 = Approved/Taken, 49 = Completed
            })
            ->where('current_status_id', 5) // Only count approved days
            ->count();
            
            // Calculate available days
            $entitledDays = $leaveType->entitled_days;
            $availableDays = $entitledDays - $usedDays;
            
            return response()->json([
                'success' => true, 
                'entitled_days' => $entitledDays,
                'used_days' => $usedDays,
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
            
            // Extract day and month from the start date
            $inputDay = (int) date('d', strtotime($startDate));
            $inputMonth = (int) date('m', strtotime($startDate));
            
            // Check if the date is a public holiday (matching day and month only)
            $isPublicHoliday = PublicHoliday::where('current_status_id', 5) // Active/approved holidays
                ->get()
                ->first(function($holiday) use ($inputDay, $inputMonth) {
                    // Extract day and month from holiday start and end dates
                    $holidayStartDay = (int) date('d', strtotime($holiday->start_date));
                    $holidayStartMonth = (int) date('m', strtotime($holiday->start_date));
                    $holidayEndDay = (int) date('d', strtotime($holiday->end_date));
                    $holidayEndMonth = (int) date('m', strtotime($holiday->end_date));
                    
                    // Same month holiday (e.g., May 1 to May 1, or May 1 to May 3)
                    if ($holidayStartMonth == $holidayEndMonth) {
                        return $inputMonth == $holidayStartMonth && 
                               $inputDay >= $holidayStartDay && 
                               $inputDay <= $holidayEndDay;
                    }
                    
                    // Multi-month holiday (e.g., Dec 31 to Jan 2)
                    // Check if input falls in start month from start day onwards
                    if ($inputMonth == $holidayStartMonth && $inputDay >= $holidayStartDay) {
                        return true;
                    }
                    
                    // Check if input falls in end month up to end day
                    if ($inputMonth == $holidayEndMonth && $inputDay <= $holidayEndDay) {
                        return true;
                    }
                    
                    // Check if input month is between start and end months
                    if ($holidayStartMonth < $holidayEndMonth) {
                        // Normal case: e.g., Nov to Dec
                        return $inputMonth > $holidayStartMonth && $inputMonth < $holidayEndMonth;
                    } else {
                        // Year-spanning case: e.g., Dec to Jan
                        return $inputMonth > $holidayStartMonth || $inputMonth < $holidayEndMonth;
                    }
                });
            
            if ($isPublicHoliday) {
                return response()->json([
                    'success' => false, 
                    'is_holiday' => true,
                    'holiday_title' => $isPublicHoliday->title,
                    'error' => 'The selected date (' . date('d M Y', strtotime($startDate)) . ') is a public holiday: ' . $isPublicHoliday->title
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

    public function generateAnnualPlans(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'required|integer|min:2020|max:2100',
                'override' => 'boolean',
                'leave_type_ids' => 'nullable|array',
                'leave_type_ids.*' => 'integer'
            ]);
            
            $year = $validated['year'];
            $override = $validated['override'] ?? false;
            $leaveTypeIds = $validated['leave_type_ids'] ?? null;
            
            // Quick validation checks
            $activeStaffCount = Staff::where('current_status_id', 1)->count();
            
            if ($activeStaffCount === 0) {
                return response()->json(['success' => false, 'error' => 'No active staff found'], 400);
            }
            
            // Get leave types count based on selection
            if ($leaveTypeIds && count($leaveTypeIds) > 0) {
                $leaveTypesCount = LeaveType::whereIn('id', $leaveTypeIds)->count();
                if ($leaveTypesCount === 0) {
                    return response()->json(['success' => false, 'error' => 'No valid leave types selected'], 400);
                }
            } else {
                $leaveTypesCount = LeaveType::count();
                if ($leaveTypesCount === 0) {
                    return response()->json(['success' => false, 'error' => 'No leave types found in the system'], 400);
                }
            }

            // Check if plans already exist for this year
            $existingPlansQuery = LeaveCalendarPlan::whereYear('start_date', $year);
            
            if ($leaveTypeIds && count($leaveTypeIds) > 0) {
                $existingPlansQuery->whereIn('leave_type_id', $leaveTypeIds);
            }
            
            $existingPlansCount = $existingPlansQuery->count();
            
            if ($existingPlansCount > 0 && !$override) {
                return response()->json([
                    'success' => false, 
                    'error' => "Found {$existingPlansCount} existing leave plan(s) for year {$year}. Please check 'Override existing plans' if you want to regenerate them."
                ], 400);
            }
            
            // Dispatch the job to run in background
            \App\Jobs\GenerateAnnualLeaveCalendarPlans::dispatch($year, $leaveTypeIds, $override, Auth::id());
            
            $estimatedPlans = $activeStaffCount * $leaveTypesCount;
            
            $message = "Annual leave plan generation started in the background. ";
            $message .= "Approximately {$estimatedPlans} plans will be created for {$activeStaffCount} staff member(s) ";
            $message .= "across {$leaveTypesCount} leave type(s) for year {$year}.";
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {}
            Log::error('Failed to generate annual plans: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to start annual plan generation'], 500);
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
        return view('leave_management.leave_applications');
    }

    public function leaveApplicationsList()
    {
        try {
            $requests = LeaveRequest::with('createdBy', 'employee', 'leaveType', 'position')
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($request) {
                    $encrypted_leave_application_id = Crypt::encrypt($request->id);
                    
                    return [
                        'id' => $request->id,
                        'staff_id' => $request->staff_id,
                        'encrypted_leave_application_id' => $encrypted_leave_application_id,
                        'position_id' => $request->position_id,
                        'start_date' => $request->start_date ? $request->start_date->format('Y-m-d H:i') : null,
                        'end_date' => $request->end_date ? $request->end_date->format('Y-m-d H:i') : null,
                        'is_partial_day' => $request->isPartialDay(),
                        'hours_requested' => $request->getHoursRequested(),
                        'days' => $request->days,
                        'display_days' => $request->getDisplayDuration(),
                        'entitled_days' => $request->entitled_days,
                        'days_balance' => $request->days_balance,
                        'used_days' => $request->used_days,
                        'requested_days' => $request->requested_days,
                        'amount' => $request->amount,
                        'current_status_id' => $request->current_status_id,
                        'allowance_type_id' => $request->allowance_type_id,
                        'created_by' => $request->createdBy ? [
                            'id' => $request->createdBy->id,
                            'name' => $request->createdBy->name,
                        ] : null,
                        'employee' => $request->employee ? [
                            'id' => $request->employee->id,
                            'name' => $request->employee->full_names,
                        ] : null,
                        'leave_type' => $request->leaveType ? [
                            'id' => $request->leaveType->id,
                            'type_name' => $request->leaveType->type_name,
                        ] : null,
                        'position' => $request->position ? [
                            'id' => $request->position->id,
                            'position_name' => $request->position->position_name,
                        ] : null,
                        'date_time' => $request->date_time,
                        'can_update' => Auth::user()->isAbleTo('update_leave_applications'),
                        'can_delete' => Auth::user()->isAbleTo('delete_leave_applications'),
                        'can_view' => Auth::user()->isAbleTo('read_leave_applications'),
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

    public function leaveApplicationsByStaff($encryptedLeaveApplicationId)
    {
        try {
            $leaveApplicationId = Crypt::decrypt($encryptedLeaveApplicationId);

            $requests = LeaveRequest::with('createdBy', 'employee', 'leaveType', 'position')
                ->where('id', $leaveApplicationId)
                ->orderBy('date_time', 'desc')
                ->get();


            return view('leave_management.leave_application_details', ['leave_applications' => $requests]);
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
                'staff_id' => 'required|integer',
                'leave_type_id' => 'required|integer',
                'available_days' => 'required|numeric|min:0',
                'allowance_type' => 'nullable|integer|min:0',
                'amount' => 'nullable|numeric|min:0',
                'leave_duration_type' => 'required|in:full_day,partial_day,days_and_hours',
            ];
            
            if ($durationType === 'full_day') {
                $rules['start_date'] = 'required|date';
                $rules['requested_days'] = 'required|numeric|min:0.5';
            } else {
                $rules['start_date'] = 'required|date';
                $rules['end_date'] = 'required|date';
            }
            
            $validated = $request->validate($rules);

            $staff = Staff::find($validated['staff_id']);
            
            $requestModel = new LeaveRequest();
            $requestModel->staff_id = $validated['staff_id'];
            $requestModel->leave_type_id = $validated['leave_type_id'];
            $requestModel->position_id = $staff->position_id;
            
            if ($durationType === 'full_day') {
                // Full days only - use default times
                $result = $this->calculateEndDate(
                    $validated['start_date'], 
                    $validated['requested_days'], 
                    $validated['leave_type_id'], 
                    $validated['available_days']
                );
                
                $requestModel->start_date = $validated['start_date'] . ' 08:00:00';
                $requestModel->end_date = $result['end_date'] . ' 17:00:00';
                $requestModel->days = $validated['requested_days'];
                $requestModel->requested_days = $validated['requested_days'];
                
            } else if ($durationType === 'partial_day') {
                // Hours only (same day) - use actual times from request
                $requestModel->start_date = $validated['start_date'];
                $requestModel->end_date = $validated['end_date'];
                
                // Calculate hours and convert to days
                $start = new \DateTime($validated['start_date']);
                $end = new \DateTime($validated['end_date']);
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $days = round($hours / 8, 2);
                
                $requestModel->days = $days;
                $requestModel->requested_days = $days;
                
            } else if ($durationType === 'days_and_hours') {
                // Days + Hours combined - use actual datetime
                $requestModel->start_date = $validated['start_date'];
                $requestModel->end_date = $validated['end_date'];
                
                // Calculate total hours and convert to days
                $start = new \DateTime($validated['start_date']);
                $end = new \DateTime($validated['end_date']);
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $days = round($hours / 8, 2);
                
                $requestModel->days = $days;
                $requestModel->requested_days = $days;
            }

            // Get leave plan for the current year
            $currentYear = date('Y', strtotime($validated['start_date']));
            $staff_leave_plan = LeaveCalendarPlan::where('employee_id', $validated['staff_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->whereYear('start_date', $currentYear)
                ->where('current_status_id', 5)
                ->first();

            if (!$staff_leave_plan) {
                return response()->json([
                    'success' => false, 
                    'error' => 'No active leave plan found for this employee and leave type for the year ' . $currentYear
                ], 404);
            }

            $requestModel->entitled_days = $staff_leave_plan->entitled_days;
            $requestModel->days_balance = $staff_leave_plan->entitled_days - $requestModel->requested_days;
            $requestModel->used_days = 0;
            $requestModel->amount = $validated['amount'] ?? 0;
            $requestModel->allowance_type_id = $validated['allowance_type'] ?? null;
            $requestModel->current_status_id = 1;
            $requestModel->created_by_id = Auth::id();
            $requestModel->date_time = now();
            $requestModel->save();

            return response()->json(['success' => true, 'leave_application' => $requestModel]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to create leave application'], 500);
        }
    }
    
    public function leaveApplicationsUpdate(Request $request, $id)
    {
        try {
            $durationType = $request->input('leave_duration_type', 'full_day');
            
            $rules = [
                'staff_id' => 'required|integer',
                'leave_type_id' => 'required|integer',
                'available_days' => 'required|numeric|min:0',
                'allowance_type' => 'nullable|integer|min:0',
                'amount' => 'nullable|numeric|min:0',
                'leave_duration_type' => 'required|in:full_day,partial_day,days_and_hours',
            ];
            
            if ($durationType === 'full_day') {
                $rules['start_date'] = 'required|date';
                $rules['requested_days'] = 'required|numeric|min:0.5';
            } else {
                $rules['start_date'] = 'required|date';
                $rules['end_date'] = 'required|date';
            }
            
            $validated = $request->validate($rules);
    
            $staff = Staff::find($validated['staff_id']);
            
            $requestModel = LeaveRequest::findOrFail($id);
            $requestModel->staff_id = $validated['staff_id'];
            $requestModel->leave_type_id = $validated['leave_type_id'];
            $requestModel->position_id = $staff->position_id;
            
            if ($durationType === 'full_day') {
                // Full days only - use default times
                $result = $this->calculateEndDate(
                    $validated['start_date'], 
                    $validated['requested_days'], 
                    $validated['leave_type_id'], 
                    $validated['available_days']
                );
                
                $requestModel->start_date = $validated['start_date'] . ' 08:00:00';
                $requestModel->end_date = $result['end_date'] . ' 17:00:00';
                $requestModel->days = $validated['requested_days'];
                $requestModel->requested_days = $validated['requested_days'];
                
            } else if ($durationType === 'partial_day') {
                // Hours only (same day) - use actual times from request
                $requestModel->start_date = $validated['start_date'];
                $requestModel->end_date = $validated['end_date'];
                
                // Calculate hours and convert to days
                $start = new \DateTime($validated['start_date']);
                $end = new \DateTime($validated['end_date']);
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $days = round($hours / 8, 2);
                
                $requestModel->days = $days;
                $requestModel->requested_days = $days;
                
            } else if ($durationType === 'days_and_hours') {
                // Days + Hours combined - use actual datetime
                $requestModel->start_date = $validated['start_date'];
                $requestModel->end_date = $validated['end_date'];
                
                // Calculate total hours and convert to days
                $start = new \DateTime($validated['start_date']);
                $end = new \DateTime($validated['end_date']);
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $days = round($hours / 8, 2);
                
                $requestModel->days = $days;
                $requestModel->requested_days = $days;
            }
    
            // Get leave plan
            $start_date_only = date('Y-m-d', strtotime($validated['start_date'])) . ' 00:00:00';
            $staff_leave_plan = LeaveCalendarPlan::where('employee_id', $validated['staff_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('start_date', $start_date_only)
                ->where('current_status_id', 5)
                ->first();
    
            if (!$staff_leave_plan) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active leave plan found for this employee and leave type starting on the specified date'
                ], 404);
            }
    
            $requestModel->entitled_days = $staff_leave_plan->entitled_days;
            $requestModel->days_balance = $staff_leave_plan->entitled_days - $requestModel->requested_days;
            $requestModel->used_days = 0;
            $requestModel->amount = $validated['amount'] ?? 0;
            $requestModel->allowance_type_id = $validated['allowance_type'] ?? null;
            $requestModel->current_status_id = 1;
            $requestModel->created_by_id = Auth::id();
            $requestModel->date_time = now();
            $requestModel->save();
    
            return response()->json(['success' => true, 'leave_application' => $requestModel]);
            
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
            $requestModel->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete leave application'], 500);
        }
    }

    public function availableLeaveDays($staffId, $leaveTypeId)
    {
        try {
            $plan = LeaveCalendarPlan::where('employee_id', $staffId)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
    
            if ($plan) {
                $startDate = $plan->start_date ? date('Y-m-d', strtotime($plan->start_date)) : null;
                $endDate = $plan->end_date ? date('Y-m-d', strtotime($plan->end_date)) : null;
                
                return response()->json([
                    'success' => true, 
                    'available_days' => $plan->balance, 
                    'start_date' => $startDate, 
                    'end_date' => $endDate
                ]);
            } else {
                return response()->json(['success' => false, 'error' => 'No leave plan found for this employee and leave type'], 404);
            }
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch available leave days'], 500);
        }
    }

    public function publicHolidaysList()
    {
        try {
            $holidays = PublicHoliday::where('current_status_id', 5) // Active/approved holidays
                ->orderBy('start_date')
                ->get()
                ->map(function ($holiday) {
                    return [
                        'id' => $holiday->id,
                        'title' => $holiday->title,
                        'start_date' => $holiday->start_date ? date('Y-m-d', strtotime($holiday->start_date)) : null,
                        'end_date' => $holiday->end_date ? date('Y-m-d', strtotime($holiday->end_date)) : null,
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
                return response()->json([
                    'success' => false,
                    'error' => 'Staff ID is required'
                ], 400);
            }
            
            // Get the specific staff member
            $employee = Staff::where('id', $staffId)
                ->where('current_status_id', 5)
                ->with(['department', 'position'])
                ->first();
            
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Staff member not found or inactive'
                ], 404);
            }
            
            // Get all leave types
            $leaveTypes = LeaveType::where('current_status_id', 5)
                ->orderBy('type_name')
                ->get();
    
            $leaveBalances = [];
            
            foreach ($leaveTypes as $leaveType) {
                // Get leave calendar plan for this staff and leave type for the year
                $plan = LeaveCalendarPlan::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->whereYear('start_date', $year)
                    ->where('current_status_id', 5) // Approved plans only
                    ->first();
                
                if ($plan) {
                    // Get total days taken (approved or completed leave requests)
                    $daysTaken = LeaveRequest::where('staff_id', $employee->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->whereYear('start_date', $year)
                        ->whereIn('current_status_id', [5, 19, 49]) // Approved or Completed
                        ->sum('used_days');
                    
                    // Get pending leave requests
                    $daysPending = LeaveRequest::where('staff_id', $employee->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->whereYear('start_date', $year)
                        ->whereIn('current_status_id', [1, 24]) // Pending or Partially Approved
                        ->sum('requested_days');
                    
                    $leaveBalances[] = [
                        'leave_type_id' => $leaveType->id,
                        'leave_type_name' => $leaveType->type_name,
                        'plan_start_date' => $plan->start_date ? date('Y-m-d', strtotime($plan->start_date)) : '-',
                        'plan_end_date' => $plan->end_date ? date('Y-m-d', strtotime($plan->end_date)) : '-',
                        'entitled_days' => round($plan->entitled_days, 2),
                        'days_taken' => round($daysTaken, 2),
                        'days_pending' => round($daysPending, 2),
                        'current_balance' => round($plan->balance, 2),
                        'available_balance' => round($plan->balance - $daysPending, 2),
                        'has_plan' => true,
                    ];
                } else {
                    $leaveBalances[] = [
                        'leave_type_id' => $leaveType->id,
                        'leave_type_name' => $leaveType->type_name,
                        'plan_start_date' => '-',
                        'plan_end_date' => '-',
                        'entitled_days' => 0,
                        'days_taken' => 0,
                        'days_pending' => 0,
                        'current_balance' => 0,
                        'available_balance' => 0,
                        'has_plan' => false,
                    ];
                }
            }
            
            $reportData = [
                'staff_id' => $employee->id,
                'staff_number' => $employee->staff_number ?? '-',
                'full_names' => $employee->full_names,
                'department' => $employee->department ? $employee->department->department_name : '-',
                'position' => $employee->position ? $employee->position->position_name : '-',
                'email' => $employee->email_address ?? '-',
                'leave_balances' => $leaveBalances,
            ];
            
            return response()->json([
                'success' => true,
                'data' => [$reportData], // Wrap in array for consistent frontend handling
                'year' => $year
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
            
            // Get the same data as the report
            $response = $this->leaveBalanceReportData($request);
            $responseData = json_decode($response->content(), true);
            
            if (!$responseData['success']) {
                return response()->json(['success' => false, 'error' => 'Failed to get report data'], 500);
            }
            
            $data = $responseData['data'];
            
            // Create CSV
            $filename = "leave_balance_report_{$year}_" . date('YmdHis') . ".csv";
            $handle = fopen('php://temp', 'r+');
            
            // Header row
            $headers = [
                'Staff Number', 'Full Names', 'Department', 'Position', 'Email',
                'Leave Type', 'Plan Start', 'Plan End', 'Entitled Days', 
                'Days Taken', 'Days Pending', 'Current Balance', 'Available Balance'
            ];
            fputcsv($handle, $headers);
            
            // Data rows
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
                ->whereIn('current_status_id', [5]) // Approved or Completed
                ->get()
                ->map(function($request) {
                    // Count days taken and refunded (only approved adjustments)
                    $daysTaken = \App\Models\LeaveRequestDaysTaken::where('parent_id', $request->id)
                        ->where('current_status_id', 5)
                        ->count();
                    
                    $daysRefunded = \App\Models\LeaveRequestDaysTaken::where('parent_id', $request->id)
                        ->where('current_status_id', 41) // Refunded status
                        ->count();
                    
                    // Count adjustment records (including pending)
                    $adjustmentCount = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)->count();

                    $leaveAdjustmentCurrentStatus = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)
                        ->orderBy('requested_at', 'desc')
                        ->first();
                    
                    // Check for pending adjustments
                    $hasPendingAdjustment = \App\Models\LeaveAdjustment::where('leave_request_id', $request->id)
                        ->whereIn('current_status_id', [1, 24]) // Pending or Partially Approved
                        ->exists();
                    
                    return [
                        'id' => $request->id,
                        'employee' => $request->employee,
                        'position' => $request->position,
                        'leave_type' => $request->leaveType,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'days_taken_count' => $daysTaken,
                        'days_refunded_count' => $daysRefunded,
                        'current_status_id' => $request->current_status_id,
                        'leave_adjustment_current_status_id' => $leaveAdjustmentCurrentStatus->current_status_id ?? null,
                        'adjustment_count' => $adjustmentCount,
                        'has_pending_adjustment' => $hasPendingAdjustment,
                        'is_partial_day' => $request->isPartialDay(),
                        'hours_requested' => $request->getHoursRequested(),
                        'can_update' => Auth::user()->isAbleTo('update_leave_adjustments'),
                        'can_view' => Auth::user()->isAbleTo('read_leave_adjustments'),
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
                'success' => true,
                'leave_request' => $leaveRequest,
                'days_taken' => $daysTaken,
                'is_partial_day' => $leaveRequest->isPartialDay(),
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
                'day_ids' => 'required|array|min:1',
                'reason' => 'required|string',
                'is_partial_day' => 'required',
                'hours_to_refund' => 'nullable|numeric|min:0',
            ]);

            Log::info('Leave Request ID for Adjustment: ' . $id);
            $leaveRequest = LeaveRequest::findOrFail($id);
            if($leaveRequest->current_status_id != 5) {
                return response()->json([
                    'success' => false, 
                    'error' => 'Leave adjustment can only be requested for approved leave requests'
                ], 422);
            }

            $isPartialDay = $validated['is_partial_day'] ?? false;
            $hoursToRefund = $validated['hours_to_refund'] ?? 0;
            Log::info('Leave Adjustment Request: is_partial_day=' . ($isPartialDay ? 'true' : 'false') . ', hours_to_refund=' . $hoursToRefund);
            
            // Check if there's already a pending adjustment
            $existingPending = \App\Models\LeaveAdjustment::where('leave_request_id', $id)
                ->whereIn('current_status_id', [1, 24]) // Pending or Partially Approved
                ->exists();
            
            if ($existingPending) {
                return response()->json([
                    'success' => false, 
                    'error' => 'There is already a pending adjustment for this leave request'
                ], 422);
            }
            
            // Calculate days to refund
            $daysToRefund = ($isPartialDay == 'true' || $isPartialDay === true) ? round($hoursToRefund / 8, 2) : count($validated['day_ids']);
            
            // Create adjustment request (pending approval)
            $adjustment = \App\Models\LeaveAdjustment::create([
                'leave_request_id' => $id,
                'days_refunded' => $daysToRefund,
                'reason' => $validated['reason'],
                'requested_by' => Auth::id(),
                'requested_at' => now(),
                'current_status_id' => 1, // Pending
                'day_ids' => $validated['day_ids'],
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Leave adjustment request submitted for approval',
                'adjustment_id' => $adjustment->id,
                'days_to_refund' => $daysToRefund,
            ]);
        } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch leave types'], 500);
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
                        'id' => $adj->id,
                        'days_refunded' => $adj->days_refunded,
                        'reason' => $adj->reason,
                        'requested_by' => $adj->requestedBy,
                        'requested_at' => $adj->requested_at,
                        'approved_by' => $adj->approvedBy,
                        'approved_at' => $adj->approved_at,
                        'confirmed_by' => $adj->confirmedBy,
                        'confirmed_at' => $adj->confirmed_at,
                        'current_status_id' => $adj->current_status_id,
                        'rejection_reason' => $adj->rejection_reason,
                    ];
                });
            
            return response()->json(['success' => true, 'history' => $history]);
         } catch (\Exception $e) {
            try { ErrorDispatcher::dispatch($e); } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch leave types'], 500);
        }
    }
   
}
