<?php

namespace App\Http\Controllers;

use App\Mail\StaffOnboardingInvitationMail;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\Position;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use App\Models\UserStaffLink;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\PasswordResetJob;
use App\Services\EmployeeUploadGuard;
use Illuminate\Validation\ValidationException;

class StaffOnboardingController extends Controller
{
    private function authorizeStaffCreation(): void
    {
        abort_unless(auth()->user()?->hasPermission('create_staff'), 403);
    }

    public function create()
    {
        $this->authorizeStaffCreation();
        return view('staff.onboarding.create', [
            'departments' => Department::orderBy('department_name')->get(),
            'positions' => Position::orderBy('position_name')->get(),
            'roles' => Role::orderBy('display_name')->get(),
            'zones' => Zone::orderBy('zone_name')->get(),
        ]);
    }

    public function send(Request $request)
    {
        $this->authorizeStaffCreation();
        $data = $request->validate([
            'recipient_email' => 'required|email|max:255|same:recipient_email_confirmation',
            'recipient_email_confirmation' => 'required|email|max:255',
            'employment_type' => 'required|in:Permanent,Temporary,Casual,Intern',
            'department_id' => 'nullable|integer|exists:sys_set_departments,id',
            'position_id' => 'nullable|integer|exists:sys_set_staff_positions,id',
            'hire_date' => 'nullable|date',
            'zone_id' => 'nullable|integer|exists:sys_set_zones,id',
            'role_id' => 'required_unless:employment_type,Casual|nullable|integer|exists:cb_roles,id',
        ]);
        $normalizedEmail = strtolower($data['recipient_email']);
        if (Staff::whereRaw('LOWER(email_address) = ?', [$normalizedEmail])->exists()) {
            throw ValidationException::withMessages(['recipient_email' => 'This company email is already attached to a staff record.']);
        }
        if ($data['employment_type'] !== 'Casual' && User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()) {
            throw ValidationException::withMessages(['recipient_email' => 'This email already belongs to a system user.']);
        }
        if (StaffOnboardingInvitation::where('recipient_email', $normalizedEmail)
            ->whereIn('status', ['sent', 'submitted'])->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['recipient_email' => 'An active onboarding invitation already exists for this email.']);
        }

