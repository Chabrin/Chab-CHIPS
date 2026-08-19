<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Zone;
use App\Models\Staff;
use App\Models\User;
use App\Models\Contract;
use App\Models\Incident;
use App\Models\Position;
use App\Models\ZoneArea;
use App\Models\Department;
use App\Models\ActionTaken;
use App\Models\UserStaffLink;
use Illuminate\Http\Request;
use App\Models\Communication;
use App\Models\StaffDiscipline;
use App\Services\ErrorDispatcher;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeTermination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessAttendanceUpload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\EmployeeTerminationElement;
use App\Models\StaffDisciplineDocumentTracker;
use Illuminate\Validation\ValidationException;
use App\Models\EmployeeTerminationDocumentTracker;

class StaffController extends Controller
{
    /**
     * Display staff list page
     */
    public function index()
    {
        $activeStaff = Staff::with(['position', 'zone:id,zone_name', 'zonesManaged:id,zone_name,zone_manager_id', 'zonesSupervised:id,zone_name,zone_supervisor_id'])
            ->select('id', 'gender', 'staff_number', 'full_names', 'mobile_number', 'email_address', 'national_id', 'position_id', 'zone_id')
            ->orderBy('full_names', 'asc')
            ->where('current_status_id', 5)
            ->get()
            ->map(function ($member) {
                $member->encrypted_id = Crypt::encrypt($member->id);
                return $member;
            });

        $otherStaff = Staff::with(['position', 'zone:id,zone_name', 'zonesManaged:id,zone_name,zone_manager_id', 'zonesSupervised:id,zone_name,zone_supervisor_id'])
            ->select('id', 'gender', 'staff_number', 'full_names', 'mobile_number', 'email_address', 'national_id', 'position_id', 'zone_id', 'current_status_id')
            ->orderBy('full_names', 'asc')
            ->where('current_status_id', '!=', 5)
            ->get()
            ->map(function ($member) {
                $member->encrypted_id = Crypt::encrypt($member->id);
                return $member;
            });

        return view('staff.index', compact('activeStaff', 'otherStaff'));
    }

