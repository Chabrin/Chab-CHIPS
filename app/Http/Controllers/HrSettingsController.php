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
use App\Services\ErrorDispatcher;
use App\Models\ZoneAssignmentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            $canUpdate = Auth::user()->isAbleTo('update_staff_positions');
            $canRead = Auth::user()->isAbleTo('read_staff_positions');
            $canDelete = Auth::user()->isAbleTo('delete_staff_positions');

            $positions = Position::with(['reportsTo', 'salaryAccount'])
                ->withCount('staff')
                ->orderBy('position_name')
                ->get()
                ->map(function ($position) use ($canUpdate, $canRead, $canDelete) {
                    return [
                        'id' => $position->id,
                        'encrypted_id' => Crypt::encrypt($position->id),
                        'position_name' => $position->position_name,
                        'slug_name' => $position->slug_name,
                        'basic_salary' => $position->basic_salary,
                        'reports_to_name' => $position->reportsTo->position_name ?? null,
                        'staff_count' => $position->staff_count,
                        'can_update' => $canUpdate,
                        'can_read' => $canRead,
                        'can_delete' => $canDelete,
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
            $position->slug_name = Str::slug($validated['position_name'], '_');
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

    public function positionsDestroyAjax($id)
    {
        try {
            $position = Position::findOrFail($id);

            $staffCount = Staff::where('position_id', $id)->count();
            if ($staffCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'This position cannot be deleted because it is assigned to ' . $staffCount . ' staff member(s).'
                ], 422);
            }

            $reportsToCount = Position::where('reports_to_id', $id)->count();
            if ($reportsToCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'This position cannot be deleted because other positions report to it.'
                ], 422);
            }

            $position->delete();

            return response()->json([
                'success' => true,
                'message' => 'Position deleted successfully'
            ]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete position'], 500);
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

    /**
     * Field Officers report — one row per staff member who is set as
     * field_officer_id on at least one property, with their property count.
     * Read directly off sys_pm_properties; no separate assignment table involved.
     */
    public function fieldOfficersReport()
    {
        try {
            $rows = DB::table('sys_pm_properties as p')
                ->join('sys_staff as s', 's.id', '=', 'p.field_officer_id')
                ->select('s.id', 's.full_names', 's.mobile_number', 's.email_address', DB::raw('COUNT(p.id) as properties_count'))
                ->whereNotNull('p.field_officer_id')
                ->groupBy('s.id', 's.full_names', 's.mobile_number', 's.email_address')
                ->orderBy('s.full_names')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading field officers report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load field officers'], 500);
        }
    }

    /**
     * Zone Managers report — one row per staff member set as zone_manager_id
     * on at least one property, with property count and distinct field-officer count.
     */
    public function zoneManagersReport()
    {
        try {
            $rows = DB::table('sys_pm_properties as p')
                ->join('sys_staff as s', 's.id', '=', 'p.zone_manager_id')
                ->select(
                    's.id',
                    's.full_names',
                    's.mobile_number',
                    's.email_address',
                    DB::raw('COUNT(p.id) as properties_count'),
                    DB::raw('COUNT(DISTINCT p.field_officer_id) as field_officers_count')
                )
                ->whereNotNull('p.zone_manager_id')
                ->groupBy('s.id', 's.full_names', 's.mobile_number', 's.email_address')
                ->orderBy('s.full_names')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading zone managers report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load zone managers'], 500);
        }
    }

    /**
     * Zone Supervisors report — same shape as Field Officers.
     */
    public function zoneSupervisorsReport()
    {
        try {
            $rows = DB::table('sys_pm_properties as p')
                ->join('sys_staff as s', 's.id', '=', 'p.zone_supervisor_id')
                ->select('s.id', 's.full_names', 's.mobile_number', 's.email_address', DB::raw('COUNT(p.id) as properties_count'))
                ->whereNotNull('p.zone_supervisor_id')
                ->groupBy('s.id', 's.full_names', 's.mobile_number', 's.email_address')
                ->orderBy('s.full_names')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading zone supervisors report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load zone supervisors'], 500);
        }
    }

    /**
     * Property Managers report — staff holding the "PROPERTY MANAGER" position.
     * Properties don't carry a property_manager_id column, so this is listed by
     * staff position rather than a per-property count.
     */
    public function propertyManagersReport()
    {
        try {
            $rows = DB::table('sys_staff as s')
                ->join('sys_set_staff_positions as pos', 'pos.id', '=', 's.position_id')
                ->select('s.id', 's.full_names', 's.mobile_number', 's.email_address', 'pos.position_name')
                ->where('pos.position_name', 'ILIKE', 'PROPERTY MANAGER')
                ->orderBy('s.full_names')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property managers report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load property managers'], 500);
        }
    }

    /**
     * Properties assigned to a given staff member under a given zone-assignment
     * role column (field_officer_id / zone_manager_id / zone_supervisor_id).
     * Backs the "Details" drill-down modal on the zone assignments report tabs.
     */
    public function zoneAssignmentStaffProperties(Request $request)
    {
        try {
            $columnMap = [
                'field_officer' => 'field_officer_id',
                'zone_manager' => 'zone_manager_id',
                'zone_supervisor' => 'zone_supervisor_id',
            ];

            $type = $request->input('type');
            $staffId = $request->input('staff_id');

            if (!isset($columnMap[$type]) || !$staffId) {
                return response()->json(['error' => 'Invalid role type or staff id'], 400);
            }

            $column = $columnMap[$type];

            $rows = DB::table('sys_pm_properties as p')
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'p.client_id')
                ->leftJoin('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
                ->select(
                    'p.id',
                    'p.property_name',
                    'p.reference_number',
                    'c.names as client_name',
                    'z.zone_name'
                )
                ->where("p.$column", $staffId)
                ->orderBy('p.reference_number')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading staff properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load properties'], 500);
        }
    }

    /**
     * Properties with no field officer assigned — the "Unallocated" tab on the
     * zone assignments report page.
     */
    public function unallocatedPropertiesReport()
    {
        try {
            $rows = DB::table('sys_pm_properties as p')
                ->leftJoin('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
                ->leftJoin('josya_users as zm', 'zm.id', '=', 'z.zone_manager_id')
                ->leftJoin('josya_users as zs', 'zs.id', '=', 'z.zone_supervisor_id')
                ->select(
                    'p.id',
                    'p.property_name',
                    'p.reference_number',
                    'z.zone_name',
                    'zm.name as zone_manager_name',
                    'zs.name as zone_supervisor_name'
                )
                ->whereNull('p.field_officer_id')
                ->orderBy('p.reference_number')
                ->get();

            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading unallocated properties: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load unallocated properties'], 500);
        }
    }

    /**
     * Current field officer for a property, plus the zone manager/supervisor of
     * the property's zone (read strictly from sys_set_zones/josya_users — display
     * only, not editable from this modal). Used to prefill the Allocate modal.
     */
    public function propertyAllocationDetails($id)
    {
        try {
            $row = DB::table('sys_pm_properties as p')
                ->leftJoin('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
                ->leftJoin('sys_staff as fo', 'fo.id', '=', 'p.field_officer_id')
                ->leftJoin('josya_users as zm', 'zm.id', '=', 'z.zone_manager_id')
                ->leftJoin('josya_users as zs', 'zs.id', '=', 'z.zone_supervisor_id')
                ->select(
                    'p.id',
                    'p.property_name',
                    'p.reference_number',
                    'z.zone_name',
                    'p.field_officer_id',
                    'fo.full_names as field_officer_name',
                    'zm.name as zone_manager_name',
                    'zs.name as zone_supervisor_name'
                )
                ->where('p.id', $id)
                ->first();

            if (!$row) {
                return response()->json(['error' => 'Property not found'], 404);
            }

            return response()->json(['data' => $row]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading property allocation details: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load property details'], 500);
        }
    }

    /**
     * Set/change the field officer on a property. Zone manager/supervisor are
     * zone-level attributes (sys_set_zones) — not editable from here.
     */
    public function allocatePropertyZoneRoles(Request $request, $id)
    {
        try {
            $property = Property::withAll()->withInactive()->findOrFail($id);

            $validated = $request->validate([
                'field_officer_id' => 'required|integer|exists:sys_staff,id',
            ]);

            $property->update($validated);

            return response()->json(['success' => true, 'message' => 'Property allocated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error allocating property: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to allocate property'], 500);
        }
    }

    /**
     * Bulk-reassign a set of already-allocated properties to a different
     * field officer in one action. Updated one at a time (not a single
     * whereIn(...)->update()) so AuditLogsObserver still fires per property,
     * matching the audit trail a single allocatePropertyZoneRoles() call
     * would leave.
     */
    public function bulkReassignFieldOfficer(Request $request)
    {
        try {
            $validated = $request->validate([
                'property_ids' => 'required|array|min:1',
                'property_ids.*' => 'integer|exists:sys_pm_properties,id',
                'field_officer_id' => 'required|integer|exists:sys_staff,id',
            ]);

            DB::transaction(function () use ($validated) {
                $properties = Property::withAll()->withInactive()
                    ->whereIn('id', $validated['property_ids'])
                    ->get();

                foreach ($properties as $property) {
                    $property->update(['field_officer_id' => $validated['field_officer_id']]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Properties reassigned successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk reassigning field officer: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to reassign properties'], 500);
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

    public function staffList(Request $request)
    {
        try {
            $query = Staff::with(['position:id,position_name', 'zone:id,zone_name'])
                ->select('id', 'full_names', 'position_id', 'zone_id');

            if ($request->filled('zone_id')) {
                $query->where('zone_id', $request->input('zone_id'));
            }

            if ($request->filled('position_slug')) {
                $positionIds = Position::where('slug_name', $request->input('position_slug'))->pluck('id');
                $query->whereIn('position_id', $positionIds);
            }

            $staff = $query->get()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'full_names' => $employee->full_names,
                        'position' => $employee->position ? $employee->position->position_name : null,
                        'zone_name' => $employee->zone->zone_name ?? null,
                    ];
                });

            return response()->json([
                'success' => true,
                'staff' => $staff
            ]);
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
