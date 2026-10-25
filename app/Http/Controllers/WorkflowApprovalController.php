<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfirmationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendApprovalNotification;

class WorkflowApprovalController extends Controller
{
    public function __construct(private \App\Services\Leave\LeaveCalendarService $calendar,
        private \App\Services\Leave\LeaveTierService $tiers,
        private \App\Services\Leave\LeaveApprovalService $approvals,
        private \App\Services\Leave\LeaveApprovalRouter $router)
    {
    }

    private $workflowStatuses = [
        1 => 'Pending',
        2 => 'Paid',
        3 => 'Cancelled',
        4 => 'Suspended',
        5 => 'Approved',
        6 => 'Posted',
        7 => 'Converted',
        8 => 'Partially Cleared',
        9 => 'Cleared',
        10 => 'N/A',
        11 => 'Unallocated',
        12 => 'Partially Allocated',
        13 => 'Fully Allocated',
        15 => 'Reconciled',
        16 => 'Unreconciled',
        17 => 'Processed',
        18 => 'Unmatched',
        19 => 'Completed',
        20 => 'Not Posted',
        21 => 'Renewed',
        22 => 'Expired',
        23 => 'Partially Posted',
        24 => 'Partially Approved',
        25 => 'Sent',
        26 => 'Failed',
        27 => 'Offsetted',
        28 => 'Terminated',
        29 => 'Withdrawn',
        30 => 'Blacklisted',
        31 => 'Vacant',
        32 => "Owner's Account",
        33 => 'Maintenance',
        34 => 'Paid Vacant',
        35 => 'Sat Deposit',
        36 => 'Absconded',
        37 => 'Paid Owner',
        38 => 'Occupied',
        39 => 'Unreconciled Amount',
        40 => 'Solved',
        41 => 'Rejected',
        42 => 'Closed',
        46 => 'Deleted',
        47 => 'Exported',
        48 => 'Vacation Request',
        49 => 'Active',
        50 => 'On Leave',
        60 => 'Read',
        61 => 'Unread',
        62 => 'Returns Cleared',
        70 => 'Topped Up',
        71 => 'Utilized',
        72 => 'Cleared Voucher',
        75 => 'FO Approved',
        77 => 'ZM Approved',
        78 => 'PM Approved',
        79 => 'Clerical Approved',
        81 => 'Paid Arrears',
        82 => 'SFO Approved',
        83 => 'Internal Audit Approved',
        84 => 'Received',
        85 => 'Checked',
        87 => 'Verified',
        88 => 'Reset',
        89 => 'Deleted',
        91 => 'Rescheduled',
        94 => 'Reinstated',
    ];

    public function index()
    {
        $approvalSections = [
            ['permission' => 'update_contracts', 'label' => 'Staff Contracts'],
            ['permission' => 'update_discipline_records', 'label' => 'Staff Discipline Tracker'],
            ['permission' => 'update_zone_assignments', 'label' => 'Zone Assignments'],
            ['permission' => 'update_employee_terminations', 'label' => 'Employee Termination'],
            ['permission' => 'update_employee_attendances', 'label' => 'Employee Attendance'],
            ['permission' => 'update_communication_templates', 'label' => 'Communication Templates'],
            ['permission' => 'update_leave_applications', 'label' => 'Leave Application'],
            // ['permission' => 'update_leave_calendar_plans', 'label' => 'Leave Calendar'],
            ['permission' => 'update_leave_adjustments', 'label' => 'Leave Adjustments']
        ];

        // Leave is shown to anyone who can approve it (HR, or a holder of a supervisor position); every other
        // section still follows its permission
        $user = Auth::user();
        $approvalSections = array_values(array_filter($approvalSections, function ($section) use ($user) {
            return $section['label'] === 'Leave Application'
                ? $this->router->isApprover($user)
                : $user->isAbleTo($section['permission']);
        }));

        return view('workflow_approvals.index', compact('approvalSections'));
    }

