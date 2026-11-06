<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Role;
use App\Models\Zone;
use App\Models\Staff;
use App\Models\StaffOnboardingInvitation;
use App\Exports\StaffExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Models\PayrollElement;
use App\Services\NssfCalculator;
use App\Services\EmployeeUploadGuard;
use App\Services\EmployeeContractExpiry;
use App\Services\EmployeeContractReport;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractChange;
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
use App\Exports\AttendanceExport;
use App\Models\EmployeeTermination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessAttendanceUpload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\EmployeeTerminationElement;
use App\Models\StaffDisciplineDocumentTracker;
use Illuminate\Validation\ValidationException;
use App\Traits\HandlesStaffSignature;
use App\Models\EmployeeTerminationDocumentTracker;

class StaffController extends Controller
{
    use HandlesStaffSignature;

    public function __construct(private NssfCalculator $nssfCalculator) {
    }

    /** Friendlier field names for validation messages on the banking & statutory section. */
    private const STATUTORY_ATTRIBUTES = [
        'bank_name' => 'bank',
        'account_name' => 'account name',
        'account_number' => 'account number',
        'pin_number' => 'KRA PIN number',
        'nhif_number' => 'SHA number',
        'nssf_number' => 'NSSF number',
        'login_email' => 'login email',
        'documents.national_id' => 'National ID / Passport copy',
        'documents.kra_pin' => 'KRA PIN certificate',
        'photo' => 'staff photo',
        'documents.contract' => 'signed employment contract',
        'documents.cv' => 'CV',
        'documents.academic' => 'academic certificates',
    ];

    private const UPLOAD_MESSAGES = [
        'photo.uploaded' => 'The :attribute could not be received. Check the upload limit shown beside the field and choose a smaller file.',
        'documents.*.uploaded' => 'The :attribute could not be received. Check the upload limit shown beside the field and choose a smaller file.',
    ];

    private const DOCUMENT_MAP = [
        'national_id' => 'National ID / Passport',
        'kra_pin'     => 'KRA PIN Certificate',
        'contract'    => 'Signed Employment Contract',
        'cv'          => 'Curriculum Vitae (CV)',
        'academic'    => 'Academic Certificates & Transcripts',
    ];
    /**
     * Display staff list page
     */
    public function index()
    {
        $staff = Staff::with(['position', 'zone:id,zone_name', 'zonesManaged:id,zone_name,zone_manager_id', 'zonesSupervised:id,zone_name,zone_supervisor_id'])
            ->select('id', 'gender', 'staff_number', 'full_names', 'mobile_number', 'email_address', 'national_id', 'position_id', 'zone_id', 'current_status_id')
            ->orderBy('full_names', 'asc')
            ->get()
            ->map(function ($member) {
                $member->encrypted_id = Crypt::encrypt($member->id);
                return $member;
            });

        return view('staff.index', compact('staff'));
    }

    /**
     * Export all staff records to an Excel file.
     */
    public function export() {
        $fileName = 'staff_records_' . now()->format('Y_m_d_His') . '.xlsx';
        return Excel::download(new StaffExport, $fileName);
    }