        $token = Str::random(64);
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => strtolower($data['recipient_email']),
            'token_hash' => hash('sha256', $token),
            'employment_type' => $data['employment_type'],
            'department_id' => $data['department_id'] ?? null,
            'position_id' => $data['position_id'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'role_id' => $data['employment_type'] === 'Casual' ? null : ($data['role_id'] ?? null),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addHours(72),
            'status' => 'sent',
        ]);

        try {
            if ($this->mailerMayExposeLinksInLogs()) {
                throw new \RuntimeException('A mailer that writes private links to logs is not allowed.');
            }
            $url = route('staff.onboarding.complete', ['token' => $token]);
            Mail::to($invitation->recipient_email)->send(new StaffOnboardingInvitationMail($url));
        } catch (\Throwable $e) {
            $invitation->update(['status' => 'mail_failed']);
            Log::error('Staff onboarding invitation delivery failed.', ['invitation_id' => $invitation->id, 'exception' => get_class($e)]);
            return back()->withInput()->with('error', 'The invitation could not be sent. Check the mail service and try again.');
        }

        return redirect()->route('staff.onboarding.index')->with('success', 'A private onboarding link was sent to ' . $invitation->recipient_email . '.');
    }

    public function index()
    {
        $this->authorizeStaffCreation();
        $invitations = StaffOnboardingInvitation::latest()->paginate(25);
        return view('staff.onboarding.index', compact('invitations'));
    }

    public function show(string $token)
    {
        $invitation = $this->findUsableInvitation($token);
        return $this->privatePage('staff.onboarding.complete', [
            'invitation' => $invitation,
            'token' => $token,
            'banks' => \App\Models\Bank::orderBy('bank_name')->get(),
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $invitation = $this->findUsableInvitation($token);
        $data = $request->validate([
            'full_names' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female',
            'birth_date' => 'required|date|before:today',
            'national_id' => 'required|string|max:255|unique:sys_staff,national_id',
            'passport' => 'nullable|string|max:255',
            'mobile_number' => 'required|string|max:30',
            'personal_email' => 'required|email|max:255',
            'address' => 'nullable|string|max:1000',
            'marital' => 'required|in:Single,Married,Divorced,Widowed',
            'spouse_name' => 'nullable|string|max:255',
            'citizenship' => 'required|string|max:100',
            'disability' => 'required|in:YES,NO',
            'bank_name' => 'required|integer|exists:sys_set_banks,id',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'pin_number' => 'required|string|max:255',
            'nhif_number' => 'required|string|max:255',
            'nssf_number' => 'required|string|max:255',
            'documents.national_id' => 'required|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'documents.kra_pin' => 'required|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'documents.contract' => 'nullable|file|mimes:pdf|max:5120',
            'documents.cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'documents.academic' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ]);
        $files = [];
        foreach (['national_id', 'kra_pin', 'contract', 'cv', 'academic'] as $type) {
            $files["documents.$type"] = $request->file("documents.$type");
        }
        app(EmployeeUploadGuard::class)->validate($files);
        $documentTypes = [
            'national_id' => 'National ID / Passport', 'kra_pin' => 'KRA PIN Certificate',
            'contract' => 'Signed Employment Contract', 'cv' => 'Curriculum Vitae (CV)',
            'academic' => 'Academic Certificates & Transcripts',
        ];
        $data['onboarding_documents'] = [];
        foreach ($documentTypes as $key => $title) {
            $file = $request->file("documents.$key");
            if (!$file) continue;
            $data['onboarding_documents'][] = [
                'type_name' => $title,
                'path' => $file->store("staff_onboarding/{$invitation->id}", 'local'),
                'name' => $file->getClientOriginalName(),
                'size' => (int) round($file->getSize() / 1024),
                'mime' => $file->getMimeType(),
            ];
        }
        try {
            DB::transaction(function () use ($invitation, $data) {
                $lockedInvite = StaffOnboardingInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedInvite->status === 'sent' && $lockedInvite->expires_at->isFuture(), 410);
                $lockedInvite->update(['submission' => $data, 'status' => 'submitted', 'submitted_at' => now()]);
            });
        } catch (\Throwable $e) {
            foreach ($data['onboarding_documents'] as $document) Storage::disk('local')->delete($document['path']);
            throw $e;
        }
        return $this->privatePage('staff.onboarding.received');
    }

    public function review(StaffOnboardingInvitation $invitation)
    {
        $this->authorizeStaffCreation();
        abort_unless($invitation->status === 'submitted', 404);
        return view('staff.onboarding.review', [
            'invitation' => $invitation,
            'role' => $invitation->role_id ? Role::find($invitation->role_id) : null,
            'department' => $invitation->department_id ? Department::find($invitation->department_id) : null,
            'position' => $invitation->position_id ? Position::find($invitation->position_id) : null,
            'zone' => $invitation->zone_id ? Zone::find($invitation->zone_id) : null,
        ]);
    }

    public function approve(StaffOnboardingInvitation $invitation)
    {
        $this->authorizeStaffCreation();
        abort_unless($invitation->status === 'submitted', 409, 'This submission has already been reviewed.');
        if ($invitation->employment_type !== 'Casual') {
            abort_unless(auth()->user()->hasPermission('create_users'), 403, 'You need create users permission to approve an onboarding that provisions a system account.');
        }
        $data = $invitation->submission;
        abort_unless(is_array($data), 409, 'The submitted onboarding data is unavailable.');
        if (Staff::where('national_id', $data['national_id'])->exists()) {
            throw ValidationException::withMessages(['national_id' => 'This national ID is already attached to a staff record.']);
        }
        if ($invitation->employment_type !== 'Casual' && User::whereRaw('LOWER(email) = ?', [strtolower($invitation->recipient_email)])->exists()) {
            throw ValidationException::withMessages(['recipient_email' => 'This company email already belongs to a system user.']);
        }

        $resetDetails = null;
        $approvedDocumentPaths = [];
        try {
        DB::transaction(function () use ($invitation, $data, &$resetDetails, &$approvedDocumentPaths) {
            $lockedInvite = StaffOnboardingInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedInvite->status === 'submitted', 409, 'This submission has already been reviewed.');
            if (Staff::where('national_id', $data['national_id'])->exists()) {
                throw ValidationException::withMessages(['national_id' => 'This national ID is already attached to a staff record.']);
            }
            if (Staff::whereRaw('LOWER(email_address) = ?', [strtolower($invitation->recipient_email)])->exists()) {
                throw ValidationException::withMessages(['recipient_email' => 'This company email is already attached to a staff record.']);
            }
            if ($invitation->employment_type !== 'Casual' && User::whereRaw('LOWER(email) = ?', [strtolower($invitation->recipient_email)])->exists()) {
                throw ValidationException::withMessages(['recipient_email' => 'This company email already belongs to a system user.']);
            }
            $staff = new Staff();
            $documents = $data['onboarding_documents'] ?? [];
            unset($data['onboarding_documents']);
            $staff->fill(array_merge($data, [
                'full_names' => strtoupper($data['full_names']),
                'email_address' => $invitation->recipient_email,
                'employment_type' => $invitation->employment_type,
                'department_id' => $invitation->department_id,
                'position_id' => $invitation->position_id,
                'hire_date' => $invitation->hire_date,
                'zone_id' => $invitation->zone_id,
            ]));
            $staff->date_time = now();
            $staff->staff_number = Staff::nextStaffNumber($invitation->employment_type);
            $staff->uid = uniqid('staff_', true);
            $staff->current_status_id = 1;
            $staff->save();

            $documentTypeIds = DocumentType::pluck('id', 'name');
            Storage::disk('local')->makeDirectory("employee_documents/{$staff->id}");
            foreach ($documents as $document) {
                $typeId = $documentTypeIds[$document['type_name']] ?? null;
                if (!$typeId) throw new \RuntimeException('Required staff document type is missing: ' . $document['type_name']);
                $approvedPath = "employee_documents/{$staff->id}/" . $staff->id . '-' . basename($document['path']);
                if (!Storage::disk('local')->copy($document['path'], $approvedPath)) {
                    throw new \RuntimeException('Could not move onboarding document into the staff document store.');
                }
                $approvedDocumentPaths[] = $approvedPath;
                EmployeeDocument::create([
                    'employee_id' => $staff->id,
                    'document_type_id' => $typeId,
                    'title' => $document['type_name'],
                    'file_path' => $approvedPath,
                    'file_name' => $document['name'],
                    'file_size' => $document['size'],
                    'mime_type' => $document['mime'],
                    'uploaded_by' => auth()->id(),
                ]);
            }

            if ($invitation->employment_type !== 'Casual') {
                $username = Str::before($invitation->recipient_email, '@');
                $base = $username;
                $suffix = 1;
                while (User::where('username', $username)->exists()) $username = $base . $suffix++;
                $user = User::create([
                    'name' => $staff->full_names,
                    'username' => $username,
                    'email' => $invitation->recipient_email,
                    'password' => Str::password(40),
                    'block' => 0,
                    'sendEmail' => 0,
                    'registerDate' => now(),
                    'activation' => 1,
                    'params' => '',
                    'resetCount' => 0,
                    'otpKey' => '',
                    'otep' => '',
                    'requireReset' => 1,
                    'staff_id' => $staff->id,
                ]);
                DB::table('cb_role_user')->insert(['user_id' => $user->id, 'role_id' => $invitation->role_id]);
                UserStaffLink::create(['user_id' => $user->id, 'staff_id' => $staff->id]);
                $resetToken = Str::random(64);
                $user->reset_token = Hash::make($resetToken);
                $resetValidityHours = (int) (DB::table('cb_system_configs')->where('setting', 'password_reset_link_validity')->value('value') ?? 4);
                $user->reset_token_expires_at = now()->addHours(max(1, $resetValidityHours));
                $user->save();
                $resetDetails = ['username' => $user->name, 'email' => $user->email, 'token' => $resetToken];
            }
            $lockedInvite->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'staff_id' => $staff->id, 'submission' => null]);
        });
        } catch (\Throwable $e) {
            foreach ($approvedDocumentPaths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }
        Storage::disk('local')->deleteDirectory("staff_onboarding/{$invitation->id}");

        if ($resetDetails) {
            try {
                if ($this->mailerMayExposeLinksInLogs()) {
                    throw new \RuntimeException('A mailer that writes password links to logs is not allowed.');
                }
                PasswordResetJob::dispatch($resetDetails);
            } catch (\Throwable $e) {
                Log::error('Staff account setup email could not be queued.', ['invitation_id' => $invitation->id, 'exception' => get_class($e)]);
                return redirect()->route('staff.onboarding.index')->with('success', 'Staff record created, but no safe password setup email could be sent. Configure a private mail transport and issue a password reset.');
            }
        }

        return redirect()->route('staff.onboarding.index')->with('success', 'Onboarding approved and the staff record was created.');
    }

    public function revoke(StaffOnboardingInvitation $invitation)
    {
        $this->authorizeStaffCreation();
        abort_unless(in_array($invitation->status, ['sent', 'submitted'], true), 409);
        foreach ($invitation->submission['onboarding_documents'] ?? [] as $document) {
            Storage::disk('local')->delete($document['path']);
        }
        $invitation->update(['status' => 'revoked', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'submission' => null]);
        return back()->with('success', 'The onboarding link was revoked and any pending submission was removed.');
    }

    private function findUsableInvitation(string $token): StaffOnboardingInvitation
    {
        abort_unless(strlen($token) === 64, 404);
        $invitation = StaffOnboardingInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($invitation->status === 'sent' && $invitation->expires_at->isFuture(), 410);
        return $invitation;
    }

    private function mailerMayExposeLinksInLogs(): bool
    {
        $default = config('mail.default');
        $config = config("mail.mailers.{$default}", []);
        if ($default === 'log' || ($config['transport'] ?? null) === 'log') return true;
        if (in_array($config['transport'] ?? null, ['failover', 'roundrobin'], true)) {
            foreach ($config['mailers'] ?? [] as $mailer) {
                if ($mailer === 'log' || config("mail.mailers.{$mailer}.transport") === 'log') return true;
            }
        }
        return false;
    }

    private function privatePage(string $view, array $data = [])
    {
        return response()->view($view, $data)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