    private function logConfirmation($section, $action, $ids, $approvalStage = null, $metadata = null)
    {
        ConfirmationLog::create([
            'section' => $section,
            'model' => $this->getModelClass($section),
            'action' => $action,
            'ids' => is_array($ids) ? $ids : [$ids],
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
            'approval_stage' => $approvalStage,
            'metadata' => $metadata,
        ]);
    }

    private function getModelClass($section)
    {
        $map = [
            'Staff Contracts' => \App\Models\Contract::class,
            'Staff Discipline Tracker' => \App\Models\StaffDiscipline::class,
            'Zone Assignments' => \App\Models\ZoneAssignment::class,
            'Leave Application' => \App\Models\LeaveRequest::class,
            'Leave Calendar' => \App\Models\LeaveCalendarPlan::class,
            'Employee Termination' => \App\Models\EmployeeTermination::class,
            'Employee Attendance' => \App\Models\EmployeeAttendance::class,
            'Communication' => \App\Models\Communication::class,
            'Communication Templates' => \App\Models\CommunicationTemplate::class,
        ];
        return $map[$section] ?? '';
    }

    public function approvalsList(Request $request)
    {
        $section = $request->get('section');
    
        switch ($section) {
            case 'Staff Contracts':
                $items = \App\Models\Contract::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Staff Contract',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Staff: ' . ($item->employee->full_names ?? '-'),
                            'Staff No: ' . ($item->staff_number ?? '-'),
                            'Position: ' . ($item->position->position_name ?? '-'),
                            'Salary: KES ' . number_format($item->basic_salary, 2),
                            'Start: ' . ($item->date_start ? date('Y-m-d', strtotime($item->date_start)) : '-'),
                            'End: ' . ($item->date_end ? date('Y-m-d', strtotime($item->date_end)) : '-'),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Staff Discipline Tracker':
                $items = \App\Models\DisciplineCase::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Discipline Record',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Staff: ' . ($item->staff->full_names ?? '-'),
                            'Position: ' . ($item->position->position_name ?? '-'),
                            'Case Number: ' . ($item->case_number ?? '-'),
                            'Date: ' . ($item->date_time ? date('Y-m-d', strtotime($item->date_time)) : '-'),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Zone Assignments':
                $items = \App\Models\ZoneAssignment::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Zone Assignment',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Staff: ' . ($item->staff->full_names ?? '-'),
                            'Zone: ' . ($item->zone->zone_name ?? '-'),
                            'Assigned As: ' . ($item->assigned_as ?? '-'),
                            'Type: ' . ($item->assignment_type->type_name ?? '-'),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Leave Application':
                // Only the requests this user may act on: supervisors see their team's stage-1 requests, HR sees the rest
                $items = $this->router->actionableFor(Auth::user());
                $records = $items->map(function ($item) {
                    $statusCode = (int) $item->current_status_id;
                    $approvalStage = $statusCode === 24 ? 2 : 1;
                    $stageOneConfirmedBy = null;

                    if ($statusCode === 24 && $item->status_changed_by) {
                        $stageOneConfirmedBy = \App\Models\User::whereKey($item->status_changed_by)->value('name');
                    }

                    // Determine leave duration type and calculate hours properly
                    $durationType = 'Full Day(s)';
                    $durationDetails = '';

                    $startTime = strtotime($item->start_date);
                    $endTime = strtotime($item->end_date);
                    $hoursRequested = round(abs(($endTime - $startTime) / 3600), 2);

                    $isSameDate = date('Y-m-d', $startTime) === date('Y-m-d', $endTime);
                    $startTimeOnly = date('H:i', $startTime);
                    $endTimeOnly = date('H:i', $endTime);
                    $hasCustomTimes = !($startTimeOnly === '08:00' && $endTimeOnly === '17:00');

                    if ($isSameDate && $hasCustomTimes) {
                        // Hours Only (Partial Day)
                        $durationType = 'Hours Only';
                        $durationDetails = ' | Hours: ' . $hoursRequested . ' hrs | Time: ' .
                                        $startTimeOnly . ' - ' . $endTimeOnly;
                    } elseif (!$isSameDate && $hasCustomTimes) {
                        // Days + Hours
                        $durationType = 'Days + Hours';
                        $durationDetails = ' | Total Hours: ' . $hoursRequested . ' hrs | Start: ' .
                                        date('Y-m-d H:i', $startTime) . ' | End: ' .
                                        date('Y-m-d H:i', $endTime);
                    } else {
                        // Full Day(s)
                        $durationDetails = ' | Days: ' . $item->days;
                    }

                    return [
                        'id' => $item->id,
                        'type' => 'Leave Application',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Employee: ' . ($item->employee->full_names ?? '-'),
                            'Position: ' . ($item->employee->position->position_name ?? '-'),
                            'Leave Type: ' . ($item->leaveType->type_name ?? '-'),
                            'Duration Type: ' . $durationType,
                            'Start: ' . ($item->start_date ? date('Y-m-d', strtotime($item->start_date)) : '-'),
                            'End: ' . ($item->end_date ? date('Y-m-d', strtotime($item->end_date)) : '-'),
                            'Requested Days: ' . ($item->requested_days ?? '-'),
                            'Balance: ' . ($item->days_balance ?? '-'),
                        ]) . $durationDetails,
                        'status_code' => $statusCode,
                        'status' => $this->router->stageLabel($item),
                        'stage_one_confirmed_by' => $stageOneConfirmedBy,
                        'approval_stage' => $approvalStage,
                        'days_taken' => [],
                        'duration_type' => $durationType,
                    ];
                })->values();
                break;
            case 'Leave Calendar':
                $items = \App\Models\LeaveCalendarPlan::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Leave Calendar',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Employee: ' . ($item->employee->full_names ?? '-'),
                            'Leave Type: ' . ($item->leaveType->type_name ?? '-'),
                            'Start: ' . ($item->start_date ? date('Y-m-d', strtotime($item->start_date)) : '-'),
                            'End: ' . ($item->end_date ? date('Y-m-d', strtotime($item->end_date)) : '-'),
                            'Entitled Days: ' . ($item->entitled_days ?? '-'),
                            'Balance: ' . ($item->balance ?? '-'),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Employee Termination':
                $items = \App\Models\EmployeeTermination::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Employee Termination',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Staff: ' . ($item->staff->full_names ?? '-'),
                            'Contract No: ' . ($item->contract->staff_number ?? '-'),
                            'Years Served: ' . ($item->years_served ?? '-'),
                            'Pay/Year: KES ' . number_format($item->pay_per_year, 2),
                            'Severance: KES ' . number_format($item->severerance_payment, 2),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Employee Attendance':
                $items = \App\Models\EmployeeAttendance::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Employee Attendance',
                        'requested_by' => $item->created_by_id ? (\App\Models\User::find($item->created_by_id)->name ?? '-') : '-',
                        'requested_at' => $item->date_time ?? '-',
                        'details' => implode(' | ', [
                            'Employee ID: ' . ($item->employee_id ?? '-'),
                            'Date: ' . ($item->date_time ?? '-'),
                            'Month/Year: ' . ($item->month_year ?? '-'),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Communication':
                $items = \App\Models\Communication::where('status', 1)->get(); // 5 = Approved (waiting to be sent)
                $records = $items->map(function ($item) {
                    $statusCode = $item->status;
                    return [
                        'id' => $item->id,
                        'type' => 'Communication',
                        'requested_by' => $item->created_by ? (\App\Models\User::find($item->created_by)->name ?? '-') : '-',
                        'requested_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : '-',
                        'details' => implode(' | ', [
                            'Type: ' . ($item->type ?? '-'),
                            'Subject: ' . ($item->subject ?? '-'),
                            'Recipients: ' . implode(', ', json_decode($item->recipients, true) ?? []),
                            'Attachment: ' . ($item->attachment ? basename($item->attachment) : 'None'),
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Communication Templates':
                $items = \App\Models\CommunicationTemplate::where('current_status_id', 1)->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    return [
                        'id' => $item->id,
                        'type' => 'Communication Template',
                        'requested_by' => $item->createdBy->name ?? '-',
                        'requested_at' => $item->date_time,
                        'details' => implode(' | ', [
                            'Template Name: ' . ($item->template_name ?? '-'),
                            'Template Type: ' . ($item->template_type ?? '-'),
                            'Subject: ' . ($item->subject ?? '-'),
                            'Communication Method: ' . ($item->communication_method ?? '-'),
                            
                        ]),
                        'status_code' => $statusCode,
                        'status' => $this->workflowStatuses[$statusCode] ?? 'Unknown',
                    ];
                });
                break;
            case 'Leave Adjustments':
                $records = \App\Models\LeaveAdjustment::with([
                    'leaveRequest.employee', 
                    'leaveRequest.leaveType',
                    'leaveRequest.position',
                    'requestedBy',
                    'approvedBy'
                ])
                ->whereIn('current_status_id', [1, 24]) // Pending or Partially Approved
                ->orderBy('requested_at', 'desc')
                ->get()
                ->map(function($adj) {
                    return [
                        'id' => $adj->id,
                        'type' => 'Leave Adjustment',
                        'requested_by' => $adj->requestedBy ? $adj->requestedBy->name : '-',
                        'requested_at' => $adj->requested_at,
                        'details' => implode(' | ', [
                            'Employee: ' . ($adj->leaveRequest->employee->full_names ?? '-'),
                            'Position: ' . ($adj->leaveRequest->position->position_name ?? '-'),
                            'Leave Type: ' . ($adj->leaveRequest->leaveType->type_name ?? '-'),
                            'Leave Period: ' . (date('M d, Y', strtotime($adj->leaveRequest->start_date)) . ' - ' . 
                                               date('M d, Y', strtotime($adj->leaveRequest->end_date))),
                            'Days to Refund: ' . $adj->days_refunded,
                            'Reason: ' . $adj->reason,
                        ]),
                        'status' => $adj->current_status_id == 1 ? 'Pending' : 'Partially Approved',
                        'status_code' => $adj->current_status_id,
                        'approval_stage' => $adj->current_status_id == 1 ? 1 : 2,
                        'stage' => $adj->current_status_id == 1 ? 1 : 2,
                        'employee_name' => $adj->leaveRequest->employee->full_names ?? '-',
                        'position' => $adj->leaveRequest->position->position_name ?? '-',
                        'leave_type' => $adj->leaveRequest->leaveType->type_name ?? '-',
                        'days_to_refund' => $adj->days_refunded,
                        'reason' => $adj->reason,
                        'leave_period' => date('M d, Y', strtotime($adj->leaveRequest->start_date)) . ' - ' . 
                                        date('M d, Y', strtotime($adj->leaveRequest->end_date)),
                        'day_ids' => $adj->day_ids,
                    ];
                });
                break;

            default:
                $records = collect();
        }
    
        return response()->json(['records' => $records]);
    }

    private function generateDaysBetweenDates($startDate, $endDate)
    {
        $days = [];
        // Extract just the date part from datetime
        $current = strtotime(date('Y-m-d', strtotime($startDate)));
        $end = strtotime(date('Y-m-d', strtotime($endDate)));
        
        while ($current <= $end) {
            $dayOfWeek = date('N', $current); // 1=Monday, 7=Sunday
            $dayTypeId = 1; // Default weekday
            
            if ($dayOfWeek == 6) {
                $dayTypeId = 2; // Saturday
            } elseif ($dayOfWeek == 7) {
                $dayTypeId = 3; // Sunday
            }
            
            $days[] = [
                'date' => date('Y-m-d', $current),
                'type' => $dayTypeId,
                'day_name' => date('l', $current)
            ];
            
            $current = strtotime('+1 day', $current);
        }
        
        return $days;
    }

    /**
     * The days that approving a pending leave request will charge, for the approver to review. Only available to
     * a user who is allowed to act on the request.
     */
    public function getLeaveApplicationDays($id)
    {
        try {
            $item = \App\Models\LeaveRequest::findOrFail($id);

            if ($error = $this->router->canAct(Auth::user(), $item)) {
                return response()->json(['success' => false, 'error' => $error], 403);
            }

            $preview = $this->approvals->previewDays($item);

            return response()->json([
                'success' => true,
                'days' => $preview['days'],
                'total_days' => $preview['total'],
                'is_partial_day' => $preview['is_partial_day'],
                'total_hours' => $preview['hours'],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Leave application not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error getting leave application days: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to get days'], 500);
        }
    }

    /**
     * Find the public holiday (if any) falling on the given date, honouring each holiday's recurrent flag.
     */
    private function checkIfPublicHoliday($date) {
        return $this->calendar->holidayOn(\Carbon\Carbon::parse($date));
    }

    /**
     * Check that a record is still in a status eligible for approval/rejection, so an
     * already-actioned or out-of-sequence record can't be re-approved/rejected.
     *
     * @param int|null $currentStatus
     * @param array $allowedStatuses
     */
    private function assertActionableStatus(?int $currentStatus, array $allowedStatuses = [1]): ?\Illuminate\Http\JsonResponse
    {
        if (!in_array($currentStatus, $allowedStatuses, true)) {
            return response()->json(['success' => false, 'error' => 'This record is no longer pending and cannot be actioned.'], 422);
        }

        return null;
    }

    /**
     * Check that a staff member referenced by an approval still exists and isn't inactive or terminated.
     *
     * @param int|null $staffId
     */
    private function assertStaffValid(?int $staffId): ?\Illuminate\Http\JsonResponse
    {
        $staff = $staffId ? \App\Models\Staff::find($staffId) : null;

        if (!$staff) {
            return response()->json(['success' => false, 'error' => 'The referenced staff member no longer exists.'], 422);
        }

        if (in_array($staff->current_status_id, [2, 3], true)) {
            return response()->json(['success' => false, 'error' => 'The referenced staff member is inactive or terminated.'], 422);
        }

        return null;
    }

    /**
     * Check that a staff member referenced by a termination still exists and isn't already terminated.
     *
     * @param int|null $staffId
     */
    private function assertStaffTerminable(?int $staffId): ?\Illuminate\Http\JsonResponse
    {
        $staff = $staffId ? \App\Models\Staff::find($staffId) : null;

        if (!$staff) {
            return response()->json(['success' => false, 'error' => 'The referenced staff member no longer exists.'], 422);
        }

        if ($staff->current_status_id == 3) {
            return response()->json(['success' => false, 'error' => 'The referenced staff member is already terminated.'], 422);
        }

        return null;
    }

    /**
     * Get the ids of staff members not in the given excluded employment statuses, for
     * scoping bulk approval updates to valid staff references only.
     *
     * @param array $excludedStatuses
     */
    private function validStaffIds(array $excludedStatuses): array
    {
        return \App\Models\Staff::where(function ($query) use ($excludedStatuses) {
            $query->whereNotIn('current_status_id', $excludedStatuses)
                ->orWhereNull('current_status_id');
        })->pluck('id')->toArray();
    }

    /**
     * Approve one item of the chosen section; a record that doesn't exist is a 404, not a server error.
     */
    public function approve(Request $request, $id)
    {
        try {
            return $this->approveItem($request, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Record not found'], 404);
        }
    }

    private function approveItem(Request $request, $id)
    {
        $section = $request->input('section');
        switch ($section) {
            case 'Staff Contracts':
                $item = \App\Models\Contract::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                if ($error = $this->assertStaffValid($item->staff_id)) {
                    return $error;
                }
                $item->current_status_id = 5; // Approved
                $item->save();

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Staff Discipline Tracker':
                $item = \App\Models\DisciplineCase::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                if ($error = $this->assertStaffValid($item->staff_id)) {
                    return $error;
                }
                $item->current_status_id = 5;
                $item->save();

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Zone Assignments':
                $item = \App\Models\ZoneAssignment::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                if ($error = $this->assertStaffValid($item->staff_id)) {
                    return $error;
                }
                if (!\App\Models\Zone::find($item->zone_id) || !\App\Models\Property::withoutGlobalScopes()->find($item->property_id)) {
                    return response()->json(['success' => false, 'error' => 'The referenced zone or property no longer exists.'], 422);
                }
                $item->current_status_id = 5;
                $item->save();

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Leave Application':
                // Routing, permission, maker-checker and logging all live in the approval service
                $result = $this->approvals->approve(\App\Models\LeaveRequest::findOrFail($id), Auth::user());

                return response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 422));
            case 'Leave Calendar':
                $item = \App\Models\LeaveCalendarPlan::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                if ($error = $this->assertStaffValid($item->employee_id)) {
                    return $error;
                }
                $item->current_status_id = 5;
                $item->save();
                break;
            case 'Employee Termination':
                $item = \App\Models\EmployeeTermination::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                if ($error = $this->assertStaffTerminable($item->staff_id)) {
                    return $error;
                }
                $item->current_status_id = 5;
                $item->save();

                \App\Models\Staff::where('id', $item->staff_id)->update(['current_status_id' => 3]); // Terminated

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Employee Attendance':
                $item = \App\Models\EmployeeAttendance::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                if ($error = $this->assertStaffValid($item->employee_id)) {
                    return $error;
                }
                $item->current_status_id = 5; // Approved
                $item->save();

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Communication Templates':
                $item = \App\Models\CommunicationTemplate::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 5; // Approved
                $item->save();

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Leave Adjustments':
                $adjustment = \App\Models\LeaveAdjustment::findOrFail($id);

                $leaveRequest = $adjustment->leaveRequest;
                if (!$leaveRequest) {
                    return response()->json(['success' => false, 'error' => 'The referenced leave application no longer exists.'], 422);
                }
                if ($error = $this->assertStaffValid($leaveRequest->staff_id)) {
                    return $error;
                }

                if ($adjustment->current_status_id == 1) {
                    // Stage 1: First approval
                    $adjustment->current_status_id = 24; // Partially Approved
                    $adjustment->approved_by = Auth::id();
                    $adjustment->approved_at = now();
                    $adjustment->save();
                    
                    $this->logConfirmation($section, 'approve', $id, 1);
                    
                } elseif ($adjustment->current_status_id == 24) {
                    // Stage 2: Final confirmation
                    
                    // Check same person restriction
                    if ($adjustment->approved_by == Auth::id()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'You cannot approve the same adjustment twice. A different approver is required.'
                        ], 422);
                    }
                    
                    // Mark days as refunded
                    \App\Models\LeaveRequestDaysTaken::whereIn('id', $adjustment->day_ids)
                        ->update(['current_status_id' => 41]); // Refunded
                        $this->tiers->retagForRequest($leaveRequest);
                    
                    // Update leave request used_days
                    $leaveRequest = $adjustment->leaveRequest;
                    $leaveRequest->used_days = ($leaveRequest->used_days ?? 0) - $adjustment->days_refunded;
                    $leaveRequest->save();
                    
                    // Update leave calendar plan balance
                    $start_date_only = date('Y-m-d', strtotime($leaveRequest->start_date)) . ' 00:00:00';
                    $plan = \App\Models\LeaveCalendarPlan::where('employee_id', $leaveRequest->staff_id)
                        ->where('leave_type_id', $leaveRequest->leave_type_id)
                        ->where('start_date', $start_date_only)
                        ->where('current_status_id', 5)
                        ->first();
                    
                    if ($plan) {
                        $plan->balance = $plan->balance + $adjustment->days_refunded;
                        $plan->save();
                    }
                    
                    $adjustment->current_status_id = 5; // Approved
                    $adjustment->confirmed_by = Auth::id();
                    $adjustment->confirmed_at = now();
                    $adjustment->save();
                    
                    $this->logConfirmation($section, 'approve', $id, 2, [
                        'days_refunded' => $adjustment->days_refunded,
                        'leave_request_id' => $adjustment->leave_request_id,
                        'employee_id' => $leaveRequest->staff_id,
                        'plan_balance_after' => $plan ? $plan->balance : null,
                    ]);

                    // Send notification
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                } else {
                    return response()->json(['success' => false, 'error' => 'This record is no longer pending and cannot be actioned.'], 422);
                }
                break;
        }

        $this->logConfirmation($section, 'approve', $id);

        return response()->json(['success' => true]);
    }
    
    /**
     * Reject one item of the chosen section; a record that doesn't exist is a 404, not a server error.
     */
    public function reject(Request $request, $id)
    {
        try {
            return $this->rejectItem($request, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Record not found'], 404);
        }
    }

    private function rejectItem(Request $request, $id)
    {
        $section = $request->input('section');
        switch ($section) {
            case 'Staff Contracts':
                $item = \App\Models\Contract::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41; // Rejected
                $item->save();
                break;
            case 'Staff Discipline Tracker':
                $item = \App\Models\DisciplineCase::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41;
                $item->save();
                break;
            case 'Zone Assignments':
                $item = \App\Models\ZoneAssignment::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41;
                $item->save();
                break;
            case 'Leave Application':
                $result = $this->approvals->reject(\App\Models\LeaveRequest::findOrFail($id), Auth::user());

                return response()->json($result, $result['success'] ? 200 : ($result['status'] ?? 422));
            case 'Leave Calendar':
                $item = \App\Models\LeaveCalendarPlan::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41;
                $item->save();
                break;
            case 'Employee Termination':
                $item = \App\Models\EmployeeTermination::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41;
                $item->save();
                break;
            case 'Employee Attendance':
                $item = \App\Models\EmployeeAttendance::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41; // Rejected
                $item->save();
                break;
            case 'Communication':
                $item = \App\Models\Communication::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->status)) {
                    return $error;
                }
                $item->status = 41; // Rejected
                $item->save();
                break;
            case 'Communication Templates':
                $item = \App\Models\CommunicationTemplate::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 41; // Rejected
                $item->save();
                break;
            case 'Leave Adjustments':
                $adjustment = \App\Models\LeaveAdjustment::findOrFail($id);
                if ($error = $this->assertActionableStatus($adjustment->current_status_id, [1, 24])) {
                    return $error;
                }
                $adjustment->current_status_id = 41; // Rejected
                $adjustment->rejection_reason = $request->input('reason', 'Rejected by approver');
                $adjustment->save();
                break;

        }
        
        $this->logConfirmation($section, 'reject', $id);

        return response()->json(['success' => true]);
    }
    
    public function bulkApprove(Request $request)
    {
        $ids = $request->input('ids', []);
        $section = $request->input('section');
        switch ($section) {
            case 'Staff Contracts':
                $validStaffIds = $this->validStaffIds([2, 3]);
                $eligibleIds = \App\Models\Contract::whereIn('id', $ids)->where('current_status_id', 1)->whereIn('staff_id', $validStaffIds)->pluck('id')->toArray();
                \App\Models\Contract::whereIn('id', $eligibleIds)->update(['current_status_id' => 5]);

                // Send notifications for each approved contract
                foreach ($eligibleIds as $id) {
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                }
                break;
            case 'Staff Discipline Tracker':
                $validStaffIds = $this->validStaffIds([2, 3]);
                $eligibleIds = \App\Models\DisciplineCase::whereIn('id', $ids)->where('current_status_id', 1)->whereIn('staff_id', $validStaffIds)->pluck('id')->toArray();
                \App\Models\DisciplineCase::whereIn('id', $eligibleIds)->update(['current_status_id' => 5]);

                // Send notifications
                foreach ($eligibleIds as $id) {
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                }
                break;
            case 'Zone Assignments':
                $validStaffIds = $this->validStaffIds([2, 3]);
                $validZoneIds = \App\Models\Zone::pluck('id')->toArray();
                $validPropertyIds = \App\Models\Property::withoutGlobalScopes()->pluck('id')->toArray();
                \App\Models\ZoneAssignment::whereIn('id', $ids)
                    ->where('current_status_id', 1)
                    ->whereIn('staff_id', $validStaffIds)
                    ->whereIn('zone_id', $validZoneIds)
                    ->whereIn('property_id', $validPropertyIds)
                    ->update(['current_status_id' => 5]);
                break;
            case 'Leave Application':
                $errors = [];
                $approvedCount = 0;

                foreach (\App\Models\LeaveRequest::whereIn('id', $ids)->get() as $item) {
                    $result = $this->approvals->approve($item, Auth::user());

                    if ($result['success']) {
                        $approvedCount++;
                    } else {
                        $errors[] = "Leave ID {$item->id}: " . $result['error'];
                    }
                }

                if (!empty($errors)) {
                    return response()->json([
                        'success' => false,
                        'error' => implode(', ', $errors),
                        'approved_count' => $approvedCount,
                    ], 422);
                }

                return response()->json(['success' => true, 'approved_count' => $approvedCount]);
            case 'Leave Calendar':
                $validStaffIds = $this->validStaffIds([2, 3]);
                \App\Models\LeaveCalendarPlan::whereIn('id', $ids)->where('current_status_id', 1)->whereIn('employee_id', $validStaffIds)->update(['current_status_id' => 5]);
                break;
            case 'Employee Termination':
                $validStaffIds = $this->validStaffIds([3]);
                $eligibleIds = \App\Models\EmployeeTermination::whereIn('id', $ids)->where('current_status_id', 1)->whereIn('staff_id', $validStaffIds)->pluck('id')->toArray();
                \App\Models\EmployeeTermination::whereIn('id', $eligibleIds)->update(['current_status_id' => 5]);

                $items = \App\Models\EmployeeTermination::whereIn('id', $eligibleIds)->get();
                foreach ($items as $item) {
                    \App\Models\Staff::where('id', $item->staff_id)->update(['current_status_id' => 3]); // Terminated
                }

                // Send notifications
                foreach ($eligibleIds as $id) {
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                }
                break;
            case 'Employee Attendance':
                $validStaffIds = $this->validStaffIds([2, 3]);
                \App\Models\EmployeeAttendance::whereIn('id', $ids)->where('current_status_id', 1)->whereIn('employee_id', $validStaffIds)->update(['current_status_id' => 5]);
                break;
            case 'Communication Templates':
                \App\Models\CommunicationTemplate::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 5]);
                break;
        }