    /**
     * Update staff member status
     */
    public function updateStatus(Request $request, $encryptedId)
    {
        if (!auth()->user()->hasPermission('update_staff')) {
            abort(403);
        }

        $validated = $request->validate([
            'status_id' => 'required|integer|in:' . implode(',', array_keys(Staff::STATUSES)),
        ]);

        try {
            $id = Crypt::decrypt($encryptedId);
            $staff = Staff::findOrFail($id);
            $staff->current_status_id = $validated['status_id'];
            $staff->save();

            return response()->json([
                'success' => true,
                'status_id' => $validated['status_id'],
                'status_label' => Staff::STATUSES[$validated['status_id']],
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
     * NSSF Tier I / Tier II breakdown for a payroll element at a given gross pay (empty for non-NSSF elements).
     */
    public function elementBreakdown(Request $request, $id) {
        $element = PayrollElement::findOrFail($id);

        if ((int) $element->formula_id !== (int) config('nssf.formula_id')) {
            return response()->json(['tiers' => [], 'total' => 0]);
        }

        return response()->json(
            $this->nssfCalculator->calculate((float) $request->query('gross', 0))
        );
    }

    /**
     * Check the elements submitted for a contract and turn them into rows to save. A contract holds only recurring
     * items: one-time items are entered on Monthly Payroll Items, and the items the payroll run calculates itself
     * (NSSF, SHA, Housing Levy, PAYE) and the basic and net salary elements are never kept on a contract. The entry
     * type comes from the element, since that is what the run uses.
     *
     * @param array $elements rows with 'element' (the element id) and 'amount'
     * @return array<int, array{element_id: int, entry_type_id: int, amount: mixed}>
     * @throws ValidationException
     */
    private function contractElementRows(array $elements): array {
        $found = PayrollElement::whereIn('id', array_column($elements, 'element'))->get()->keyBy('id');
        $rows = [];
        $errors = [];

        foreach ($elements as $row) {
            $element = $found->get((int) $row['element']);

            if (!$element) {
                $errors[] = 'One of the selected elements no longer exists.';
            } elseif ($element->isOneTime()) {
                $errors[] = "{$element->element_name} is a one-time item. Enter it on Monthly Payroll Items instead of the contract.";
            } elseif ($element->isStatutory()) {
                $errors[] = "{$element->element_name} is calculated by the payroll run for every staff member, so it is not added to a contract.";
            } elseif ($element->isSystem()) {
                $errors[] = "{$element->element_name} comes from the contract's basic salary, so it is not added as an element.";
            } elseif (isset($rows[$element->id])) {
                $errors[] = "{$element->element_name} is added more than once.";
            } else {
                $rows[$element->id] = ['element_id' => $element->id, 'entry_type_id' => (int) $element->element_type, 'amount' => $row['amount']];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['elements' => array_values(array_unique($errors))]);
        }

        return array_values($rows);
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

            // NSSF Tier I / Tier II for the contract's gross pay (basic + allowances)
            $nssfBreakdown = $staff->contract
                ? $this->nssfCalculator->calculate((float) $staff->contract->basic_salary + (float) $staff->contract->elements->where('entry_type_id', 2)->sum('amount'))
                : null;

            return view('staff.details', compact('staff', 'nssfBreakdown'));
        } catch (\Exception $e) {
            Log::error('Error fetching staff details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Staff member not found');
        }
    }

   

    /**
     * Show the form for creating new staff updated on 27th 
     */
   public function create(Request $request)
    {
        $departments = Department::orderBy('department_name')->get();
        $banks = Bank::orderBy('bank_name')->get();
        $positions = Position::orderBy('position_name')->get();
        $zones = Zone::orderBy('zone_name')->get();
        $zoneAreas = ZoneArea::orderBy('area_name')->get();
        $roles = Role::orderBy('display_name')->get();

        // Coming from an approved onboarding submission: prefill what the new staff member entered
        $onboarding = null;
        if ($request->filled('onboarding')) {
            try {
                $onboarding = $this->reviewedOnboarding((int) $request->query('onboarding'));
            } catch (ValidationException $e) {
                return redirect()->route('staff.onboarding.index')->with('error', $e->validator->errors()->first());
            }
        }
        $prefill = [];
        $onboardingFiles = [];
        if ($onboarding) {
            $prefill = collect($onboarding->submission)->except(['onboarding_documents', 'staff_photo_path'])->all();
            // Company details from invitations sent before HR moved them onto this form
            foreach (['employment_type', 'department_id', 'position_id', 'hire_date', 'zone_id', 'role_id'] as $field) {
                if (filled($onboarding->$field)) $prefill[$field] = $onboarding->$field;
            }
            $onboardingFiles = $onboarding->submittedFiles();
        }

        // Default to Permanent on initial load
        $defaultCategory = $prefill['employment_type'] ?? 'Permanent';
        $nextStaffNumber = Staff::nextStaffNumber($defaultCategory);

        return view('staff.create', compact('departments', 'banks', 'positions', 'zones', 'zoneAreas', 'roles', 'nextStaffNumber', 'onboarding', 'prefill', 'onboardingFiles'));
    }

    /** An onboarding submission HR has approved but not yet turned into a staff record. */
    private function reviewedOnboarding(int $id, bool $lock = false): StaffOnboardingInvitation
    {
        $query = StaffOnboardingInvitation::whereKey($id);
        if ($lock) $query->lockForUpdate();
        $invitation = $query->first();
        if (!$invitation || $invitation->status !== 'reviewed') {
            throw ValidationException::withMessages(['onboarding_invitation_id' => 'This onboarding submission is not awaiting a staff record. It may already have been used or cancelled.']);
        }
        return $invitation;
    }

    /**
     * Get the next category-specific staff number via AJAX updated on 27th
     */
    public function getStaffNumber(Request $request)
    {
        $category = $request->input('category', 'Permanent');
        $staffNumber = Staff::nextStaffNumber($category);

        return response()->json([
            'success' => true,
            'staff_number' => $staffNumber
        ]);
    }
    

    /**
     * Store a newly created staff member, optionally creating a linked system-user login
     * when the requester has the create_users permission and fills in the password field.
     */
   public function store(Request $request)
    {
        try {
            if (!auth()->user()->hasPermission('create_users')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'You do not have permission to create this staff member. Your form has been kept.'], 403);
                }
                return redirect()->back()->with('error', 'You need the create users permission to add staff, because permanent and temporary staff members get a system login.')->withInput();
            }

            // Files the staff member already uploaded through an approved onboarding link stand in
            // for the required uploads; anything HR uploads here replaces the onboarding copy.
            $onboarding = $request->filled('onboarding_invitation_id') ? $this->reviewedOnboarding((int) $request->input('onboarding_invitation_id')) : null;
            $submittedFiles = $onboarding ? $onboarding->submittedFiles() : [];
            $onboardingSignature = $this->hasSignatureInput($request) ? null : ($submittedFiles['signature'] ?? null);
            unset($submittedFiles['signature']);
            $carriedFiles = array_filter($submittedFiles, fn ($file, $field) => !$request->hasFile($field), ARRAY_FILTER_USE_BOTH);
            $requiredUpload = fn (string $field) => isset($carriedFiles[$field]) ? 'nullable' : 'required';

            $validated = $request->validate($this->signatureRules() + [
                'full_names' => 'required|string|max:255',
                'employment_type' => 'required|string|in:Permanent,Temporary,Casual,Intern', //edited on 27th
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
                'bank_name' => 'required|integer',
                'account_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:255',
                'pin_number' => 'required|string|max:255',
                'nhif_number' => 'required|string|max:255',
                'nssf_number' => 'required|string|max:255',
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
                'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'documents' => 'nullable|array',
                'documents.national_id' => $requiredUpload('documents.national_id') . '|file|mimes:pdf,png,jpg,jpeg|max:5120',
                'documents.kra_pin'     => $requiredUpload('documents.kra_pin') . '|file|mimes:pdf,png,jpg,jpeg|max:5120',
                'documents.contract'    => 'nullable|file|mimes:pdf|max:5120',
                'documents.cv'          => 'nullable|file|mimes:pdf,doc,docx|max:5120',
                'documents.academic'    => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
                'onboarding_invitation_id' => 'nullable|integer',
                // Make login details required ONLY if not Casual
                'login_email' => 'required_unless:employment_type,Casual|nullable|email|max:255',
                'role_id' => 'required_unless:employment_type,Casual|nullable|integer|exists:cb_roles,id',
                'password' => 'nullable|string|min:8|max:255',
            ], self::UPLOAD_MESSAGES, self::STATUTORY_ATTRIBUTES);

            $this->validateStaffUploads($request, null, $carriedFiles, $onboarding?->id);
            $isCasual = $validated['employment_type'] === 'Casual';

            $managedZoneIds = $validated['managed_zone_ids'] ?? [];
            $supervisedZoneIds = $validated['supervised_zone_ids'] ?? [];
            unset($validated['managed_zone_ids'], $validated['supervised_zone_ids']);
            $this->assertNoOverlappingZoneRoles($managedZoneIds, $supervisedZoneIds);

            // Uploaded files and the system-login fields are handled separately below, not mass-assigned to the staff record
            $userValidated = [
                'email' => $validated['login_email'] ?? null,
                'role_id' => $validated['role_id'] ?? null,
                'password' => $validated['password'] ?? null,
            ];
            unset($validated['photo'], $validated['documents'], $validated['login_email'], $validated['role_id'], $validated['password'], $validated['onboarding_invitation_id'], $validated['signature_file'], $validated['signature_data']);

            $validated = $this->applyPositionDepartment($validated);

            // Uppercase names
            if (isset($validated['full_names'])) {
                $validated['full_names'] = strtoupper($validated['full_names']);
            }
            if (isset($validated['spouse_name'])) {
                $validated['spouse_name'] = strtoupper($validated['spouse_name']);
            }

            // Permanent and Temporary staff get a system login. A random password is generated when left blank.
            $generatedPassword = null;
            if (!$isCasual) {
                if (empty($userValidated['password'])) {
                    $generatedPassword = Str::password(12);
                    $userValidated['password'] = $generatedPassword;
                }

                if (User::where('email', $userValidated['email'])->exists()) {
                    throw ValidationException::withMessages([
                        'login_email' => ['This email is already registered as a system user login.'],
                    ]);
                }
            }

            $staff = DB::transaction(function () use ($request, $validated, $managedZoneIds, $supervisedZoneIds, $userValidated, $generatedPassword, $isCasual, $onboarding, $carriedFiles, $onboardingSignature) {
                // Lock the submission so a double submit cannot create two staff records from it
                if ($onboarding) $this->reviewedOnboarding($onboarding->id, true);

                $staff = new Staff();
                $staff->fill($validated);
                $staff->date_time = now();
                // Pass employment_type to generate category-specific staff numbers (PER, TMP, CAS)
                $staff->staff_number = Staff::nextStaffNumber($validated['employment_type']);
                $staff->uid = uniqid('staff_', true);
                $staff->current_status_id = 1;
                $staff->save();

                // Save the profile photo
                if ($request->hasFile('photo')) {
                    $staff->photo = $request->file('photo')->store('staff_photos', 'public');
                    $staff->save();
                }

                // Save the uploaded documents (REQ-01)
                $this->storeStaffDocuments($request, $staff);
                $signature = $this->storeSignature($request, "staff_signatures/{$staff->id}");
                if (!$signature && $onboardingSignature) {
                    $signature = "staff_signatures/{$staff->id}/" . basename($onboardingSignature['path']);
                    if (!Storage::disk('local')->copy($onboardingSignature['path'], $signature)) {
                        throw new \RuntimeException('Could not save the onboarding signature.');
                    }
                }
                if ($signature) {
                    $staff->signature = $signature;
                    $staff->save();
                }
                if ($onboarding) {
                    $this->attachOnboardingFiles($staff, $carriedFiles);
                    $onboarding->update(['status' => 'approved', 'staff_id' => $staff->id, 'submission' => null]);
                }

                $this->syncZoneRoles($staff, $managedZoneIds, $supervisedZoneIds);

                // ONLY create a system user if the staff member is NOT Casual
                if (!$isCasual) {
                    $username = $this->buildUniqueUsername($userValidated['email'], $staff);

                    $user = User::create([
                        'name' => $staff->full_names,
                        'username' => $username,
                        'email' => $userValidated['email'],
                        'password' => $userValidated['password'],
                        'block' => 0,
                        'sendEmail' => 0,
                        'registerDate' => now(),
                        'activation' => '',
                        'params' => '',
                        'resetCount' => 0,
                        'otpKey' => '',
                        'otep' => '',
                        'requireReset' => $generatedPassword ? 1 : 0,
                        'staff_id' => $staff->id,
                    ]);

                    DB::table('cb_role_user')->insert([
                        'user_id' => $user->id,
                        'role_id' => $userValidated['role_id'],
                    ]);

                    UserStaffLink::create([
                        'user_id' => $user->id,
                        'staff_id' => $staff->id,
                    ]);
                }

                return $staff;
            });

            if ($onboarding) {
                Storage::disk('local')->deleteDirectory("staff_onboarding/{$onboarding->id}");
            }

            $redirect = redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Staff member created successfully');
            if ($generatedPassword) {
                $redirect->with('generated_password', $generatedPassword);
            }
            if ($request->expectsJson()) {
                return response()->json(['redirect_url' => $redirect->getTargetUrl()]);
            }
            return $redirect;
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please correct the highlighted fields. Your selected files have been kept.', 'errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Staff create failed: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to save the staff member. Your form and selected files have been kept.'], 500);
            }
            return redirect()->back()->with('error', 'Failed to create staff member')->withInput();
        }
    }
    /**
     * Save the documents uploaded on the create-staff form (REQ-01).
     */
    private function validateStaffUploads(Request $request, ?int $staffId = null, array $carriedFiles = [], ?int $ignoreInvitationId = null): void
    {
        $fingerprints = [];
        $files = ['photo' => $request->file('photo')];
        foreach (array_keys(self::DOCUMENT_MAP) as $key) {
            $files["documents.$key"] = $request->file("documents.$key");
        }
        foreach ($files as $field => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $fingerprints[$field] = hash_file('sha256', $file->getRealPath());
            }
        }
        // Onboarding files being moved onto this record are checked the same way as fresh uploads
        foreach ($carriedFiles as $field => $file) {
            if (!Storage::disk('local')->exists($file['path'])) {
                throw ValidationException::withMessages([$field => 'The file submitted through onboarding could not be found. Upload it here instead.']);
            }
            $fingerprints[$field] = hash_file('sha256', Storage::disk('local')->path($file['path']));
        }
        app(EmployeeUploadGuard::class)->validateHashes($fingerprints, $staffId, $ignoreInvitationId);
    }

    /**
     * Show a staff member's signature image (stored privately, so it is served through here).
     */
    public function signature($id)
    {
        abort_unless(auth()->user()->hasPermission('read_staff'), 403);
        $staff = Staff::findOrFail($id);
        abort_unless($staff->signature && Storage::disk('local')->exists($staff->signature), 404);

        return Storage::disk('local')->response($staff->signature, 'signature.' . pathinfo($staff->signature, PATHINFO_EXTENSION), [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Copy the photo and documents submitted through onboarding onto the new staff record.
     * The onboarding originals are removed after the transaction commits.
     */
    private function attachOnboardingFiles(Staff $staff, array $carriedFiles): void
    {
        $types = DocumentType::pluck('id', 'name');

        foreach ($carriedFiles as $field => $file) {
            if ($field === 'photo') {
                $staff->photo = 'staff_photos/' . basename($file['path']);
                if (!Storage::disk('public')->put($staff->photo, Storage::disk('local')->get($file['path']))) {
                    throw new \RuntimeException('Could not save staff profile photo.');
                }
                $staff->save();
                continue;
            }

            if (!isset($types[$file['type_name']])) {
                throw new \RuntimeException("Document type missing: {$file['type_name']}");
            }
            $path = "employee_documents/{$staff->id}/" . basename($file['path']);
            if (!Storage::disk('local')->copy($file['path'], $path)) {
                throw new \RuntimeException('Could not move onboarding document into the staff document store.');
            }
            EmployeeDocument::create([
                'employee_id' => $staff->id,
                'document_type_id' => $types[$file['type_name']],
                'title' => $file['type_name'],
                'file_path' => $path,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'mime_type' => $file['mime'],
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    private function storeStaffDocuments(Request $request, Staff $staff): void {
        $types = DocumentType::pluck('id', 'name');

        foreach (self::DOCUMENT_MAP as $key => $typeName) {
            $file = $request->file("documents.$key");
            if (!$file || !$file->isValid()) {
                continue;
            }
            if (!isset($types[$typeName])) {
                throw new \RuntimeException("Document type missing: $typeName");
            }

            $this->saveEmployeeDocument($staff, $types[$typeName], $typeName, $file);
        }
    }

    /**
     * Attach the physically signed contract (PDF) to a contract, replacing any earlier upload for that contract.
     */
    private function saveSignedContract(Contract $contract, UploadedFile $file): void {
        app(EmployeeUploadGuard::class)->validate(['signed_contract' => $file], (int) $contract->staff_id);

        $typeId = DocumentType::where('name', 'Signed Employment Contract')->value('id');
        if (!$typeId) {
            throw new \RuntimeException('Document type missing: Signed Employment Contract');
        }

        $staff = Staff::findOrFail($contract->staff_id);
        $previous = EmployeeDocument::where('contract_id', $contract->id)->get();

        $document = $this->saveEmployeeDocument($staff, $typeId, 'Signed contract ' . date('d M Y', strtotime($contract->date_start)) . ' - ' . date('d M Y', strtotime($contract->date_end)), $file);
        $document->update(['contract_id' => $contract->id]);

        foreach ($previous as $old) {
            Storage::disk('local')->delete($old->file_path);
            $old->delete();
        }
    }

    /**
     * Store one uploaded file on the private disk and record it against the staff member.
     */
    private function saveEmployeeDocument(Staff $staff, int $typeId, string $title, UploadedFile $file): EmployeeDocument {
        return EmployeeDocument::create([
            'employee_id' => $staff->id,
            'document_type_id' => $typeId,
            'title' => $title,
            'file_path' => $file->store("employee_documents/{$staff->id}", 'local'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => (int) round($file->getSize() / 1024),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => auth()->id(),
        ]);
    }

/**
 * Apply photo/document changes from the edit form.
 * Returns the old files to delete once the DB transaction has committed.
 */
private function syncStaffFiles(Request $request, Staff $staff): array
{
    $obsolete = [];

    if ($request->hasFile('photo')) {
        if ($staff->photo) {
            $obsolete[] = ['public', $staff->photo];
        }
        $staff->photo = $request->file('photo')->store('staff_photos', 'public');
        $staff->save();
    }

    $types    = DocumentType::pluck('id', 'name');
    $existing = EmployeeDocument::where('employee_id', $staff->id)->get()->groupBy('document_type_id');
    $remove   = $request->input('remove_documents', []);

    foreach (self::DOCUMENT_MAP as $key => $typeName) {
        $typeId     = $types[$typeName] ?? null;
        $file       = $request->file("documents.$key");
        $hasNewFile = $file && $file->isValid();

        if ($hasNewFile && !$typeId) {
            throw new \RuntimeException("Document type missing: $typeName");
        }

        if ($typeId && ($hasNewFile || in_array($key, $remove, true))) {
            foreach ($existing->get($typeId, collect()) as $current) {
                $obsolete[] = ['local', $current->file_path];
                $current->delete();
            }
        }

        if ($hasNewFile) {
            $this->saveEmployeeDocument($staff, $typeId, $typeName, $file);
        }
    }

    return $obsolete;
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
        $staff = Staff::with(['zonesManaged', 'zonesSupervised', 'employeeDocuments'])->findOrFail($id);
        $departments = Department::orderBy('department_name')->get();
        $banks = Bank::orderBy('bank_name')->get();
        $positions = Position::orderBy('position_name')->get();
        $zones = Zone::orderBy('zone_name')->get();
        $zoneAreas = ZoneArea::orderBy('area_name')->get();

        $types = DocumentType::pluck('id', 'name');
        $documentSlots = collect(self::DOCUMENT_MAP)->map(function ($typeName, $key) use ($types, $staff) {
            $typeId = $types[$typeName] ?? null;
            return [
                'key'   => $key,
                'label' => $typeName,
                'doc'   => $typeId ? $staff->employeeDocuments->firstWhere('document_type_id', $typeId) : null,
            ];
        })->values();

        return view('staff.edit', compact('staff', 'departments', 'banks', 'positions', 'zones', 'zoneAreas', 'documentSlots'));
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
    Log::info("Updating staff member with ID: " . $id);
    try {

        $validated = $request->validate($this->signatureRules() + [
            'remove_signature' => 'nullable|boolean',
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
            'bank_name' => 'required|integer',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'pin_number' => 'required|string|max:255',
            'nhif_number' => 'required|string|max:255',
            'nssf_number' => 'required|string|max:255',
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
            'photo'                 => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'documents'             => 'nullable|array',
            'documents.national_id' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'documents.kra_pin'     => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'documents.contract'    => 'nullable|file|mimes:pdf|max:5120',
            'documents.cv'          => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'documents.academic'    => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'remove_documents'      => 'nullable|array',
            'remove_documents.*'    => 'in:' . implode(',', array_keys(self::DOCUMENT_MAP)),
        ], self::UPLOAD_MESSAGES, self::STATUTORY_ATTRIBUTES);

        $this->validateStaffUploads($request, (int) $id);

        $managedZoneIds = $validated['managed_zone_ids'] ?? [];
        $supervisedZoneIds = $validated['supervised_zone_ids'] ?? [];
        unset($validated['managed_zone_ids'], $validated['supervised_zone_ids']);
        $this->assertNoOverlappingZoneRoles($managedZoneIds, $supervisedZoneIds);

        // keep uploaded files and flags out of the mass-assigned columns
        unset($validated['photo'], $validated['documents'], $validated['remove_documents'], $validated['signature_file'], $validated['signature_data'], $validated['remove_signature']);

        $validated = $this->applyPositionDepartment($validated);

        $staff = Staff::findOrFail($id);

        // Convert names to uppercase
        if (isset($validated['full_names'])) {
            $validated['full_names'] = strtoupper($validated['full_names']);
        }
        if (isset($validated['spouse_name'])) {
            $validated['spouse_name'] = strtoupper($validated['spouse_name']);
        }

        $obsoleteFiles = [];
        DB::transaction(function () use ($request, $staff, $validated, $managedZoneIds, $supervisedZoneIds, &$obsoleteFiles) {
            $staff->update($validated);
            $this->syncZoneRoles($staff, $managedZoneIds, $supervisedZoneIds);
            $obsoleteFiles = $this->syncStaffFiles($request, $staff);
            // A new signature replaces the old one; "remove" with nothing new clears it
            if ($this->hasSignatureInput($request) || $request->boolean('remove_signature')) {
                if ($staff->signature) $obsoleteFiles[] = ['local', $staff->signature];
                $staff->signature = $this->storeSignature($request, "staff_signatures/{$staff->id}");
                $staff->save();
            }
        });

        // Only delete replaced/removed files after the DB changes have committed
        foreach ($obsoleteFiles as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $redirect = redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Staff member updated successfully');
        if ($request->expectsJson()) {
            return response()->json(['redirect_url' => $redirect->getTargetUrl()]);
        }
        return $redirect;
    } catch (ValidationException $e) {
        Log::error('Validation error updating staff member: ' . json_encode($e->errors()));
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Please correct the highlighted fields. Your selected files have been kept.', 'errors' => $e->errors()], 422);
        }
        return redirect()->back()->withErrors($e->errors())->withInput();
    } catch (\Exception $e) {
        Log::error('Staff update failed: ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
        try {
            ErrorDispatcher::dispatch($e);
        } catch (\Exception $ex) {
            Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unable to save the staff member. Your form and selected files have been kept.'], 500);
        }
        return redirect()->back()->with('error', 'Failed to update staff member')->withInput();
    }
}

    /**
     * Fill in the staff department from the chosen position when no department was picked.
     * A department the user chose explicitly is never overridden.
     */
    private function applyPositionDepartment(array $validated): array
    {
        if (empty($validated['department_id']) && !empty($validated['position_id'])) {
            $positionDepartmentId = Position::where('id', $validated['position_id'])->value('department_id');
            if ($positionDepartmentId) {
                $validated['department_id'] = $positionDepartmentId;
            }
        }

        return $validated;
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
        abort_unless($contract, 404);

        // Rows the run no longer reads from a contract are shown as a notice, and cleared when the contract is saved
        [$visibleElements, $hiddenElements] = $contract->elements->partition(fn ($row) => $row->payrollElement?->canBeOnContract());

        return view('staff.contracts.contracts_edit', compact('contract', 'visibleElements', 'hiddenElements'));
    }

   public function contractList()
{
    try {
        $canRead   = Auth::user()->isAbleTo('read_contracts');
        $canUpdate = Auth::user()->isAbleTo('update_contracts');

        $report = app(EmployeeContractReport::class);
        $currentIds = $report->currentContracts()->pluck('id')->flip();

        $contracts = Contract::with('employee.department', 'position', 'createdBy', 'elements.payrollElement')
            ->whereHas('employee', function ($query) {
                $query->whereIn('current_status_id', [1, 4]);
            })
            ->orderBy('date_time', 'desc')
            ->get()
            ->map(function ($contract) use ($canRead, $canUpdate, $currentIds, $report) {
                $encrypted_id = $contract->employee ? Crypt::encrypt($contract->employee->id) : null;

                return [
                    'id' => $contract->id,
                    'staff_id' => $contract->staff_id,
                    'staff_number' => $contract->employee?->staff_number ?? $contract->staff_number,
                    'department' => $contract->employee?->department?->department_name,
                    'approval_status' => $report->approvalLabel($contract->current_status_id),
                    'is_current_approved' => $currentIds->has($contract->id),
                    'position_id' => $contract->position_id,
                    'date_start' => $contract->date_start ? date('Y-m-d', strtotime($contract->date_start)) : null,
                    'date_end' => $contract->date_end ? date('Y-m-d', strtotime($contract->date_end)) : null,
                    'basic_salary' => $contract->basic_salary,
                    'current_status_id' => $contract->current_status_id,
                    'expiry' => app(EmployeeContractExpiry::class)->tracking($contract),
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
                    'can_read' => $canRead,
                    'can_update' => $canUpdate,
                ];
            });

        return response()->json(['contracts' => $contracts, 'report' => $report->summary()]);
    } catch (\Throwable $e) {
        try {
            ErrorDispatcher::dispatch($e);
        } catch (\Exception $ex) {
            Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
        }
        Log::error('Error loading contracts list: ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
        ]);
        return response()->json(['error' => 'Failed to load contracts'], 500);
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
                'elements.*.entry_type' => 'nullable|in:1,2',
                'elements.*.amount' => 'required|numeric|min:0',
                'signed_contract' => 'nullable|file|mimes:pdf|max:5120',
            ]);

            $elementRows = $this->contractElementRows($validated['elements'] ?? []);
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
            foreach ($elementRows as $row) {
                $contract->elements()->create($row);
            }

            ContractChange::contractCreated($contract, Auth::id());

            if ($request->hasFile('signed_contract')) {
                $this->saveSignedContract($contract, $request->file('signed_contract'));
            }

            return redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Contract created successfully');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
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
                'elements.*.entry_type' => 'nullable|in:1,2',
                'elements.*.amount' => 'required|numeric|min:0',
                'signed_contract' => 'nullable|file|mimes:pdf|max:5120',
            ]);

            $elementRows = $this->contractElementRows($validated['elements'] ?? []);
            $contract = Contract::with('elements')->findOrFail($id);
            $oldSalary = $contract->basic_salary;
            $oldElements = ContractChange::elementAmounts($contract->elements);
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
            DB::transaction(function () use ($contract, $elementRows) {
                $contract->elements()->delete();
                foreach ($elementRows as $row) {
                    $contract->elements()->create($row);
                }
            });

            ContractChange::salary($contract, $oldSalary, $contract->basic_salary, Auth::id());
            ContractChange::elements($contract, $oldElements, array_map('floatval', array_column($elementRows, 'amount', 'element_id')), Auth::id());

            if ($request->hasFile('signed_contract')) {
                $this->saveSignedContract($contract, $request->file('signed_contract'));
            }

            return redirect()->route('staff.show', Crypt::encrypt($staff->id))->with('success', 'Contract updated successfully');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
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

    /**
     * Return attendance records as JSON, optionally filtered by date and status.
     */
    public function attendanceList(Request $request) {
        try {
            $records = EmployeeAttendance::with(['staff', 'employee_attendance_timed', 'createdBy'])
                ->when($request->status, fn($q, $s) => $q->where('current_status_id', $s))
                ->when($request->date, fn($q, $d) => $q->whereHas(
                    'employee_attendance_timed',
                    fn($t) => $t->whereDate('date', $d)
                ))
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
                        'employee_attendance_timed' => $att->employee_attendance_timed->map(fn($timed) => [
                            'id' => $timed->id,
                            'date' => $timed->date,
                            'time_in' => $timed->time_in,
                            'time_out' => $timed->time_out,
                            'hours' => $timed->hours,
                            'current_status_id' => $timed->current_status_id,
                        ]),
                        'current_status_id' => $att->current_status_id,
                        'created_by' => $att->createdBy ? [
                            'id' => $att->createdBy->id,
                            'name' => $att->createdBy->name,
                        ] : null,
                        'can_read' => Auth::user()->isAbleTo('read_contracts'),
                    ];
                });

            return response()->json(['records' => $records]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading attendance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load attendance records'], 500);
        }
    }

    /**
     * Validate an attendance CSV/XLSX upload and queue it for background processing.
     */
    public function attendanceUpload(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'attendance_file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $path = $request->file('attendance_file')->store('attendance_uploads');
            Cache::forget(ProcessAttendanceUpload::resultKey(Auth::id()));

            ProcessAttendanceUpload::dispatch($path, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Upload started. Data will appear once processed.',
            ]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attendance. Please try again.',
            ], 500);
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

    /**
     * Return the summary of the current user's latest attendance upload, or pending if it hasn't finished.
     */
    public function attendanceUploadResult() {
        $result = Cache::get(ProcessAttendanceUpload::resultKey(Auth::id()));
        return response()->json($result ? ['done' => true] + $result : ['done' => false]);
    }

    /**
     * Download attendance records as an Excel file, optionally filtered by date and status.
     */
    public function attendanceExport(Request $request) {
        $fileName = 'employee_attendance_' . now()->format('Y_m_d_His') . '.xlsx';
        return Excel::download(new AttendanceExport($request->date, $request->status), $fileName);
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
                $oldSalary = $contract->basic_salary;

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
                ContractChange::salary($contract, $oldSalary, $contract->basic_salary, Auth::id());
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

            $submitted = collect($request->input('elements', []))
                ->filter(fn ($element) => !empty($element['element_id']))
                ->map(fn ($element) => ['element' => $element['element_id'], 'amount' => $element['amount'] ?? 0])
                ->all();
            $rows = $this->contractElementRows($submitted);

            $before = ContractChange::elementAmounts($contract->elements);

            DB::transaction(function () use ($contract, $rows) {
                $contract->elements()->delete();
                foreach ($rows as $row) {
                    $contract->elements()->create($row);
                }
            });

            ContractChange::elements($contract, $before, array_map('floatval', array_column($rows, 'amount', 'element_id')), Auth::id());

            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => collect($e->errors())->flatten()->first()], 422);
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
