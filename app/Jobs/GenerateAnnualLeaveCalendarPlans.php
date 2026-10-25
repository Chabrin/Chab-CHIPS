<?php

namespace App\Jobs;

use App\Models\Staff;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Services\Leave\LeaveCalendarService;
use Illuminate\Bus\Queueable;
use App\Models\LeaveCalendarPlan;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateAnnualLeaveCalendarPlans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $year;
    public $leaveTypeIds;
    public $override;
    public $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($year, $leaveTypeIds = null, $override = false, $userId = null)
    {
        $this->year = $year;
        $this->leaveTypeIds = $leaveTypeIds;
        $this->override = $override;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(LeaveCalendarService $calendar): void
    {
        Log::info("Starting annual leave calendar generation for year {$this->year}");
        
        // Get all active staff
        $activeStaff = Staff::where('current_status_id', 1)->get();
        
        // Only annual leave is planned; sick, compassionate and the like are applied for as they happen
        $leaveTypes = LeaveType::all()->filter(fn($type) => $calendar->isAnnualLeaveType($type->type_name));
        if ($this->leaveTypeIds && count($this->leaveTypeIds) > 0) {
            $leaveTypes = $leaveTypes->whereIn('id', $this->leaveTypeIds);
        }
        Log::info("Generating annual leave plans for: " . $leaveTypes->pluck('type_name')->implode(', '));
        
        if ($activeStaff->isEmpty() || $leaveTypes->isEmpty()) {
            Log::warning("No active staff or leave types found for annual plan generation");
            return;
        }
        
        $startDate = "{$this->year}-01-01";
        $endDate = "{$this->year}-12-31";
        
        $created = 0;
        $skipped = 0;
        $updated = 0;
        
        foreach ($activeStaff as $staff) {
            foreach ($leaveTypes as $leaveType) {
                try {
                    // Check if plan already exists
                    $existingPlan = LeaveCalendarPlan::where('employee_id', $staff->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->whereYear('start_date', $this->year)
                        ->first();
                    
                    if ($existingPlan) {
                        if ($this->override) {
                            // Update existing plan
                            $usedDays = $this->getLeaveUsedDays($this->year, $leaveType->id, $staff->id);
                            $entitled = $this->entitlementFor($calendar, $staff, $leaveType);
                            $existingPlan->update([
                                'start_date' => $startDate,
                                'end_date' => $endDate,
                                'entitled_days' => $entitled,
                                'balance' => $entitled - $usedDays,
                                'current_status_id' => 5, // Approved
                            ]);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    
                    // Create new plan
                    $usedDays = $this->getLeaveUsedDays($this->year, $leaveType->id, $staff->id);
                    $entitled = $this->entitlementFor($calendar, $staff, $leaveType);

                    LeaveCalendarPlan::create([
                        'employee_id' => $staff->id,
                        'leave_type_id' => $leaveType->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'entitled_days' => $entitled,
                        'balance' => $entitled - $usedDays,
                        'current_status_id' => 5, // Approved
                        'created_by' => $this->userId,
                        'date_time' => now(),
                    ]);
                    
                    $created++;
                    
                } catch (\Exception $e) {
                    Log::error("Failed to create leave plan for staff {$staff->id}, leave type {$leaveType->id}: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        Log::info("Annual leave calendar generation completed: Created={$created}, Updated={$updated}, Skipped={$skipped}");
    }

    /**
     * Entitled days for a staff member's annual leave, pro-rated for mid-year hires.
     */
    private function entitlementFor(LeaveCalendarService $calendar, Staff $staff, LeaveType $leaveType) {
        return $calendar->calculateAnnualEntitlement($staff->getRawOriginal('hire_date'), (int) $this->year);
    }

    /**
     * Get used leave days for a specific year, leave type, and employee
     */
    private function getLeaveUsedDays($year, $leave_type_id, $employee_id)
    {
        $used_days = LeaveRequest::whereYear('start_date', $year)
            ->where('leave_type_id', $leave_type_id)
            ->where('staff_id', $employee_id)
            ->whereIn('current_status_id', [5, 49]) // Approved or Completed
            ->sum('days');

        return $used_days;
    }
}