        $this->logConfirmation($section, 'bulk approve', $ids);

        return response()->json(['success' => true]);
    }
    
    public function bulkReject(Request $request)
    {
        $ids = $request->input('ids', []);
        $section = $request->input('section');
        switch ($section) {
            case 'Staff Contracts':
                \App\Models\Contract::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
            case 'Staff Discipline Tracker':
                \App\Models\DisciplineCase::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
            case 'Zone Assignments':
                \App\Models\ZoneAssignment::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
            case 'Leave Application':
                $errors = [];

                foreach (\App\Models\LeaveRequest::whereIn('id', $ids)->get() as $item) {
                    $result = $this->approvals->reject($item, Auth::user());

                    if (!$result['success']) {
                        $errors[] = "Leave ID {$item->id}: " . $result['error'];
                    }
                }

                if (!empty($errors)) {
                    return response()->json(['success' => false, 'error' => implode(', ', $errors)], 422);
                }

                return response()->json(['success' => true]);
            case 'Leave Calendar':
                \App\Models\LeaveCalendarPlan::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
            case 'Employee Termination':
                \App\Models\EmployeeTermination::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
            case 'Employee Attendance':
                \App\Models\EmployeeAttendance::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
            case 'Communication':
                \App\Models\Communication::whereIn('id', $ids)->where('status', 1)->update(['status' => 41]); // Rejected
                break;
            case 'Communication Templates':
                \App\Models\CommunicationTemplate::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 41]);
                break;
        }

        $this->logConfirmation($section, 'bulk reject', $ids);

        return response()->json(['success' => true]);
    }
}