    /**
     * Update staff member status
     */
    public function updateStatus(Request $request, $encryptedId)
    {
        if (!auth()->user()->hasPermission('update_staff')) {
            abort(403);
        }

        $validStatuses = [4, 5, 28, 36];
        $validated = $request->validate([
            'status_id' => 'required|integer|in:4,5,28,36',
        ]);

        try {
            $id = Crypt::decrypt($encryptedId);
            $staff = Staff::findOrFail($id);
            $staff->current_status_id = $validated['status_id'];
            $staff->save();

            $statusLabels = [4 => 'Suspended', 5 => 'Approved', 28 => 'Terminated', 36 => 'Absconded'];

            return response()->json([
                'success' => true,
                'status_id' => $validated['status_id'],
                'status_label' => $statusLabels[$validated['status_id']],
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating staff status: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update status'], 500);
        }
    }

    /**
     * Convert a staff member to system user
     */
    public function convertToUser(Request $request, $encryptedId)
    {
        if (!auth()->user()->hasPermission('create_users')) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
            }
            abort(403);
        }

        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255|unique:josya_users,email',
                'password' => 'required|string|min:6|max:255',
            ]);

            $id = Crypt::decrypt($encryptedId);
            $staff = Staff::findOrFail($id);

            if (UserStaffLink::where('staff_id', $staff->id)->exists()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => 'This staff member is already linked to a user account.'], 422);
                }
                return redirect()->route('staff.index')->with('error', 'This staff member is already linked to a user account.');
            }

            $username = $this->buildUniqueUsername($validated['email'], $staff);

            DB::transaction(function () use ($staff, $validated, $username) {
                $user = User::create([
                    'name' => $staff->full_names,
                    'username' => $username,
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'block' => 0,
                    'sendEmail' => 0,
                    'registerDate' => now(),
                    'activation' => '',
                    'params' => '',
                    'resetCount' => 0,
                    'otpKey' => '',
                    'otep' => '',
                    'requireReset' => 0,
                ]);

                UserStaffLink::create([
                    'user_id' => $user->id,
                    'staff_id' => $staff->id,
                ]);
            });

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Staff converted to system user successfully.']);
            }
            return redirect()->route('staff.index')->with('success', 'Staff converted to system user successfully.');
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error converting staff to user: ' . $e->getMessage());
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to convert staff to user.'], 500);
            }
            return redirect()->route('staff.index')->with('error', 'Failed to convert staff to user.');
        }
    }

    private function buildUniqueUsername(string $email, Staff $staff): string
    {
        $emailBase = Str::of($email)->before('@')->lower()->value();
        $fallbackBase = Str::slug((string) $staff->full_names, '.');
        $base = trim($emailBase) !== '' ? $emailBase : ($fallbackBase !== '' ? $fallbackBase : 'user');

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $counter++;
            $username = $base . $counter;
        }

        return $username;
    }

    /**
     * Display staff details page
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $staff = Staff::with([
                'department',
                'bank',
                'position',
                'contract',
                'contract.elements.payrollElement',
                'employee_attendances.employee_attendance_timed',
                // New discipline system relationships
                'disciplineCases' => function ($query) {
                    $query->with([
                        'incidentReports',
                        'registration',
                        'investigators',
                        'showCauseNotice',
                        'employeeResponse',
                        'deadlineExtensions',
                        'hearings',
                        'outcome',
                        'activityLogs' => function ($q) {
                            $q->orderBy('performed_at', 'desc')->limit(5);
                        }
                    ])->orderBy('date_time', 'desc');
                },
                'incidentReports',
                'investigationsAssigned',
                'witnessStatements'
            ])->findOrFail($id);

            // dd($staff);

            return view('staff.details', compact('staff'));
        } catch (\Exception $e) {
            Log::error('Error fetching staff details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Staff member not found');
        }
    }

   

    /**
     * Show the form for creating new staff
     */
    public function create()
    {
        $departments = Department::orderBy('department_name')->get();
        $banks = Bank::orderBy('bank_name')->get();
        $positions = Position::orderBy('position_name')->get();
        $zones = Zone::orderBy('zone_name')->get();
        $zoneAreas = ZoneArea::orderBy('area_name')->get();

        return view('staff.create', compact('departments', 'banks', 'positions', 'zones', 'zoneAreas'));
    }

    /**
     * Store a newly created staff member
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_number' => 'required|string|max:255|unique:sys_staff,staff_number',
                'full_names' => 'required|string|max:255',
                'mobile_number' => 'nullable|string|max:255',
                'email_address' => 'required|email|max:255|unique:sys_staff,email_address',
                'national_id' => 'required|string|max:255|unique:sys_staff,national_id',
                'gender' => 'required|string',
                'birth_date' => 'required|date',
                'address' => 'nullable|string',
                'passport' => 'nullable|string|max:255',
                'department_id' => 'nullable|integer',
                'position_id' => 'nullable|integer',
                'hire_date' => 'nullable|date',
                'zone_id' => 'nullable|integer',
                'managed_zone_ids' => 'nullable|array',
                'managed_zone_ids.*' => 'integer|exists:sys_set_zones,id',
                'supervised_zone_ids' => 'nullable|array',
                'supervised_zone_ids.*' => 'integer|exists:sys_set_zones,id',
                'bank_name' => 'nullable|integer',
                'account_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:255',
                'pin_number' => 'nullable|string|max:255',
                'nhif_number' => 'nullable|string|max:255',
                'nssf_number' => 'nullable|string|max:255',
                'marital' => 'required|string',
                'spouse_name' => 'nullable|string|max:255',
                'kins_names' => 'nullable|string|max:255',
                'district' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'sub_location' => 'nullable|string|max:255',
                'birth_place' => 'nullable|string|max:255',
                'citizenship' => 'required|string',
                'personal_email' => 'nullable|email|max:255',
                'disability' => 'required|string',
            ]);

            $managedZoneIds = $validated['managed_zone_ids'] ?? [];
            $supervisedZoneIds = $validated['supervised_zone_ids'] ?? [];
            unset($validated['managed_zone_ids'], $validated['supervised_zone_ids']);
            $this->assertNoOverlappingZoneRoles($managedZoneIds, $supervisedZoneIds);

            // Uppercase names
            if (isset($validated['full_names'])) {
                $validated['full_names'] = strtoupper($validated['full_names']);
            }
            if (isset($validated['spouse_name'])) {
                $validated['spouse_name'] = strtoupper($validated['spouse_name']);
            }

            $staff = DB::transaction(function () use ($validated, $managedZoneIds, $supervisedZoneIds) {
                $staff = new Staff();
                $staff->fill($validated);
                $staff->date_time = now();
                $staff->uid = uniqid('staff_', true);
                $staff->save();

                $this->syncZoneRoles($staff, $managedZoneIds, $supervisedZoneIds);

                return $staff;
            });

            return redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Staff member created successfully');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return redirect()->back()->with('error', 'Failed to create staff member')->withInput();
        }
    }

    /**
     * A zone can't have the same staff member as both manager and supervisor.
     */
    private function assertNoOverlappingZoneRoles(array $managedZoneIds, array $supervisedZoneIds): void
    {
        $overlap = array_intersect($managedZoneIds, $supervisedZoneIds);
        if (!empty($overlap)) {
            throw ValidationException::withMessages([
                'supervised_zone_ids' => ['A zone cannot have the same staff member as both manager and supervisor.'],
            ]);
        }
    }

    /**
     * Assign this staff member as manager/supervisor of the given zones, and
     * clear them from any zone they previously held that role on but is no
     * longer selected for. Zone.zone_manager_id/zone_supervisor_id remain the
     * source of truth — this just lets the assignment be made from the staff
     * record instead of the zone settings page.
     */
    private function syncZoneRoles(Staff $staff, array $managedZoneIds, array $supervisedZoneIds): void
    {
        Zone::where('zone_manager_id', $staff->id)->whereNotIn('id', $managedZoneIds)->update(['zone_manager_id' => null]);
        if (!empty($managedZoneIds)) {
            Zone::whereIn('id', $managedZoneIds)->update(['zone_manager_id' => $staff->id]);
        }

        Zone::where('zone_supervisor_id', $staff->id)->whereNotIn('id', $supervisedZoneIds)->update(['zone_supervisor_id' => null]);
        if (!empty($supervisedZoneIds)) {
            Zone::whereIn('id', $supervisedZoneIds)->update(['zone_supervisor_id' => $staff->id]);
        }
    }

    /**
     * Show the form for editing staff
     */
    public function edit($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $staff = Staff::with(['zonesManaged', 'zonesSupervised'])->findOrFail($id);
            $departments = Department::orderBy('department_name')->get();
            $banks = Bank::orderBy('bank_name')->get();
            $positions = Position::orderBy('position_name')->get();
            $zones = Zone::orderBy('zone_name')->get();
            $zoneAreas = ZoneArea::orderBy('area_name')->get();
            return view('staff.edit', compact('staff', 'departments', 'banks', 'positions', 'zones', 'zoneAreas'));
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return redirect()->route('staff.index')->with('error', 'Staff member not found');
        }
    }

    /**
     * Update the specified staff member
     */
    public function update(Request $request, $id)
    {
        Log::info("Updating staff member with encrypted ID: " . $id);
        try {

            $validated = $request->validate([
                'staff_number' => 'required|string|max:255|unique:sys_staff,staff_number,' . $id,
                'full_names' => 'required|string|max:255',
                'mobile_number' => 'nullable|string|max:255',
                'email_address' => 'required|email|max:255|unique:sys_staff,email_address,' . $id,
                'national_id' => 'required|string|max:255|unique:sys_staff,national_id,' . $id,
                'gender' => 'required|string',
                'birth_date' => 'required|date',
                'address' => 'nullable|string',
                'passport' => 'nullable|string|max:255',
                'department_id' => 'nullable|integer',
                'position_id' => 'nullable|integer',
                'zone_id' => 'nullable|integer',
                'managed_zone_ids' => 'nullable|array',
                'managed_zone_ids.*' => 'integer|exists:sys_set_zones,id',
                'supervised_zone_ids' => 'nullable|array',
                'supervised_zone_ids.*' => 'integer|exists:sys_set_zones,id',
                'hire_date' => 'nullable|date',
                'bank_name' => 'nullable|integer',
                'account_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:255',
                'pin_number' => 'nullable|string|max:255',
                'nhif_number' => 'nullable|string|max:255',
                'nssf_number' => 'nullable|string|max:255',
                'marital' => 'required|string',
                'spouse_name' => 'nullable|string|max:255',
                'kins_names' => 'nullable|string|max:255',
                'district' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'sub_location' => 'nullable|string|max:255',
                'birth_place' => 'nullable|string|max:255',
                'citizenship' => 'required|string',
                'personal_email' => 'nullable|email|max:255',
                'disability' => 'required|string',
            ]);

            $managedZoneIds = $validated['managed_zone_ids'] ?? [];
            $supervisedZoneIds = $validated['supervised_zone_ids'] ?? [];
            unset($validated['managed_zone_ids'], $validated['supervised_zone_ids']);
            $this->assertNoOverlappingZoneRoles($managedZoneIds, $supervisedZoneIds);

            $staff = Staff::findOrFail($id);

            // Convert names to uppercase
            if (isset($validated['full_names'])) {
                $validated['full_names'] = strtoupper($validated['full_names']);
            }
            if (isset($validated['spouse_name'])) {
                $validated['spouse_name'] = strtoupper($validated['spouse_name']);
            }

            DB::transaction(function () use ($staff, $validated, $managedZoneIds, $supervisedZoneIds) {
                $staff->update($validated);
                $this->syncZoneRoles($staff, $managedZoneIds, $supervisedZoneIds);
            });

            return redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Staff member updated successfully');
        } catch (ValidationException $e) {
            Log::error('Validation error updating staff member: ' . json_encode($e->errors()));
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }

            return redirect()->back()->with('error', 'Failed to update staff member')->withInput();
        }
    }

    /**
     * Get zone areas by zone ID
     */
    public function getZoneAreas($zoneId)
    {
        try {
            $zoneAreas = ZoneArea::where('zone_id', $zoneId)
                ->orderBy('area_name')
                ->get(['id', 'area_name']);
            return response()->json($zoneAreas);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load zone areas'], 500);
        }
    }

    /**
     * Remove the specified staff member
     */
    public function destroy($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $staff = Staff::findOrFail($id);
            $staff->delete();
            return redirect()->route('staff.index')->with('success', 'Staff member deleted successfully');
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return redirect()->back()->with('error', 'Failed to delete staff member');
        }
    }


    public function contractsIndex()
    {
        return view('staff.contracts.contracts_index');
    }

    public function contractsCreate()
    {
        return view('staff.contracts.contracts_create');
    }

    public function contractsEdit($encryptedId)
    {
        $id = Crypt::decrypt($encryptedId);
        $contract = Contract::with('elements.payrollElement')->where('staff_id', $id)->first();


        return view('staff.contracts.contracts_edit', compact('contract'));
    }

    public function contractList()
    {
        try {
            $contracts = Contract::with('employee', 'position', 'createdBy', 'elements.payrollElement')
                ->whereHas('employee', function ($query) {
                    $query->where('current_status_id', 5);
                })
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($contract) {
                    // Encrypt employee ID if employee exists
                    $encrypted_id = $contract->employee ? Crypt::encrypt($contract->employee->id) : null;

                    return [
                        'id' => $contract->id,
                        'staff_id' => $contract->staff_id,
                        'staff_number' => $contract->staff_number,
                        'position_id' => $contract->position_id,
                        'date_start' => $contract->date_start ? date('Y-m-d', strtotime($contract->date_start)) : null,
                        'date_end' => $contract->date_end ? date('Y-m-d', strtotime($contract->date_end)) : null,
                        'basic_salary' => $contract->basic_salary,
                        'current_status_id' => $contract->current_status_id,
                        'created_by' => $contract->createdBy ? [
                            'id' => $contract->createdBy->id,
                            'name' => $contract->createdBy->name,
                        ] : null,
                        'employee' => $contract->employee ? [
                            'id' => $contract->employee->id,
                            'name' => $contract->employee->full_names,
                            'encrypted_id' => $encrypted_id,
                        ] : null,
                        'position' => $contract->position ? [
                            'id' => $contract->position->id,
                            'position_name' => $contract->position->position_name,
                        ] : null,
                        'elements' => $contract->elements->map(function ($element) {
                            return [
                                'id' => $element->id,
                                'element_name' => $element->payrollElement ? $element->payrollElement->element_name : null,
                                'entry_type' => $element->entry_type_id,
                                'amount' => $element->amount,
                            ];
                        }),
                        'date_time' => $contract->date_time,
                        'can_read' => Auth::user()->isAbleTo('read_contracts'),
                        'can_update' => Auth::user()->isAbleTo('update_contracts'),
                    ];
                });

            return response()->json(['contracts' => $contracts]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading payroll elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load payroll elements'], 500);
        }
    }

    public function contractStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'staff_positions' => 'required|integer',
                'date_start' => 'required|date',
                'date_end' => 'required|date|after_or_equal:date_start',
                'basic_salary' => 'required|numeric|min:0',
                'elements' => 'nullable|array',
                'elements.*.element' => 'required|integer',
                'elements.*.entry_type' => 'required|in:1,2',
                'elements.*.amount' => 'required|numeric|min:0',
            ]);


            $staff = Staff::find($validated['staff_id']);

            // Create contract
            $contract = new Contract();
            $contract->staff_id = $validated['staff_id'];
            $contract->position_id = $validated['staff_positions'];
            $contract->date_start = $validated['date_start'] . ' 00:00:00';
            $contract->date_end = $validated['date_end'] . ' 00:00:00';
            $contract->basic_salary = $validated['basic_salary'];
            $contract->current_status_id = 5; // Active
            $contract->created_by = Auth::id();
            $contract->date_time = now();
            $contract->staff_number = $staff->staff_number ?? null;
            $contract->save();

            // Store contract elements
            if (!empty($validated['elements'])) {
                foreach ($validated['elements'] as $elementData) {
                    $contract->elements()->create([
                        'element_id' => $elementData['element'],
                        'entry_type_id' => $elementData['entry_type'],
                        'amount' => $elementData['amount'],
                    ]);
                }
            }


            return redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Contract created successfully');
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading payroll elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load payroll elements'], 500);
        }
    }

    public function contractUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'staff_positions' => 'required|integer',
                'date_start' => 'required|date',
                'date_end' => 'required|date|after_or_equal:date_start',
                'basic_salary' => 'required|numeric|min:0',
                'elements' => 'nullable|array',
                'elements.*.element' => 'required|integer',
                'elements.*.entry_type' => 'required|in:1,2',
                'elements.*.amount' => 'required|numeric|min:0',
            ]);


            $contract = Contract::findOrFail($id);
            $staff = Staff::find($validated['staff_id']);

            // Update contract fields
            $contract->staff_id = $validated['staff_id'];
            $contract->position_id = $validated['staff_positions'];
            $contract->date_start = $validated['date_start'] . ' 00:00:00';
            $contract->date_end = $validated['date_end'] . ' 00:00:00';
            $contract->basic_salary = $validated['basic_salary'];
            $contract->current_status_id = 5; // Active
            $contract->created_by = Auth::id();
            $contract->date_time = now();
            $contract->staff_number = $staff->staff_number ?? null;
            $contract->save();

            // Update contract elements: delete old, insert new
            $contract->elements()->delete();

            if (!empty($validated['elements'])) {
                foreach ($validated['elements'] as $elementData) {
                    $contract->elements()->create([
                        'element_id' => $elementData['element'],
                        'entry_type_id' => $elementData['entry_type'],
                        'amount' => $elementData['amount'],
                    ]);
                }
            }

            return redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Contract updated successfully');
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating contract: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update contract'], 500);
        }
    }

    // Staff Discipline Tracker
    public function disciplineTrackerIndex()
    {
        return view('staff.discipline_tracker.index');
    }

    public function disciplineTrackerCreate()
    {
        return view('staff.discipline_tracker.create');
    }

    public function disciplineTrackerEdit($id)
    {
        $record = StaffDiscipline::with(['staff', 'position', 'incident', 'actionTaken', 'documentTrackers'])->findOrFail($id);
        return view('staff.discipline_tracker.edit', compact('record'));
    }

    public function disciplineIncidentsList()
    {
        try {
            $actions = Incident::orderBy('name')->get(['id', 'name']);
            return response()->json($actions);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load discipline actions'], 500);
        }
    }

    public function disciplineActionsTakenList()
    {
        try {
            $actions = ActionTaken::orderBy('name')->get(['id', 'name']);
            return response()->json($actions);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load discipline actions'], 500);
        }
    }

    public function incidentsStore(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:incidents,name']);
        $incident = Incident::create(['name' => $request->name]);
        return response()->json(['success' => true, 'incident' => $incident]);
    }

    public function actionTakenStore(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:action_taken,name']);
        $action = ActionTaken::create(['name' => $request->name]);
        return response()->json(['success' => true, 'action' => $action]);
    }


    public function disciplineTrackerList()
    {
        try {
            $records = StaffDiscipline::with(['staff', 'position', 'createdBy', 'incident', 'actionTaken', 'documentTrackers'])
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($rec) {
                    $encrypted_id = $rec->staff ? Crypt::encrypt($rec->staff->id) : null;

                    return [
                        'id' => $rec->id,
                        'staff_id' => $rec->staff_id,
                        'staff' => $rec->staff ? [
                            'id' => $rec->staff->id,
                            'name' => $rec->staff->full_names,
                            'encrypted_id' => $encrypted_id,
                        ] : null,
                        'position' => $rec->position ? [
                            'id' => $rec->position->id,
                            'position_name' => $rec->position->position_name,
                        ] : null,
                        'incident_date_time' => $rec->incident_date_time ? date('Y-m-d H:i', strtotime($rec->incident_date_time)) : null,
                        'incident' => $rec->incident ? [
                            'id' => $rec->incident->id,
                            'incident_name' => $rec->incident->name,
                        ] : null,
                        'action_taken' => $rec->actionTaken ? [
                            'id' => $rec->actionTaken->id,
                            'action_name' => $rec->actionTaken->name,
                        ] : null,
                        'description' => $rec->description,
                        'documents' => $rec->documentTrackers->map(function ($doc) {
                            return [
                                'id' => $doc->id,
                                'documents' => $doc->documents,
                                'params' => $doc->params,
                            ];
                        }),
                        'current_status_id' => $rec->current_status_id,
                        'created_by' => $rec->createdBy ? [
                            'id' => $rec->createdBy->id,
                            'name' => $rec->createdBy->name,
                        ] : null,
                        'date_time' => $rec->date_time,
                        'can_read' => Auth::user()->isAbleTo('read_discipline_records'),
                        'can_update' => Auth::user()->isAbleTo('update_discipline_records'),
                        'can_delete' => Auth::user()->isAbleTo('delete_discipline_records'),
                    ];
                });

            return response()->json(['records' => $records]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }

    public function disciplineTrackerStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'position_id' => 'required|integer',
                'incident_date_time' => 'required',
                'incident_id' => 'required|integer',
                'action_taken_id' => 'required|integer',
                'description' => 'nullable|string',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048', // adjust as needed
            ]);

            $record = new StaffDiscipline($validated);
            $record->date_time = now();
            $record->created_by = Auth::id();
            $record->current_status_id = 1;
            // Set flag if documents are present
            $record->documents = $request->hasFile('documents') ? 'YES' : 'NO';
            $record->save();

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline_documents', 'public');
                    StaffDisciplineDocumentTracker::create([
                        'parent_id' => $record->id,
                        'documents' => $file->getClientOriginalName(),
                        'params' => $path,
                    ]);
                }
            }

            return redirect()->route('discipline.tracker.index')->with('success', 'Contract created successfully');
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }

    public function disciplineTrackerUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer|exists:sys_staff,id',
                'position_id' => 'required|integer|exists:sys_hr_positions,id',
                'incident_date_time' => 'required|date',
                'incident_id' => 'required|integer',
                'action_taken_id' => 'required|integer',
                'description' => 'nullable|string',
                'current_status_id' => 'required|integer',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
            ]);

            $record = StaffDiscipline::findOrFail($id);
            $record->fill($validated);
            $record->documents = $request->hasFile('documents') ? 'YES' : $record->documents;
            $record->save();

            // Handle new document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline_documents', 'public');
                    StaffDisciplineDocumentTracker::create([
                        'parent_id' => $record->id,
                        'documents' => $file->getClientOriginalName(),
                        'params' => $path,
                    ]);
                }
            }

            return redirect()->route('discipline.tracker.index')->with('success', 'Record updated successfully');
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }

    public function disciplineDocumentDelete($id)
    {
        try {
            $doc = StaffDisciplineDocumentTracker::findOrFail($id);
            $parentId = $doc->parent_id;
            // Delete the file from storage
            \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->params);
            $doc->delete();

            // If no more documents remain, update parent record
            $remaining = StaffDisciplineDocumentTracker::where('parent_id', $parentId)->count();
            if ($remaining === 0) {
                $parent = \App\Models\StaffDiscipline::find($parentId);
                if ($parent) {
                    $parent->documents = 'NO';
                    $parent->save();
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }

    public function disciplineTrackerDelete($id)
    {
        try {
            $record = \App\Models\StaffDiscipline::findOrFail($id);
            $record->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }


    // Employee Termination
    public function employeeTerminationIndex()
    {
        return view('staff.employee_termination.index');
    }

    public function employeeTerminationCreate()
    {
        return view('staff.employee_termination.create');
    }

    public function employeeTerminationShow($encryptedTerminationID)
    {
        try {
            $id = Crypt::decrypt($encryptedTerminationID);
            $record = EmployeeTermination::with([
                'staff',
                'contract',
                'createdBy',
                'documentTrackers',
                'elements.element',
                'disciplineCase',
                'exitInterview',
                'finalDues',
            ])->findOrFail($id);
            return view('staff.employee_termination.show', compact('record'));
        } catch (\Exception $e) {
            Log::error('Error fetching employee termination details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Employee termination record not found');
        }
    }

    public function employeeTerminationEdit($id)
    {
        $record = EmployeeTermination::with([
            'staff',
            'contract',
            'createdBy',
            'documentTrackers',
            'elements.element'
        ])->findOrFail($id);
        return view('staff.employee_termination.edit', compact('record'));
    }

    public function employeeTerminationList()
    {
        try {
            $records = EmployeeTermination::with([
                'staff',
                'contract',
                'createdBy',
                'documentTrackers',
                'elements.element'
            ])
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($rec) {
                    $encrypted_id = $rec->staff ? Crypt::encrypt($rec->staff->id) : null;
                    $encrypted_termination_id = Crypt::encrypt($rec->id);

                    return [
                        'id' => $rec->id,
                        'date_time' => $rec->date_time ? date('Y-m-d H:i:s', strtotime($rec->date_time)) : null,
                        'staff_id' => $rec->staff_id,
                        'encrypted_termination_id' => $encrypted_termination_id,
                        'contract_id' => $rec->contract_id,
                        'years_served' => $rec->years_served,
                        'pay_per_year' => $rec->pay_per_year,
                        'severance_payment' => $rec->severerance_payment,
                        'termination_reason_id' => $rec->termination_reason_id,
                        'current_status_id' => $rec->current_status_id,
                        'staff' => $rec->staff ? [
                            'id' => $rec->staff->id,
                            'name' => $rec->staff->full_names,
                            'staff_number' => $rec->staff->staff_number,
                            'encrypted_id' => $encrypted_id,
                        ] : null,
                        'contract' => $rec->contract ? [
                            'id' => $rec->contract->id,
                            'staff_number' => $rec->contract->staff_number,
                            'basic_salary' => $rec->contract->basic_salary,
                            'position_id' => $rec->contract->position_id,
                        ] : null,
                        'created_by' => $rec->createdBy ? [
                            'id' => $rec->createdBy->id,
                            'name' => $rec->createdBy->name,
                        ] : null,
                        'documents' => $rec->documentTrackers->map(function ($doc) {
                            return [
                                'id' => $doc->id,
                                'documents' => $doc->documents,
                                'params' => $doc->params,
                            ];
                        }),
                        'elements' => $rec->elements->map(function ($el) {
                            return [
                                'id' => $el->id,
                                'element_id' => $el->element_id,
                                'element_name' => $el->element ? $el->element->element_name : null,
                                'amount' => $el->amount,
                            ];
                        }),
                        'can_read' => Auth::user()->isAbleTo('read_employee_terminations'),
                        'can_update' => Auth::user()->isAbleTo('update_employee_terminations'),
                        'can_delete' => Auth::user()->isAbleTo('delete_employee_terminations'),
                    ];
                });

            return response()->json(['records' => $records]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading employee terminations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load employee terminations'], 500);
        }
    }

    public function employeeTerminationStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'contract_id' => 'required|integer',
                'years_served' => 'required|integer|min:0',
                'pay_per_year' => 'required|numeric|min:0',
                'severance_payment' => 'required|numeric|min:0',
                'termination_reason_id' => 'required|integer',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
                'severance_elements' => 'nullable|array',
                'severance_elements.*.element_id' => 'required|integer',
                'severance_elements.*.amount' => 'required|numeric|min:0',
            ]);

            // Save main termination record
            $termination = new EmployeeTermination();
            $termination->staff_id = $validated['staff_id'];
            $termination->contract_id = $validated['contract_id'];
            $termination->years_served = $validated['years_served'];
            $termination->pay_per_year = $validated['pay_per_year'];
            $termination->severerance_payment = $validated['severance_payment'];
            $termination->termination_reason_id = $validated['termination_reason_id'];
            $termination->date_time = now();
            $termination->created_by_id = Auth::id();
            $termination->documents = $request->hasFile('documents') ? 'YES' : 'NO';
            $termination->current_status_id = 1;
            $termination->save();

            // Save documents
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('termination_documents', 'public');
                    EmployeeTerminationDocumentTracker::create([
                        'parent_id' => $termination->id,
                        'documents' => $file->getClientOriginalName(),
                        'params' => $path,
                    ]);
                }
            }

            // Save severance elements
            if (!empty($validated['severance_elements'])) {
                foreach ($validated['severance_elements'] as $element) {
                    EmployeeTerminationElement::create([
                        'parent_id' => $termination->id,
                        'element_id' => $element['element_id'],
                        'amount' => $element['amount'],
                    ]);
                }
            }

            return redirect()->route('employee.termination.index')->with('success', 'Employee termination recorded successfully');
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }

    public function terminationContractList($staffId)
    {
        try {

            $contracts = Contract::with('employee', 'position', 'createdBy', 'elements.payrollElement')
                ->where('staff_id', $staffId)
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($contract) {
                    // Encrypt employee ID if employee exists

                    return [
                        'id' => $contract->id,
                        'staff_id' => $contract->staff_id,
                        'staff_number' => $contract->staff_number,
                        'position_id' => $contract->position_id,
                        'date_start' => $contract->date_start ? date('Y-m-d', strtotime($contract->date_start)) : null,
                        'date_end' => $contract->date_end ? date('Y-m-d', strtotime($contract->date_end)) : null,
                        'basic_salary' => $contract->basic_salary,
                        'current_status_id' => $contract->current_status_id,
                        'created_by' => $contract->createdBy ? [
                            'id' => $contract->createdBy->id,
                            'name' => $contract->createdBy->name,
                        ] : null,
                        'employee' => $contract->employee ? [
                            'id' => $contract->employee->id,
                            'name' => $contract->employee->full_names,
                            'encrypted_id' => $contract->employee->id,
                        ] : null,
                        'position' => $contract->position ? [
                            'id' => $contract->position->id,
                            'position_name' => $contract->position->position_name,
                        ] : null,
                        'elements' => $contract->elements->map(function ($element) {
                            return [
                                'id' => $element->payrollElement->id,
                                'element_name' => $element->payrollElement ? $element->payrollElement->element_name : null,
                                'entry_type' => $element->entry_type_id,
                                'amount' => $element->amount,
                            ];
                        }),
                        'date_time' => $contract->date_time,

                    ];
                });


            return response()->json(['contracts' => $contracts]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading discipline records: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load discipline records'], 500);
        }
    }

    public function employeeTerminationUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'contract_id' => 'required|integer',
                'years_served' => 'required|integer|min:0',
                'pay_per_year' => 'required|numeric|min:0',
                'severance_payment' => 'required|numeric|min:0',
                'termination_reason_id' => 'required|integer',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
                'severance_elements' => 'nullable|array',
                'severance_elements.*.element_id' => 'required|integer',
                'severance_elements.*.amount' => 'required|numeric|min:0',
            ]);

            $termination = EmployeeTermination::findOrFail($id);
            $termination->staff_id = $validated['staff_id'];
            $termination->contract_id = $validated['contract_id'];
            $termination->years_served = $validated['years_served'];
            $termination->pay_per_year = $validated['pay_per_year'];
            $termination->severerance_payment = $validated['severance_payment'];
            $termination->termination_reason_id = $validated['termination_reason_id'];
            $termination->date_time = now();
            $termination->created_by_id = Auth::id();
            // If new documents are uploaded, set flag to YES
            if ($request->hasFile('documents')) {
                $termination->documents = 'YES';
            }
            $termination->save();

            // Handle new document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('termination_documents', 'public');
                    EmployeeTerminationDocumentTracker::create([
                        'parent_id' => $termination->id,
                        'documents' => $file->getClientOriginalName(),
                        'params' => $path,
                    ]);
                }
            }

            // Update severance elements: delete old, insert new
            EmployeeTerminationElement::where('parent_id', $termination->id)->delete();
            if (!empty($validated['severance_elements'])) {
                foreach ($validated['severance_elements'] as $element) {
                    EmployeeTerminationElement::create([
                        'parent_id' => $termination->id,
                        'element_id' => $element['element_id'],
                        'amount' => $element['amount'],
                    ]);
                }
            }

            return redirect()->route('employee.termination.index')->with('success', 'Employee termination updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return back()->with('error', 'Failed to update employee termination')->withInput();
        }
    }

    public function employeeTerminationDocumentDelete($id)
    {
        try {
            $doc = EmployeeTerminationDocumentTracker::findOrFail($id);
            $parentId = $doc->parent_id;
            // Delete the file from storage
            \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->params);
            $doc->delete();

            // If no more documents remain, update parent record
            $remaining = EmployeeTerminationDocumentTracker::where('parent_id', $parentId)->count();
            if ($remaining === 0) {
                $parent = EmployeeTermination::find($parentId);
                if ($parent) {
                    $parent->documents = 'NO';
                    $parent->save();
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error deleting employee termination document: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete employee termination document'], 500);
        }
    }

    // Employee Attendance
    public function attendanceIndex()
    {
        return view('staff.employee_attendance.index');
    }

    public function attendanceList()
    {
        try {
            $records = EmployeeAttendance::with(['staff', 'employee_attendance_timed', 'createdBy'])
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($att) {
                    $encrypted_id = $att->staff ? Crypt::encrypt($att->staff->id) : null;
                    return [
                        'id' => $att->id,
                        'employee_id' => $att->employee_id,
                        'staff' => $att->staff ? [
                            'id' => $att->staff->id,
                            'name' => $att->staff->full_names,
                            'staff_number' => $att->staff->staff_number,
                            'encrypted_id' => $encrypted_id,
                        ] : null,
                        'date_time' => $att->date_time ? $att->date_time->format('Y-m-d H:i') : '-',
                        'month_year' => $att->month_year ?? '-',
                        'employee_attendance_timed' => $att->employee_attendance_timed ? $att->employee_attendance_timed->map(function ($timed) {
                            return [
                                'id' => $timed->id,
                                'date' => $timed->date,
                                'time_in' => $timed->time_in,
                                'time_out' => $timed->time_out,
                                'hours' => $timed->hours,
                                'current_status_id' => $timed->current_status_id,
                            ];
                        }) : [],
                        'current_status_id' => $att->current_status_id ?? '-',
                        'created_by' => $att->createdBy ? [
                            'id' => $att->createdBy->id,
                            'name' => $att->createdBy->name,
                        ] : null,
                        'can_read' => Auth::user()->isAbleTo('read_contracts'),
                    ];
                });
            return response()->json(['records' => $records]);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return redirect()->back()->with('error', 'Failed to create staff member')->withInput();
        }
    }

    public function attendanceUpload(Request $request)
    {
        try {
            $request->validate([
                'attendance_file' => 'required|file|mimes:csv,txt'
            ]);

            $file = $request->file('attendance_file');

            // Dispatch job for background processing
            $path = $file->store('attendance_uploads'); // returns relative path
            ProcessAttendanceUpload::dispatch($path, Auth::id());

            return response()->json(['success' => true, 'message' => 'Upload started. Data will appear once processed.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return back()->with('error', 'Failed to upload attendance')->withInput();
        }
    }

    public function attendanceSampleDownload()
    {
        try {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="employee_attendance_sample.csv"',
            ];

            $sampleData = [
                ['Staff ID', 'Date', 'Month/Year', 'Time In', 'Time Out', 'Hours'],
                ['123', '2025-11-01', '11/2025', '2025-11-01 08:00', '2025-11-01 17:00', '9'],
                ['124', '2025-11-01', '11/2025', '2025-11-01 09:00', '2025-11-01 18:00', '9'],
                ['125', '2025-11-01', '11/2025', '2025-11-01 08:30', '2025-11-01 17:30', '9'],
            ];

            $callback = function () use ($sampleData) {
                $file = fopen('php://output', 'w');
                foreach ($sampleData as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return back()->with('error', 'Failed to update employee termination')->withInput();
        }
    }

    // Contracts Bulk Edit
    public function bulkEditContractsIndex()
    {
        if (!auth()->user()->hasPermission('update_contracts')) {
            abort(403);
        }

        try {
            $positions = Position::orderBy('position_name')->get(['id', 'position_name']);
        } catch (\Exception $e) {
            Log::error('Error loading positions for bulk edit: ' . $e->getMessage());
            $positions = collect();
        }

        $contractStatuses = [
            1  => 'Pending',
            5  => 'Approved',
            3  => 'Cancelled',
            22 => 'Expired',
            41 => 'Rejected',
            28 => 'Terminated',
        ];

        return view('staff.contracts.contracts_bulk_edit', compact('positions', 'contractStatuses'));
    }

    public function bulkUpdateContracts(Request $request)
    {
        if (!auth()->user()->hasPermission('update_contracts')) {
            abort(403);
        }

        $contracts = $request->input('contracts', []);

        if (empty($contracts)) {
            return response()->json(['success' => false, 'error' => 'No contracts provided'], 422);
        }

        $results = [];

        foreach ($contracts as $data) {
            $id = $data['id'] ?? null;
            if (!$id) {
                $results[] = ['id' => null, 'ok' => false, 'error' => 'Missing contract ID'];
                continue;
            }

            try {
                // Client-side already validates, but re-check server-side
                if (!empty($data['date_start']) && !empty($data['date_end'])) {
                    if (strtotime($data['date_start']) > strtotime($data['date_end'])) {
                        $results[] = ['id' => $id, 'ok' => false, 'error' => 'date_start must be before date_end'];
                        continue;
                    }
                }

                if (isset($data['basic_salary']) && $data['basic_salary'] < 0) {
                    $results[] = ['id' => $id, 'ok' => false, 'error' => 'basic_salary must be 0 or greater'];
                    continue;
                }

                $contract = Contract::findOrFail($id);

                if (!empty($data['date_start'])) {
                    $contract->date_start = $data['date_start'] . ' 00:00:00';
                }
                if (!empty($data['date_end'])) {
                    $contract->date_end = $data['date_end'] . ' 00:00:00';
                }
                if (isset($data['basic_salary']) && $data['basic_salary'] !== '') {
                    $contract->basic_salary = $data['basic_salary'];
                }
                if (!empty($data['position_id'])) {
                    $contract->position_id = $data['position_id'];
                }
                if (!empty($data['current_status_id'])) {
                    $contract->current_status_id = $data['current_status_id'];
                }

                $contract->save();
                $results[] = ['id' => $id, 'ok' => true];
            } catch (\Exception $e) {
                Log::error("Bulk contract update failed for ID {$id}: " . $e->getMessage());
                $results[] = ['id' => $id, 'ok' => false, 'error' => 'Save failed: ' . $e->getMessage()];
            }
        }

        $failCount = count(array_filter($results, fn($r) => !$r['ok']));
        return response()->json([
            'success' => $failCount < count($results),
            'results' => $results,
            'updated' => count($results) - $failCount,
            'failed'  => $failCount,
        ]);
    }

    public function bulkUpdateContractElements(Request $request, $contractId)
    {
        if (!auth()->user()->hasPermission('update_contracts')) {
            abort(403);
        }

        try {
            $contract = Contract::findOrFail($contractId);

            $elements = $request->input('elements', []);

            // Delete existing elements
            $contract->elements()->delete();

            // Insert new elements
            foreach ($elements as $element) {
                if (empty($element['element_id'])) {
                    continue;
                }
                \App\Models\ContractElement::create([
                    'parent_id'     => $contract->id,
                    'element_id'    => $element['element_id'],
                    'entry_type_id' => $element['entry_type_id'] ?? 1,
                    'amount'        => $element['amount'] ?? 0,
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error("Error updating elements for contract {$contractId}: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update elements'], 500);
        }
    }

    // Staff Communication
    public function communicationIndex()
    {
        return view('staff.communication.index');
    }

    public function communicationSend(Request $request)
    {
        try {
            $validated = $request->validate([
                'subject' => 'nullable|string|max:255',
                'message' => 'required|string',
                'comm_type' => 'required|in:email,sms',
                'attachment' => 'nullable|file|max:4096',
                'recipient_type' => 'required|in:staff,position',
                'staff_ids' => 'array',
                'staff_ids.*' => 'integer|exists:sys_staff,id',
                'position_ids' => 'array',
                'position_ids.*' => 'integer|exists:sys_hr_positions,id',
            ]);

            // Build recipients array from staff_ids or position_ids
            $recipients = [];
            if ($validated['recipient_type'] === 'staff' && !empty($validated['staff_ids'])) {
                $recipients = Staff::whereIn('id', $validated['staff_ids'])->pluck('email_address')->filter()->unique()->toArray();
            } elseif ($validated['recipient_type'] === 'position' && !empty($validated['position_ids'])) {
                $recipients = Staff::whereIn('position_id', $validated['position_ids'])->pluck('email_address')->filter()->unique()->toArray();
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('communications', 'public');
            }

            $comm = Communication::create([
                'subject' => $validated['subject'] ?? null,
                'message' => $validated['message'],
                'recipients' => json_encode($recipients),
                'type' => $validated['comm_type'],
                'recipient_type' => $validated['recipient_type'],
                'status' => 1,
                'attachment' => $attachmentPath,
                'created_by' => Auth::id(),
            ]);

            return response()->json(['success' => true, 'message' => 'Communication saved and awaiting confirmation.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving communication: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save communication'], 500);
        }
    }
}
