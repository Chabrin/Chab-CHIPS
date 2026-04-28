<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Models\Staff;
use App\Models\WorkDay;
use App\Models\NssfRate;
use App\Models\Position;
use App\Models\Property;
use App\Models\ShifRate;
use App\Models\Department;
use App\Models\HrLeaveType;
use App\Models\ChartAccount;
use Illuminate\Http\Request;
use App\Models\Communication;
use App\Models\PublicHoliday;
use App\Models\PayrollElement;
use App\Models\ZoneAssignment;
use App\Services\ErrorDispatcher;
use App\Models\ZoneAssignmentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\ZoneAssignmentProperties;
use Illuminate\Validation\ValidationException;

class HrSettingsController extends Controller
{
    // Departments CRUD
    public function departmentIndex()
    {
        return view('settings.hr.departments');
    }

    public function departmentList()
    {
        try {
            $departments = Department::all()->map(function ($department) {
                $department->can_update = Auth::user()->isAbleTo('update_departments');
                $department->can_delete = Auth::user()->isAbleTo('delete_departments');
                return $department;
            });
            return response()->json(['departments' => $departments]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load departments'], 500);
        }
    }

    public function departmentStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'department_name' => 'required|string|max:255',
            ]);

            $exists = Department::where('department_name', $validated['department_name'])->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A department with that name already exists.'], 422);
            }

            $department = new Department();
            $department->department_name = $validated['department_name'];
            $department->date_time = now();
            $department->save();

            return response()->json(['success' => true, 'department' => $department]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save department'], 500);
        }
    }

    public function departmentUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'department_name' => 'required|string|max:255',
            ]);

            $department = Department::findOrFail($id);

            $exists = Department::where('department_name', $validated['department_name'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A department with that name already exists.'], 422);
            }

            $department->department_name = $validated['department_name'];
            $department->date_time = now();
            $department->save();

            return response()->json(['success' => true, 'department' => $department]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update department'], 500);
        }
    }

    public function departmentDelete($id)
    {
        try {
            $department = Department::findOrFail($id);
            $department->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete department'], 500);
        }
    }

    // Leave Types CRUD
    public function leaveTypeIndex()
    {
        return view('settings.hr.leave-types');
    }

    public function leaveTypeList()
    {
        try {
            $leaveTypes = HrLeaveType::all()->map(function ($leaveType) {
                $leaveType->can_update = Auth::user()->isAbleTo('update_leave_types');
                $leaveType->can_delete = Auth::user()->isAbleTo('delete_leave_types');
                return $leaveType;
            });
            return response()->json(['leave_types' => $leaveTypes]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load leave types'], 500);
        }
    }

    public function leaveTypeStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'type_name' => 'required|string|max:255',
                'current_status_id' => 'required|integer',
                'entitled_days' => 'required|integer|min:0',
                'salary_rate' => 'required|numeric|min:0|max:100',
                'leave_type_id' => 'nullable|integer',
            ]);

            $exists = HrLeaveType::where('type_name', $validated['type_name'])->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A leave type with that name already exists.'], 422);
            }

            $leaveType = new HrLeaveType();
            $leaveType->type_name = $validated['type_name'];
            $leaveType->current_status_id = 5;
            $leaveType->entitled_days = $validated['entitled_days'];
            $leaveType->salary_rate = $validated['salary_rate'];
            $leaveType->leave_type_id = null;
            $leaveType->date_time = now();
            $leaveType->save();

            return response()->json(['success' => true, 'leave_type' => $leaveType]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving leave type: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save leave type'], 500);
        }
    }

    public function leaveTypeUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type_name' => 'required|string|max:255',
                'current_status_id' => 'required|integer',
                'entitled_days' => 'required|integer|min:0',
                'salary_rate' => 'required|numeric|min:0|max:100',
                'leave_type_id' => 'nullable|integer',
            ]);

            $leaveType = HrLeaveType::findOrFail($id);

            $exists = HrLeaveType::where('type_name', $validated['type_name'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A leave type with that name already exists.'], 422);
            }

            $leaveType->type_name = $validated['type_name'];
            $leaveType->current_status_id = 5;
            $leaveType->entitled_days = $validated['entitled_days'];
            $leaveType->salary_rate = $validated['salary_rate'];
            $leaveType->leave_type_id = null;
            $leaveType->date_time = now();
            $leaveType->save();

            return response()->json(['success' => true, 'leave_type' => $leaveType]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update leave type'], 500);
        }
    }

    public function leaveTypeDelete($id)
    {
        try {
            $leaveType = HrLeaveType::findOrFail($id);
            $leaveType->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete leave type'], 500);
        }
    }

    // Staff Positions CRUD
    public function positionsIndex()
    {
        return view('settings.hr.positions');
    }

    public function positionsList()
    {
        try {
            $positions = Position::with(['reportsTo', 'salaryAccount', 'staff'])
                ->orderBy('position_name')
                ->get()
                ->map(function ($position) {
                    return [
                        'id' => $position->id,
                        'encrypted_id' => Crypt::encrypt($position->id),
                        'position_name' => $position->position_name,
                        'basic_salary' => $position->basic_salary,
                        'reports_to_name' => $position->reportsTo->position_name ?? null,
                        'staff_count' => $position->staff->count(),
                        'can_update' => Auth::user()->isAbleTo('update_staff_positions'),
                        'can_read' => Auth::user()->isAbleTo('read_staff_positions'),
                    ];
                });

            return response()->json(['positions' => $positions]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load positions'], 500);
        }
    }

    public function positionsDropdowns(Request $request)
    {
        try {
            $currentId = $request->get('current_id');
            $positions = Position::where('id', '!=', $currentId)
                ->orderBy('position_name')
                ->get(['id', 'position_name']);

            $accounts = ChartAccount::orderBy('account_name')
                ->get(['id', 'account_name', 'account_number'])
                ->map(function ($account) {
                    $account->full_account = $account->account_number . ' - ' . $account->account_name;
                    return $account;
                });

            return response()->json([
                'positions' => $positions,
                'accounts' => $accounts
            ]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load dropdowns'], 500);
        }
    }

    public function positionsGet(Request $request)
    {
        try {
            $position = Position::findOrFail($request->get('id'));
            return response()->json(['position' => $position]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Position not found'], 404);
        }
    }

    public function positionsStoreAjax(Request $request)
    {
        try {
            $validated = $request->validate([
                'position_name' => 'required|string|max:255',
                'basic_salary' => 'nullable|numeric|min:0',
                'salary_account_id' => 'nullable|integer',
                'reports_to_id' => 'nullable|integer',
            ]);

            $exists = Position::where('position_name', $validated['position_name'])->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'errors' => ['position_name' => ['A position with that name already exists.']]
                ], 422);
            }

            $position = new Position();
            $position->position_name = $validated['position_name'];
            $position->basic_salary = $validated['basic_salary'] ?? null;
            $position->salary_account_id = $validated['salary_account_id'] ?? null;
            $position->reports_to_id = $validated['reports_to_id'] ?? null;
            $position->current_status_id = 5;
            $position->date_time = now();
            $position->save();

            return response()->json([
                'success' => true,
                'message' => 'Position created successfully',
                'position' => $position
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to create position'], 500);
        }
    }

    public function positionsUpdateAjax(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'position_name' => 'required|string|max:255',
                'basic_salary' => 'nullable|numeric|min:0',
                'salary_account_id' => 'nullable|integer',
                'reports_to_id' => 'nullable|integer',
            ]);

            $position = Position::findOrFail($id);

            $exists = Position::where('position_name', $validated['position_name'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'errors' => ['position_name' => ['A position with that name already exists.']]
                ], 422);
            }

            $position->position_name = $validated['position_name'];
            $position->basic_salary = $validated['basic_salary'] ?? null;
            $position->salary_account_id = $validated['salary_account_id'] ?? null;
            $position->reports_to_id = $validated['reports_to_id'] ?? null;
            $position->save();

            return response()->json([
                'success' => true,
                'message' => 'Position updated successfully',
                'position' => $position
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update position'], 500);
        }
    }

    public function positionsShow($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $position = Position::with(['reportsTo', 'salaryAccount', 'staff'])->findOrFail($id);

            // Get all payroll elements
            $allPayrollElements = PayrollElement::where('current_status_id', 5)
                ->orderBy('element_name')
                ->get();

            // Get assigned element IDs for this position
            $assignedElementIds = $position->payrollElements()->pluck('sys_hr_payroll_elements.id')->toArray();

            return view('settings.hr.position-details', compact('position', 'allPayrollElements', 'assignedElementIds'));
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return redirect()->back()->with('error', 'Position not found');
        }
    }

    public function positionsUpdateElements(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $position = Position::findOrFail($id);

            $validated = $request->validate([
                'elements' => 'array',
                'elements.*' => 'integer|exists:sys_hr_payroll_elements,id',
            ]);

            $elementIds = $validated['elements'] ?? [];

            // Sync the payroll elements for this position
            $position->payrollElements()->sync($elementIds);

            return response()->json([
                'success' => true,
                'message' => 'Payroll elements updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payroll elements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Workdays Settings
    public function workdaysIndex()
    {
        return view('settings.hr.workdays');
    }

    public function workDaysList()
    {
        try {
            $workdays = WorkDay::with('createdBy')->get()->map(function ($workday) {
                return [
                    'id' => $workday->id,
                    'week_day' => $workday->week_day,
                    'work_type_id' => $workday->work_type_id,
                    'current_status_id' => $workday->current_status_id,
                    'date_time' => $workday->date_time,
                    'created_by' => $workday->createdBy ? [
                        'id' => $workday->createdBy->id,
                        'name' => $workday->createdBy->name,
                        'username' => $workday->createdBy->username,
                        'email' => $workday->createdBy->email,
                    ] : null,
                    // Add permission flags if needed
                    'can_update' => Auth::user()->isAbleTo('update_workdays'),
                    'can_delete' => Auth::user()->isAbleTo('delete_workdays'),
                ];
            });

            return response()->json(['workdays' => $workdays]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load workdays'], 500);
        }
    }

    public function workDaysStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'week_day' => 'required|string|max:50',
                'work_type_id' => 'required|integer',
            ]);

            $exists = WorkDay::where('week_day', $validated['week_day'])->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A workday for that week day already exists.'], 422);
            }

            $workday = new WorkDay();
            $workday->week_day = $validated['week_day'];
            $workday->work_type_id = $validated['work_type_id'];
            $workday->current_status_id = 5; // Active
            $workday->created_by = Auth::id();
            $workday->date_time = now();
            $workday->save();

            return response()->json(['success' => true, 'workday' => $workday]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save workday'], 500);
        }
    }

    public function workDaysUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'week_day' => 'required|string|max:50',
                'work_type_id' => 'required|integer',
            ]);

            $workday = WorkDay::findOrFail($id);

            $exists = WorkDay::where('week_day', $validated['week_day'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A workday for that week day already exists.'], 422);
            }

            $workday->week_day = $validated['week_day'];
            $workday->work_type_id = $validated['work_type_id'];
            $workday->current_status_id = 5; // Active
            $workday->save();

            return response()->json(['success' => true, 'workday' => $workday]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update workday'], 500);
        }
    }

    public function workDaysDelete($id)
    {
        try {
            $workday = WorkDay::findOrFail($id);
            $workday->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete workday'], 500);
        }
    }


    // Public Holidays Settings
    public function publicHolidaysIndex()
    {
        return view('settings.hr.publicholidays');
    }

    public function publicHolidaysList()
    {
        try {
            $holidays = PublicHoliday::with('createdBy')->orderBy('start_date', 'asc')->get()->map(function ($holiday) {
                // Ensure dates are in 'YYYY-MM-DD' format for frontend
                $start_date = $holiday->start_date ? substr($holiday->start_date, 0, 10) : null;
                $end_date = $holiday->end_date ? substr($holiday->end_date, 0, 10) : null;

                // Ensure recurrent is always 1 or 2 (int)
                $recurrent = (int) $holiday->recurrent;

                return [
                    'id' => $holiday->id,
                    'title' => $holiday->title,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'pay_rate' => $holiday->pay_rate,
                    'recurrent' => $recurrent,
                    'created_by' => $holiday->createdBy ? [
                        'id' => $holiday->createdBy->id,
                        'name' => $holiday->createdBy->name,
                    ] : null,
                    'current_status_id' => $holiday->current_status_id,
                    'created_at' => $holiday->date_time,
                    'can_update' => Auth::user()->isAbleTo('update_public_holiday'),
                    'can_delete' => Auth::user()->isAbleTo('delete_public_holiday'),
                ];
            });

            return response()->json(['publicholidays' => $holidays]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load public holidays'], 500);
        }
    }

    public function publicHolidaysStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'pay_rate' => 'required|numeric|min:0|max:100',
                'recurrent' => 'required|in:1,2',
            ]);

            $holiday = new PublicHoliday();
            $holiday->title = $validated['title'];
            $holiday->start_date = $validated['start_date'] . ' 00:00:00';
            $holiday->end_date = $validated['end_date'] . ' 00:00:00';
            $holiday->pay_rate = $validated['pay_rate'];
            $holiday->recurrent = $validated['recurrent'];
            $holiday->current_status_id = 5;
            $holiday->created_by_id = Auth::id();
            $holiday->date_time = now();
            $holiday->save();

            return response()->json(['success' => true, 'publicholiday' => $holiday]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save public holiday'], 500);
        }
    }

    public function publicHolidaysUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'pay_rate' => 'required|numeric|min:0|max:100',
                'recurrent' => 'required|in:yes,no',
            ]);

            $holiday = PublicHoliday::findOrFail($id);
            $holiday->title = $validated['title'];
            $holiday->start_date = $validated['start_date'];
            $holiday->end_date = $validated['end_date'];
            $holiday->pay_rate = $validated['pay_rate'];
            $holiday->recurrent = $validated['recurrent'];
            $holiday->save();

            return response()->json(['success' => true, 'publicholiday' => $holiday]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update public holiday'], 500);
        }
    }

    public function publicHolidaysDelete($id)
    {
        try {
            $holiday = PublicHoliday::findOrFail($id);
            $holiday->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete public holiday'], 500);
        }
    }

    // Shift Rates Settings
    public function shifRatesIndex()
    {
        return view('settings.hr.shif_rates');
    }

    public function shifRatesList()
    {
        try {
            $rates = ShifRate::with('createdBy')->orderBy('from_date', 'desc')->get()->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'rate' => $rate->rate,
                    'from_date' => $rate->from_date ? substr($rate->from_date, 0, 10) : null,
                    'current_status_id' => $rate->current_status_id,
                    'created_by' => $rate->createdBy ? [
                        'id' => $rate->createdBy->id,
                        'name' => $rate->createdBy->name,
                    ] : null,
                    'date_time' => $rate->date_time,
                    'can_update' => Auth::user()->isAbleTo('update_shif_rates'),
                    'can_delete' => Auth::user()->isAbleTo('delete_shif_rates'),
                ];
            });

            return response()->json(['shiftrates' => $rates]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load shift rates'], 500);
        }
    }

    public function shifRatesStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'rate' => 'required|numeric|min:0|max:100',
                'from_date' => 'required|date',
            ]);

            $rate = new ShifRate();
            $rate->rate = $validated['rate'];
            $rate->from_date = $validated['from_date'] . ' 00:00:00';
            $rate->current_status_id = 5;
            $rate->created_by_id = Auth::id();
            $rate->date_time = now();
            $rate->save();

            return response()->json(['success' => true, 'shiftrate' => $rate]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save shift rate'], 500);
        }
    }

    public function shifRatesUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rate' => 'required|numeric|min:0|max:100',
                'from_date' => 'required|date',
            ]);

            $rate = ShifRate::findOrFail($id);
            $rate->rate = $validated['rate'];
            $rate->from_date = $validated['from_date'] . ' 00:00:00';
            $rate->save();

            return response()->json(['success' => true, 'shiftrate' => $rate]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update shift rate'], 500);
        }
    }

    public function shifRatesDelete($id)
    {
        try {
            $rate = ShifRate::findOrFail($id);
            $rate->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete shift rate'], 500);
        }
    }

    // NSSF Rates Settings
    public function nssfRatesIndex()
    {
        return view('settings.hr.nssf_rates');
    }

    public function nssfRatesList()
    {
        try {
            $rates = NssfRate::with('createdBy')->orderBy('from_date', 'desc')->get()->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'from_date' => $rate->from_date ? substr($rate->from_date, 0, 10) : null,
                    'base_id' => $rate->base_id,
                    'employee_perc' => $rate->employee_perc,
                    'employer_perc' => $rate->employer_perc,
                    'maximum_contribution' => $rate->maximum_contribution,
                    'taxable' => $rate->taxable,
                    'current_status_id' => $rate->current_status_id,
                    'created_by' => $rate->createdBy ? [
                        'id' => $rate->createdBy->id,
                        'name' => $rate->createdBy->name,
                    ] : null,
                    'date_time' => $rate->date_time,
                    'can_update' => Auth::user()->isAbleTo('update_nssf_rates'),
                    'can_delete' => Auth::user()->isAbleTo('delete_nssf_rates'),
                ];
            });

            return response()->json(['nssfrates' => $rates]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load NSSF rates'], 500);
        }
    }

    public function nssfRatesStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'rate' => 'required|numeric|min:0|max:100',
                'from_date' => 'required|date',
                'base_id' => 'nullable|integer',
                'employee_perc' => 'required|numeric|min:0|max:100',
                'employer_perc' => 'required|numeric|min:0|max:100',
                'maximum_contribution' => 'required|numeric|min:0',
                'taxable' => 'required|boolean',
            ]);

            $rate = new NssfRate();
            $rate->rate = $validated['rate'];
            $rate->from_date = $validated['from_date'] . ' 00:00:00';
            $rate->base_id = $validated['base_id'] ?? null;
            $rate->employee_perc = $validated['employee_perc'];
            $rate->employer_perc = $validated['employer_perc'];
            $rate->maximum_contribution = $validated['maximum_contribution'];
            $rate->taxable = $validated['taxable'];
            $rate->current_status_id = 5;
            $rate->created_by_id = Auth::id();
            $rate->date_time = now();
            $rate->save();

            return response()->json(['success' => true, 'nssfrate' => $rate]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save NSSF rate'], 500);
        }
    }

    public function nssfRatesUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rate' => 'required|numeric|min:0|max:100',
                'from_date' => 'required|date',
                'base_id' => 'nullable|integer',
                'employee_perc' => 'required|numeric|min:0|max:100',
                'employer_perc' => 'required|numeric|min:0|max:100',
                'maximum_contribution' => 'required|numeric|min:0',
                'taxable' => 'required|boolean',
            ]);

            $rate = NssfRate::findOrFail($id);
            $rate->rate = $validated['rate'];
            $rate->from_date = $validated['from_date'] . ' 00:00:00';
            $rate->base_id = $validated['base_id'] ?? null;
            $rate->employee_perc = $validated['employee_perc'];
            $rate->employer_perc = $validated['employer_perc'];
            $rate->maximum_contribution = $validated['maximum_contribution'];
            $rate->taxable = $validated['taxable'];
            $rate->save();

            return response()->json(['success' => true, 'nssfrate' => $rate]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update NSSF rate'], 500);
        }
    }

    public function nssfRatesDelete($id)
    {
        try {
            $rate = NssfRate::findOrFail($id);
            $rate->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete NSSF rate'], 500);
        }
    }

    // NSSF Rates Settings
    public function zoneAssignmentsIndex()
    {
        return view('settings.hr.zone_assignments');
    }

    public function zoneAssignmentsList()
    {
        try {
            $assignments = ZoneAssignment::with(['createdBy', 'staff', 'assignment_type', 'zone'])
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($assignment) {
                    $zone = $assignment->zone;
                    if (!$zone && $assignment->zone_id) {
                        $zone = Zone::find($assignment->zone_id);
                    }

                    $assignmentType = $assignment->assignment_type;
                    $assignmentTypeName = null;
                    $assignmentTypeId = null;

                    if ($assignmentType) {
                        $assignmentTypeName = $assignmentType->type_name;
                        $assignmentTypeId = $assignmentType->id;
                    } elseif (is_numeric($assignment->assigned_as)) {
                        $role = DB::table('cb_roles')->where('id', $assignment->assigned_as)->first();
                        if ($role) {
                            $assignmentTypeName = $role->display_name;
                            $assignmentTypeId = $role->id;
                        }
                    }

                    if (!$assignmentTypeName) {
                        $assignmentTypeName = $assignment->assigned_as;
                    }

                    return [
                        'id' => $assignment->id,
                        'zone_id' => $assignment->zone_id,
                        'assigned_as' => $assignment->assigned_as,
                        'assignment_type' => $assignmentTypeName
                            ? [
                                'id' => $assignmentTypeId,
                                'type_name' => $assignmentTypeName,
                            ]
                            : null,
                        'staff_id' => $assignment->staff_id,
                        'fo_ids' => $assignment->fo_ids,
                        'properties_ids' => $assignment->properties_ids,
                        'current_status_id' => $assignment->current_status_id,
                        'created_by' => $assignment->createdBy ? [
                            'id' => $assignment->createdBy->id,
                            'name' => $assignment->createdBy->name,
                        ] : null,
                        'staff' => $assignment->staff ? [
                            'id' => $assignment->staff->id,
                            'name' => $assignment->staff->full_names,
                        ] : null,
                        'zone' => $zone ? [
                            'id' => $zone->id,
                            'zone_name' => $zone->zone_name,
                        ] : null,
                        'date_time' => $assignment->date_time,
                        'can_update' => Auth::user()->isAbleTo('update_zone_assignments'),
                        'can_delete' => Auth::user()->isAbleTo('delete_zone_assignments'),
                    ];
                });
            return response()->json(['zone_assignments' => $assignments]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load zone assignments'], 500);
        }
    }

    /**
     * Check whether the given assigned_as value refers to the field_officer role.
     * Resolves against cb_roles (used by the dropdown) with a fallback to ZoneAssignmentType.
     */
    private function isFieldOfficerRole($assignedAs): bool
    {
        $role = DB::table('cb_roles')->where('id', $assignedAs)->first();
        if ($role && strtolower($role->name) === 'field_officer') {
            return true;
        }

        $type = ZoneAssignmentType::find($assignedAs);
        if ($type && strtolower($type->type_name) === 'field officer') {
            return true;
        }

        return false;
    }

    /**
     * Get all role identifiers that represent the field_officer role
     * across cb_roles and ZoneAssignmentType tables.
     */
    private function getFieldOfficerRoleIds(): array
    {
        $ids = [];

        $cbIds = DB::table('cb_roles')
            ->where('name', 'field_officer')
            ->pluck('id')
            ->toArray();
        $ids = array_merge($ids, $cbIds);

        $typeIds = ZoneAssignmentType::whereRaw('LOWER(type_name) = ?', ['field officer'])
            ->pluck('id')
            ->toArray();
        $ids = array_merge($ids, $typeIds);

        return array_values(array_unique($ids));
    }

    public function zoneAssignmentsStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'zone_id' => 'required|integer|exists:sys_set_zones,id',
                'assigned_as' => 'required|string|max:255',
                'staff_id' => 'required|integer|exists:sys_staff,id',
                'properties_ids' => 'required|array',
                'properties_ids.*' => 'integer|exists:sys_pm_properties,id',
            ]);

            // Validate: field officers cannot be assigned to multiple zones
            if ($this->isFieldOfficerRole($validated['assigned_as'])) {
                $existingAssignments = ZoneAssignment::where('staff_id', $validated['staff_id'])
                    ->where('assigned_as', $validated['assigned_as'])
                    ->where('zone_id', '!=', $validated['zone_id'])
                    ->exists();

                if ($existingAssignments) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Field officers cannot be assigned to multiple zones. This staff member is already assigned to another zone.'
                    ], 422);
                }
            }

            // Convert properties array to comma-separated string
            $propertiesString = implode(',', $validated['properties_ids']);

            $assignment = new ZoneAssignment();
            $assignment->zone_id = $validated['zone_id'];
            $assignment->assigned_as = $validated['assigned_as'];
            $assignment->staff_id = $validated['staff_id'];
            $assignment->properties_ids = $propertiesString;
            $assignment->current_status_id = 1;
            $assignment->created_by = Auth::id();
            $assignment->date_time = now();
            $assignment->save();

            return response()->json(['success' => true, 'message' => 'Zone assignment created successfully', 'zone_assignment' => $assignment]);
        } catch (ValidationException $e) {
            Log::warning('Zone assignment validation error:', $e->errors());
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Zone assignment store error: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save zone assignment: ' . $e->getMessage()], 500);
        }
    }

    public function zoneAssignmentsUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'zone_id' => 'required|integer|exists:sys_set_zones,id',
                'assigned_as' => 'required|string|max:255',
                'staff_id' => 'required|integer|exists:sys_staff,id',
                'properties_ids' => 'required|array',
                'properties_ids.*' => 'integer|exists:sys_pm_properties,id',
            ]);

            $assignment = ZoneAssignment::findOrFail($id);

            // Validate: field officers cannot be assigned to multiple zones
            if ($this->isFieldOfficerRole($validated['assigned_as'])) {
                $existingAssignments = ZoneAssignment::where('staff_id', $validated['staff_id'])
                    ->where('assigned_as', $validated['assigned_as'])
                    ->where('zone_id', '!=', $validated['zone_id'])
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existingAssignments) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Field officers cannot be assigned to multiple zones. This staff member is already assigned to another zone.'
                    ], 422);
                }
            }

            // Convert properties array to comma-separated string
            $propertiesString = implode(',', $validated['properties_ids']);

            $assignment->zone_id = $validated['zone_id'];
            $assignment->assigned_as = $validated['assigned_as'];
            $assignment->staff_id = $validated['staff_id'];
            $assignment->properties_ids = $propertiesString;
            $assignment->save();

            return response()->json(['success' => true, 'message' => 'Zone assignment updated successfully', 'zone_assignment' => $assignment]);
        } catch (ValidationException $e) {
            Log::warning('Zone assignment validation error:', $e->errors());
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Zone assignment update error: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'assignment_id' => $id
            ]);
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update zone assignment: ' . $e->getMessage()], 500);
        }
    }

    public function zoneAssignmentsDelete($id)
    {
        try {
            $assignment = ZoneAssignment::findOrFail($id);
            $assignment->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete zone assignment'], 500);
        }
    }

    public function zoneAssignmentView($id)
    {
        $assignment = ZoneAssignment::with('createdBy', 'staff', 'assignment_type', 'zone')->findOrFail($id);

        $propertyIds = [];
        if (!empty($assignment->properties_ids)) {
            $propertyIds = array_filter(array_map('intval', array_map('trim', explode(',', $assignment->properties_ids))));
        }

        $properties = !empty($propertyIds)
            ? Property::whereIn('id', $propertyIds)->orderBy('reference_number')->get()
            : collect();

        return view('settings.hr.zone_assignment_view', compact('assignment', 'properties'));
    }

    public function zoneAssignmentUnassignProperty(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'property_id' => 'required|integer|exists:sys_pm_properties,id',
            ]);

            $assignment = ZoneAssignment::findOrFail($id);

            $currentIds = !empty($assignment->properties_ids)
                ? array_filter(array_map('trim', explode(',', $assignment->properties_ids)))
                : [];

            $remaining = array_values(array_filter($currentIds, function ($pid) use ($validated) {
                return (int) $pid !== (int) $validated['property_id'];
            }));

            if (count($remaining) === count($currentIds)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Property is not part of this assignment.'
                ], 422);
            }

            if (empty($remaining)) {
                $assignment->delete();
                return response()->json([
                    'success' => true,
                    'deleted' => true,
                    'message' => 'Last property unassigned. Assignment removed.'
                ]);
            }

            $assignment->properties_ids = implode(',', $remaining);
            $assignment->save();

            return response()->json([
                'success' => true,
                'deleted' => false,
                'message' => 'Property unassigned successfully.',
                'remaining_count' => count($remaining)
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to unassign property'], 500);
        }
    }

    public function assignmentTypesList()
    {
        try {
            $assignmentTypes = ZoneAssignmentType::all();
            return response()->json(['success' => true, 'assignment_types' => $assignmentTypes]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch assignment types'], 500);
        }
    }

    public function zonesList()
    {
        try {
            $zones = Zone::all();
            return response()->json(['success' => true, 'zones' => $zones]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch zones'], 500);
        }
    }

    public function staffList()
    {
        try {
            $staff = Staff::with('position:id,position_name')
                ->select('id', 'full_names', 'position_id')
                ->get()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'full_names' => $employee->full_names,
                        'position' => $employee->position ? $employee->position->position_name : null
                    ];
                });

            return response()->json([
                'success' => true,
                'staff' => $staff
            ]);
            return response()->json(['success' => true, 'staff' => $staff]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch staff'], 500);
        }
    }

    public function propertiesByZone($zoneId)
    {
        try {
            $properties = Property::where('zone_id', $zoneId)
                ->select('id', 'reference_number', 'description')
                ->get();

            return response()->json(['success' => true, 'properties' => $properties]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch properties for the zone'], 500);
        }
    }

    public function rolesList()
    {
        try {
            $roles = DB::table('cb_roles')
                ->whereIn('name', [
                    'field_officer',
                    'zone_manager',
                    'senior_field_officer',
                    'property_manager'
                ])
                ->select('id', 'name')
                ->get();

            return response()->json(['success' => true, 'roles' => $roles]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch roles'], 500);
        }
    }

    public function availablePropertiesInZone(Request $request)
    {
        try {
            $zoneId = $request->input('zone_id');
            $assignedAs = $request->input('assigned_as');
            $staffId = $request->input('staff_id');

            if (!$zoneId || !$assignedAs) {
                return response()->json(['success' => false, 'error' => 'Zone and Role are required'], 400);
            }

            // Get all properties in the zone
            $allPropertiesInZone = Property::where('zone_id', $zoneId)
                ->pluck('id')
                ->toArray();

            if (empty($allPropertiesInZone)) {
                return response()->json(['success' => true, 'properties' => []]);
            }

            // For field officers, exclude properties assigned to ANY other field officer
            // (across all field_officer role identifiers). For other roles, only exclude
            // assignments under the same role.
            if ($this->isFieldOfficerRole($assignedAs)) {
                $excludedRoles = $this->getFieldOfficerRoleIds();
                if (empty($excludedRoles)) {
                    $excludedRoles = [$assignedAs];
                }
                $assignedQuery = DB::table('sys_pm_zones_assignments')
                    ->where('zone_id', $zoneId)
                    ->whereIn('assigned_as', $excludedRoles);
            } else {
                $assignedQuery = DB::table('sys_pm_zones_assignments')
                    ->where('zone_id', $zoneId)
                    ->where('assigned_as', $assignedAs);
            }

            $assignedProperties = $assignedQuery
                ->when($staffId, function ($query) use ($staffId) {
                    // Exclude current staff's own assignments
                    return $query->where('staff_id', '!=', $staffId);
                })
                ->select('properties_ids')
                ->distinct()
                ->pluck('properties_ids')
                ->toArray();

            // Parse comma-separated property IDs
            $assignedPropIds = [];
            foreach ($assignedProperties as $propIds) {
                if ($propIds) {
                    $ids = array_map('trim', explode(',', $propIds));
                    $assignedPropIds = array_merge($assignedPropIds, $ids);
                }
            }
            $assignedPropIds = array_unique(array_map('intval', $assignedPropIds));

            // Get available properties (not assigned for this role)
            $availablePropIds = array_diff($allPropertiesInZone, $assignedPropIds);

            // Fetch property details
            $properties = Property::whereIn('id', $availablePropIds)
                ->select('id', 'reference_number', 'property_name')
                ->orderBy('reference_number')
                ->get();

            return response()->json(['success' => true, 'properties' => $properties]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to fetch available properties'], 500);
        }
    }


    public function communicationTemplatesIndex()
    {
        return view('settings.hr.templates.index');
    }

    public function communicationsList()
    {
        try {
            $communications = Communication::orderBy('sent_at', 'desc')
                ->get()
                ->map(function ($comm) {
                    return [
                        'id' => $comm->id,
                        'subject' => $comm->subject,
                        'message' => $comm->message,
                        'recipients' => $comm->recipients,
                        'type' => $comm->type,
                        'recipient_type' => $comm->recipient_type,
                        'status' => $comm->status,
                        'attachment' => $comm->attachment,
                        'created_by' => $comm->created_by,
                        'send_status' => $comm->send_status,
                        'sent_at' => $comm->sent_at,
                        'send_error' => $comm->send_error,
                    ];
                });

            return response()->json(['success' => true, 'communications' => $communications]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to load communications'], 500);
        }
    }

    public function communicationsShow($id)
    {
        try {
            $communication = Communication::findOrFail($id);
            return response()->json(['success' => true, 'communication' => $communication]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Communication not found'], 404);
        }
    }

    public function communicationTemplatesCreate()
    {
        return view('settings.hr.templates.create');
    }

    public function communicationTemplatesEdit($id)
    {
        $decrypted_id = Crypt::decrypt($id);
        $template = CommunicationTemplate::findOrFail($decrypted_id);
        return view('settings.hr.templates.edit', compact('template'));
    }

    public function communicationTemplatesShow($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $template = CommunicationTemplate::with('createdBy')->findOrFail($id);
            return view('settings.hr.templates.show', compact('template'));
        } catch (\Exception $e) {
            return redirect()->route('settings.hr.communication.templates')->with('error', 'Template not found');
        }
    }

    public function communicationTemplatesDeleteFile($id)
    {
        try {
            $template = CommunicationTemplate::findOrFail($id);

            if ($template->attachment_path && Storage::disk('public')->exists($template->attachment_path)) {
                Storage::disk('public')->delete($template->attachment_path);
                $template->attachment_path = null;
                $template->save();

                return response()->json(['success' => true]);
            }

            return response()->json(['success' => false, 'error' => 'No file found']);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error deleting file: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete file'], 500);
        }
    }

    public function communicationTemplatesList()
    {
        try {
            $templates = CommunicationTemplate::with('createdBy')
                ->orderBy('template_type')
                ->orderBy('template_name')
                ->get()
                ->map(function ($template) {
                    $encrypt_template_id = Crypt::encrypt($template->id);
                    return [
                        'id' => $template->id,
                        'encrypt_template_id' => $encrypt_template_id,
                        'template_name' => $template->template_name,
                        'template_type' => $template->template_type,
                        'communication_method' => $template->communication_method,
                        'subject' => $template->subject,
                        'body' => $template->body,
                        'general_config' => $template->general_config,
                        'attachment_path' => $template->attachment_path,
                        'current_status_id' => $template->current_status_id,
                        'created_by' => $template->createdBy ? [
                            'id' => $template->createdBy->id,
                            'name' => $template->createdBy->name,
                        ] : null,
                        'date_time' => $template->date_time,
                        'can_update' => Auth::user()->isAbleTo('update_communication_templates'),
                        'can_delete' => Auth::user()->isAbleTo('delete_communication_templates'),
                        'can_read' => Auth::user()->isAbleTo('read_communication_templates'),
                    ];
                });

            return response()->json(['templates' => $templates]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load communication templates'], 500);
        }
    }

    public function communicationTemplatesStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'template_name' => 'required|string|max:255',
                'template_type' => 'required',
                'communication_method' => 'required|in:email,sms',
                'subject' => 'nullable|string',
                'body' => 'required|string',
                'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240', // 10MB max
                'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240', // For general templates
                'general_config' => 'nullable|json',
            ]);

            $template = new CommunicationTemplate();
            $template->template_name = $validated['template_name'];
            $template->template_type = $validated['template_type'];
            $template->communication_method = $validated['communication_method'];
            $template->subject = $validated['subject'];
            $template->body = $validated['body'];

            // Handle general communication config
            if ($validated['template_type'] === 'general' && $request->has('general_config')) {
                $template->general_config = json_decode($validated['general_config'], true);
            }

            // Handle file upload - for non-general templates
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $templateType = str_replace('_', '-', $validated['template_type']); // contract-termination
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("communication_templates/{$templateType}", $filename, 'public');
                $template->attachment_path = $path;
            }

            // Handle attachment upload - for general templates
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('communication_templates/general', $filename, 'public');
                $template->attachment_path = $path;
            }

            $template->current_status_id = 1;
            $template->created_by_id = Auth::id();
            $template->date_time = now();
            $template->save();

            return response()->json(['success' => true, 'template' => $template]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving template: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save template'], 500);
        }
    }

    public function communicationTemplatesUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'template_name' => 'required|string|max:255',
                'template_type' => 'required|in:general,contract,leave_application,contract_termination,disciplinary_action',
                'communication_method' => 'required|in:email,sms',
                'subject' => 'nullable|string',
                'body' => 'required|string',
                'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
                'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
                'general_config' => 'nullable|json',
            ]);

            $template = CommunicationTemplate::findOrFail($id);
            $template->template_name = $validated['template_name'];
            $template->template_type = $validated['template_type'];
            $template->communication_method = $validated['communication_method'];
            $template->subject = $validated['subject'];
            $template->body = $validated['body'];
            $template->current_status_id = 1;

            // Handle general communication config
            if ($validated['template_type'] === 'general' && $request->has('general_config')) {
                $template->general_config = json_decode($validated['general_config'], true);
            } else if ($validated['template_type'] !== 'general') {
                $template->general_config = null;
            }

            // Handle file upload - for non-general templates
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($template->attachment_path && Storage::disk('public')->exists($template->attachment_path)) {
                    Storage::disk('public')->delete($template->attachment_path);
                }

                $file = $request->file('file');
                $templateType = str_replace('_', '-', $validated['template_type']);
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("communication_templates/{$templateType}", $filename, 'public');
                $template->attachment_path = $path;
            }

            // Handle attachment upload - for general templates
            if ($request->hasFile('attachment')) {
                // Delete old file if exists
                if ($template->attachment_path && Storage::disk('public')->exists($template->attachment_path)) {
                    Storage::disk('public')->delete($template->attachment_path);
                }

                $file = $request->file('attachment');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('communication_templates/general', $filename, 'public');
                $template->attachment_path = $path;
            }

            $template->save();

            return response()->json(['success' => true, 'template' => $template]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating template: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update template'], 500);
        }
    }

    public function communicationTemplatesDelete($id)
    {
        try {
            $template = CommunicationTemplate::findOrFail($id);

            // Delete associated file if exists
            if ($template->attachment_path && Storage::disk('public')->exists($template->attachment_path)) {
                Storage::disk('public')->delete($template->attachment_path);
            }

            $template->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error deleting template: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete template'], 500);
        }
    }
}
