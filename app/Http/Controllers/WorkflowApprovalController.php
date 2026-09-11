<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfirmationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendApprovalNotification;

class WorkflowApprovalController extends Controller
{
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
                // Get items in both approval stages
                $items = \App\Models\LeaveRequest::whereIn('current_status_id', [1, 24])->get();
                $records = $items->map(function ($item) {
                    $statusCode = $item->current_status_id;
                    $approvalStage = null;
                    $stageLabel = '';
                    $daysTaken = [];
                    $stageOneConfirmedBy = null;
                    
                    if ($statusCode == 1) {
                        $approvalStage = 1;
                        $stageLabel = 'Stage 1 (Set Days Taken)';
                    } elseif ($statusCode == 24) {
                        $approvalStage = 2;
                        $stageLabel = 'Stage 2 (Confirm Days)';
                        // Get the days taken that need confirmation
                        $daysTaken = \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)
                            ->orderBy('leave_date')
                            ->get()
                            ->map(function($day) {
                                $dayTypes = [1 => 'Weekday', 2 => 'Saturday', 3 => 'Sunday'];
                                return [
                                    'date' => date('Y-m-d', strtotime($day->leave_date)),
                                    'type' => $dayTypes[$day->day_type_id] ?? 'Unknown',
                                    'type_id' => $day->day_type_id
                                ];
                            })
                            ->toArray();

                        $stageOneConfirmedBy = ConfirmationLog::with('confirmedBy')
                            ->where('section', 'Leave Application')
                            ->where('model', \App\Models\LeaveRequest::class)
                            ->where('approval_stage', 1)
                            ->get()
                            ->first(function($log) use ($item) {
                                return in_array($item->id, $log->ids);
                            });
                    }
                    
                    // Determine leave duration type and calculate hours properly
                    $durationType = 'Full Day(s)';
                    $durationDetails = '';
                    
                    // Calculate hours correctly
                    $startTime = strtotime($item->start_date);
                    $endTime = strtotime($item->end_date);
                    $hoursRequested = abs(($endTime - $startTime) / 3600);
                    $hoursRequested = round($hoursRequested, 2);
                    
                    // Check if same date
                    $isSameDate = date('Y-m-d', $startTime) === date('Y-m-d', $endTime);
                    
                    // Check if has custom times
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
                            'Leave Type: ' . ($item->leaveType->type_name ?? '-'),
                            'Duration Type: ' . $durationType,
                            'Start: ' . ($item->start_date ? date('Y-m-d', strtotime($item->start_date)) : '-'),
                            'End: ' . ($item->end_date ? date('Y-m-d', strtotime($item->end_date)) : '-'),
                            'Requested Days: ' . ($item->requested_days ?? '-'),
                            'Balance: ' . ($item->days_balance ?? '-'),
                        ]) . $durationDetails,
                        'status_code' => $statusCode,
                        'status' => $stageLabel,
                        'stage_one_confirmed_by' => $stageOneConfirmedBy->confirmedBy->name ?? null,
                        'approval_stage' => $approvalStage,
                        'days_taken' => $daysTaken,
                        'duration_type' => $durationType,
                    ];
                });
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

    public function getLeaveApplicationDays($id)
    {
        try {
            $item = \App\Models\LeaveRequest::findOrFail($id);
            
            // Get all active public holidays once
            $publicHolidays = \App\Models\PublicHoliday::where('current_status_id', 5)->get();
            
            if ($item->current_status_id == 1) {
                // Stage 1: Generate suggested days based on start and end date
                
                // Calculate hours correctly using timestamps
                $startTime = strtotime($item->start_date);
                $endTime = strtotime($item->end_date);
                $hoursRequested = abs(($endTime - $startTime) / 3600);
                $hoursRequested = round($hoursRequested, 2);
                
                // Check if same date
                $isSameDate = date('Y-m-d', $startTime) === date('Y-m-d', $endTime);
                
                // Check if has custom times
                $startTimeOnly = date('H:i', $startTime);
                $endTimeOnly = date('H:i', $endTime);
                $hasCustomTimes = !($startTimeOnly === '08:00' && $endTimeOnly === '17:00');
                
                $isPartialDay = $isSameDate && $hasCustomTimes;
                
                if ($isPartialDay) {
                    // For partial day, only show the single day with time info
                    $dayOfWeek = date('N', $startTime); // 1=Monday, 7=Sunday
                    $dayTypeId = 1; // Weekday
                    if ($dayOfWeek == 6) $dayTypeId = 2; // Saturday
                    if ($dayOfWeek == 7) $dayTypeId = 3; // Sunday
                    
                    // Check if it's a public holiday
                    $currentMonth = (int) date('m', $startTime);
                    $currentDay = (int) date('d', $startTime);
                    $isPublicHoliday = $this->checkIfPublicHoliday($currentMonth, $currentDay, $publicHolidays);
                    
                    $days = [[
                        'date' => date('Y-m-d', $startTime),
                        'type' => $dayTypeId,
                        'day_name' => date('l', $startTime),
                        'start_time' => $startTimeOnly,
                        'end_time' => $endTimeOnly,
                        'hours' => $hoursRequested,
                        'is_partial' => true,
                        'is_public_holiday' => $isPublicHoliday ? true : false,
                        'holiday_title' => $isPublicHoliday ? $isPublicHoliday->title : null,
                    ]];
                } else {
                    // Full day or multi-day leave
                    $days = $this->generateDaysBetweenDates($item->start_date, $item->end_date);
                    
                    // Mark public holidays in the days array
                    foreach ($days as &$day) {
                        $dayTime = strtotime($day['date']);
                        $currentMonth = (int) date('m', $dayTime);
                        $currentDay = (int) date('d', $dayTime);
                        $isPublicHoliday = $this->checkIfPublicHoliday($currentMonth, $currentDay, $publicHolidays);
                        
                        $day['is_public_holiday'] = $isPublicHoliday ? true : false;
                        $day['holiday_title'] = $isPublicHoliday ? $isPublicHoliday->title : null;
                    }
                    
                    if ($hasCustomTimes) {
                        // Add time information to first and last day
                        if (count($days) > 0) {
                            $days[0]['start_time'] = $startTimeOnly;
                            $days[0]['has_start_time'] = true;
                            
                            $lastIndex = count($days) - 1;
                            $days[$lastIndex]['end_time'] = $endTimeOnly;
                            $days[$lastIndex]['has_end_time'] = true;
                        }
                        
                        // Add total hours info
                        foreach ($days as &$day) {
                            $day['total_hours'] = $hoursRequested;
                        }
                    }
                }
                
                return response()->json([
                    'success' => true, 
                    'days' => $days, 
                    'is_partial_day' => $isPartialDay,
                    'leave_duration_type' => $isPartialDay ? 'partial_day' : 
                        ($hasCustomTimes ? 'days_and_hours' : 'full_day'),
                    'total_hours' => $hoursRequested
                ]);
                
            } elseif ($item->current_status_id == 24) {
                // Stage 2: Show days that were set by first approver
                $days = \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)
                    ->orderBy('leave_date')
                    ->get()
                    ->map(function($day) use ($publicHolidays) {
                        $dayTime = strtotime($day->leave_date);
                        $currentMonth = (int) date('m', $dayTime);
                        $currentDay = (int) date('d', $dayTime);
                        $isPublicHoliday = $this->checkIfPublicHoliday($currentMonth, $currentDay, $publicHolidays);
                        
                        return [
                            'date' => date('Y-m-d', strtotime($day->leave_date)),
                            'type' => $day->day_type_id,
                            'day_name' => date('l', strtotime($day->leave_date)),
                            'is_public_holiday' => $isPublicHoliday ? true : false,
                            'holiday_title' => $isPublicHoliday ? $isPublicHoliday->title : null,
                        ];
                    });
                
                return response()->json([
                    'success' => true, 
                    'days' => $days, 
                    'is_partial_day' => false,
                    'leave_duration_type' => 'full_day'
                ]);
            }
            
            return response()->json(['success' => false, 'error' => 'Invalid status'], 400);
        } catch (\Exception $e) {
            Log::error('Error getting leave application days: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to get days'], 500);
        }
    }
    
    // Helper method to check if a date is a public holiday (year-agnostic)
    private function checkIfPublicHoliday($month, $day, $publicHolidays)
    {
        return $publicHolidays->first(function($holiday) use ($month, $day) {
            $holidayStartMonth = (int) date('m', strtotime($holiday->start_date));
            $holidayStartDay = (int) date('d', strtotime($holiday->start_date));
            $holidayEndMonth = (int) date('m', strtotime($holiday->end_date));
            $holidayEndDay = (int) date('d', strtotime($holiday->end_date));
            
            // Same month holiday (e.g., Dec 25 to Dec 25, or Dec 25 to Dec 26)
            if ($holidayStartMonth == $holidayEndMonth) {
                return $month == $holidayStartMonth && 
                       $day >= $holidayStartDay && 
                       $day <= $holidayEndDay;
            }
            
            // Multi-month holiday (e.g., Dec 31 to Jan 2)
            // Check if date falls in start month from start day onwards
            if ($month == $holidayStartMonth && $day >= $holidayStartDay) {
                return true;
            }
            
            // Check if date falls in end month up to end day
            if ($month == $holidayEndMonth && $day <= $holidayEndDay) {
                return true;
            }
            
            // Check if month is between start and end months
            if ($holidayStartMonth < $holidayEndMonth) {
                // Normal case: e.g., Nov to Dec
                return $month > $holidayStartMonth && $month < $holidayEndMonth;
            } else {
                // Year-spanning case: e.g., Dec to Jan
                return $month > $holidayStartMonth || $month < $holidayEndMonth;
            }
        });
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

    public function approve(Request $request, $id)
    {
        $section = $request->input('section');
        switch ($section) {
            case 'Staff Contracts':
                $item = \App\Models\Contract::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
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
                $item->current_status_id = 5;
                $item->save();
                break;
            case 'Leave Application':
                $item = \App\Models\LeaveRequest::findOrFail($id);
                
                if ($item->current_status_id == 1) {
                    // Stage 1: First approver sets up the actual days taken
                    $days_taken = $request->input('days_taken', []);
                    
                    if (empty($days_taken)) {
                        return response()->json(['success' => false, 'error' => 'Please specify the actual days taken'], 422);
                    }
                    
                    // Check if it's a partial day leave (hours only)
                    $startTime = strtotime($item->start_date);
                    $endTime = strtotime($item->end_date);
                    $isSameDate = date('Y-m-d', $startTime) === date('Y-m-d', $endTime);
                    $startTimeOnly = date('H:i', $startTime);
                    $endTimeOnly = date('H:i', $endTime);
                    $hasCustomTimes = !($startTimeOnly === '08:00' && $endTimeOnly === '17:00');
                    $isPartialDay = $isSameDate && $hasCustomTimes;
                    
                    // Delete any existing days taken entries (in case of re-approval)
                    \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)->delete();
                    
                    // For partial day (hours only), we only store ONE day entry with start time
                    if ($isPartialDay) {
                        // Only take the first day from the array and use the actual start time
                        $day = $days_taken[0];
                        \App\Models\LeaveRequestDaysTaken::create([
                            'parent_id' => $item->id,
                            'current_status_id' => 1, // Pending confirmation
                            'leave_date' => $item->start_date, // Use full datetime from original request
                            'day_type_id' => $day['type'],
                        ]);
                    } else {
                        // For full days or multi-day, store all unique days (date only at midnight)
                        $uniqueDays = [];
                        foreach ($days_taken as $day) {
                            $dateKey = $day['date'];
                            if (!isset($uniqueDays[$dateKey])) {
                                $uniqueDays[$dateKey] = $day;
                            }
                        }
                        
                        foreach ($uniqueDays as $day) {
                            \App\Models\LeaveRequestDaysTaken::create([
                                'parent_id' => $item->id,
                                'current_status_id' => 1, // Pending confirmation
                                'leave_date' => $day['date'] . ' 00:00:00',
                                'day_type_id' => $day['type'],
                            ]);
                        }
                    }
                    
                    $item->current_status_id = 24; // Partially Approved
                    $item->save();
                    
                    // Log Stage 1 approval
                    $this->logConfirmation($section, 'approve', $id, 1, [
                        'days_taken' => $isPartialDay ? [$days_taken[0]] : array_values($uniqueDays ?? $days_taken),
                        'employee_id' => $item->staff_id,
                        'leave_type_id' => $item->leave_type_id,
                        'requested_days' => $item->requested_days,
                        'is_partial_day' => $isPartialDay,
                    ]);
                    
                } elseif ($item->current_status_id == 24) {
                    // Stage 2: Second approver confirms the days taken
                    // Get first approver from logs
                    $firstApproval = ConfirmationLog::where('section', 'Leave Application')
                        ->whereJsonContains('ids', $id)
                        ->where('approval_stage', 1)
                        ->orderBy('confirmed_at', 'desc')
                        ->first();
                    
                    // Check if same person trying to approve again
                    if ($firstApproval && $firstApproval->confirmed_by == Auth::id()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'You cannot approve the same leave application twice. A different approver is required for the second stage.'
                        ], 422);
                    }
                    
                    $confirm_days = $request->input('confirm_days', false);
                    
                    if (!$confirm_days) {
                        return response()->json(['success' => false, 'error' => 'Please confirm the days taken'], 422);
                    }
                    
                    // Check if it's a partial day leave
                    $startTime = strtotime($item->start_date);
                    $endTime = strtotime($item->end_date);
                    $isSameDate = date('Y-m-d', $startTime) === date('Y-m-d', $endTime);
                    $startTimeOnly = date('H:i', $startTime);
                    $endTimeOnly = date('H:i', $endTime);
                    $hasCustomTimes = !($startTimeOnly === '08:00' && $endTimeOnly === '17:00');
                    $isPartialDay = $isSameDate && $hasCustomTimes;
                    
                    // Confirm all the days taken
                    \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)
                        ->update(['current_status_id' => 5]); // Approved
                    
                    // Calculate actual used days
                    if ($isPartialDay) {
                        // For partial day, calculate hours and convert to days
                        $hoursRequested = abs(($endTime - $startTime) / 3600);
                        $used_days = round($hoursRequested / 8, 2); // Convert hours to days
                    } else {
                        // For full days, count the actual days
                        $used_days = \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)
                            ->where('current_status_id', 5)
                            ->count();
                    }
                    
                    $item->used_days = $used_days;
                    $item->current_status_id = 5; // Approved
                    $item->save();
                    
                    // Update the leave calendar plan balance
                    $start_date_only = date('Y-m-d', strtotime($item->start_date)) . ' 00:00:00';
                    $plan = \App\Models\LeaveCalendarPlan::where('employee_id', $item->staff_id)
                        ->where('leave_type_id', $item->leave_type_id)
                        ->where('start_date', $start_date_only)
                        ->where('current_status_id', 5)
                        ->first();
                    
                    if ($plan) {
                        $plan->balance = $plan->balance - $used_days;
                        $plan->save();
                    }
                    
                    // Log Stage 2 approval
                    $this->logConfirmation($section, 'approve', $id, 2, [
                        'used_days' => $used_days,
                        'hours' => $isPartialDay ? abs(($endTime - $startTime) / 3600) : null,
                        'is_partial_day' => $isPartialDay,
                        'employee_id' => $item->staff_id,
                        'leave_type_id' => $item->leave_type_id,
                        'first_approver_id' => $firstApproval ? $firstApproval->confirmed_by : null,
                        'first_approved_at' => $firstApproval ? $firstApproval->confirmed_at : null,
                        'plan_id' => $plan ? $plan->id : null,
                        'plan_balance_before' => $plan ? ($plan->balance + $used_days) : null,
                        'plan_balance_after' => $plan ? $plan->balance : null,
                    ]);

                    // Send notification
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                } else {
                    return response()->json(['success' => false, 'error' => 'This record is no longer pending and cannot be actioned.'], 422);
                }
                break;
            case 'Leave Calendar':
                $item = \App\Models\LeaveCalendarPlan::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
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
                $item->current_status_id = 5;
                $item->save();

                \App\Models\Staff::where('id', $item->staff_id)->update(['current_status_id' => 28]); // Terminated

                // Send notification
                SendApprovalNotification::dispatch($section, $id, Auth::id());
                break;
            case 'Employee Attendance':
                $item = \App\Models\EmployeeAttendance::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 5; // Approved
                $item->save();
                break;
            case 'Communication Templates':
                $item = \App\Models\CommunicationTemplate::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id)) {
                    return $error;
                }
                $item->current_status_id = 5; // Approved
                $item->save();
                break;
            case 'Leave Adjustments':
                $adjustment = \App\Models\LeaveAdjustment::findOrFail($id);

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
                    // if ($adjustment->approved_by == Auth::id()) {
                    //     return response()->json([
                    //         'success' => false,
                    //         'error' => 'You cannot approve the same adjustment twice. A different approver is required.'
                    //     ], 422);
                    // }
                    
                    // Mark days as refunded
                    \App\Models\LeaveRequestDaysTaken::whereIn('id', $adjustment->day_ids)
                        ->update(['current_status_id' => 41]); // Refunded
                    
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
    
    public function reject(Request $request, $id)
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
                $item = \App\Models\LeaveRequest::findOrFail($id);
                if ($error = $this->assertActionableStatus($item->current_status_id, [1, 24])) {
                    return $error;
                }
                $item->current_status_id = 41;
                $item->save();
                break;
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
                $eligibleIds = \App\Models\Contract::whereIn('id', $ids)->where('current_status_id', 1)->pluck('id')->toArray();
                \App\Models\Contract::whereIn('id', $eligibleIds)->update(['current_status_id' => 5]);

                // Send notifications for each approved contract
                foreach ($eligibleIds as $id) {
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                }
                break;
            case 'Staff Discipline Tracker':
                $eligibleIds = \App\Models\DisciplineCase::whereIn('id', $ids)->where('current_status_id', 1)->pluck('id')->toArray();
                \App\Models\DisciplineCase::whereIn('id', $eligibleIds)->update(['current_status_id' => 5]);

                // Send notifications
                foreach ($eligibleIds as $id) {
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                }
                break;
            case 'Zone Assignments':
                \App\Models\ZoneAssignment::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 5]);
                break;
            case 'Leave Application':
                $items = \App\Models\LeaveRequest::whereIn('id', $ids)->get();
                $errors = [];
                $approvedIds = [];
                
                foreach ($items as $item) {
                    if ($item->current_status_id == 24) {
                        // Get first approver
                        $firstApproval = ConfirmationLog::where('section', 'Leave Application')
                            ->where('ids', 'like', '%"' . $item->id . '"%')
                            ->where('approval_stage', 1)
                            ->orderBy('confirmed_at', 'desc')
                            ->first();
                        
                        // Check if same person
                        if ($firstApproval && $firstApproval->confirmed_by == Auth::id()) {
                            $errors[] = "Cannot approve leave ID {$item->id} - same approver";
                            continue;
                        }
                        
                        // Only bulk approve stage 2 (confirmations)
                        \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)
                            ->update(['current_status_id' => 5]);
                        
                        $used_days = \App\Models\LeaveRequestDaysTaken::where('parent_id', $item->id)
                            ->where('current_status_id', 5)
                            ->count();
                        
                        $item->used_days = $used_days;
                        $item->current_status_id = 5;
                        $item->save();
                        
                        // Update leave calendar plan
                        $plan = \App\Models\LeaveCalendarPlan::where('employee_id', $item->staff_id)
                            ->where('leave_type_id', $item->leave_type_id)
                            ->where('start_date', date('Y-m-d', strtotime($item->start_date)) . ' 00:00:00')
                            ->where('current_status_id', 5)
                            ->first();

                        \App\Models\Staff::where('id', $item->staff_id)->update(['current_status_id' => 50]); // On Leave
                        
                        if ($plan) {
                            $plan->balance = $plan->balance - $used_days;
                            $plan->save();
                        }
                        
                        $approvedIds[] = $item->id;
                        
                        // Log each approval
                        $this->logConfirmation($section, 'bulk approve', $item->id, 2, [
                            'used_days' => $used_days,
                            'employee_id' => $item->staff_id,
                            'leave_type_id' => $item->leave_type_id,
                            'first_approver_id' => $firstApproval ? $firstApproval->confirmed_by : null,
                            'first_approved_at' => $firstApproval ? $firstApproval->confirmed_at : null,
                            'is_bulk_approval' => true,
                        ]);

                        // Send notification for each approved leave
                        SendApprovalNotification::dispatch($section, $item->id, Auth::id());
                    }
                }
                
                if (!empty($errors)) {
                    return response()->json([
                        'success' => false, 
                        'error' => implode(', ', $errors),
                        'approved_count' => count($approvedIds)
                    ], 422);
                }
                break;
            case 'Leave Calendar':
                \App\Models\LeaveCalendarPlan::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 5]);
                break;
            case 'Employee Termination':
                $eligibleIds = \App\Models\EmployeeTermination::whereIn('id', $ids)->where('current_status_id', 1)->pluck('id')->toArray();
                \App\Models\EmployeeTermination::whereIn('id', $eligibleIds)->update(['current_status_id' => 5]);

                $items = \App\Models\EmployeeTermination::whereIn('id', $eligibleIds)->get();
                foreach ($items as $item) {
                    \App\Models\Staff::where('id', $item->staff_id)->update(['current_status_id' => 28]); // Terminated
                }

                // Send notifications
                foreach ($eligibleIds as $id) {
                    SendApprovalNotification::dispatch($section, $id, Auth::id());
                }
                break;
            case 'Employee Attendance':
                \App\Models\EmployeeAttendance::whereIn('id', $ids)->where('current_status_id', 1)->update(['current_status_id' => 5]);
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
                \App\Models\LeaveRequest::whereIn('id', $ids)->whereIn('current_status_id', [1, 24])->update(['current_status_id' => 41]);
                break;
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
