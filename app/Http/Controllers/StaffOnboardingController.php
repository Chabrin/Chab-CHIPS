<?php

namespace App\Http\Controllers;

use App\Mail\StaffOnboardingInvitationMail;
use App\Models\Bank;
use App\Models\Staff;
use App\Models\StaffOnboardingInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\EmployeeUploadGuard;
use App\Services\StaffContactInput;
use App\Services\StaffOnboardingTracker;
use App\Traits\HandlesStaffSignature;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Invite -> staff fills the private link -> HR reviews and approves -> HR finishes
 * the record on the Add Staff form (StaffController@create / store), which is
 * prefilled from the submission and marks the invitation approved on save.
 */
class StaffOnboardingController extends Controller
{
    use HandlesStaffSignature;

    private function authorizeStaffCreation(): void
    {
        abort_unless(auth()->user()?->hasPermission('create_staff'), 403);
    }

    public function create()
    {
        $this->authorizeStaffCreation();
        return view('staff.onboarding.create');
    }

    public function send(Request $request)
    {
        $this->authorizeStaffCreation();
        $data = $request->validate([
            'recipient_email' => 'required|email|max:255|same:recipient_email_confirmation',
            'recipient_email_confirmation' => 'required|email|max:255',
        ]);
        $normalizedEmail = strtolower($data['recipient_email']);
        if (Staff::whereRaw('LOWER(email_address) = ? OR LOWER(personal_email) = ?', [$normalizedEmail, $normalizedEmail])->exists()) {
            throw ValidationException::withMessages(['recipient_email' => 'This email is already attached to a staff record.']);
        }
        $hasActiveInvite = StaffOnboardingInvitation::where('recipient_email', $normalizedEmail)
            ->where(fn ($query) => $query->where('status', 'reviewed')
                ->orWhere(fn ($query) => $query->whereIn('status', ['sent', 'submitted'])->where('expires_at', '>', now())))
            ->exists();
        if ($hasActiveInvite) {
            throw ValidationException::withMessages(['recipient_email' => 'An active onboarding invitation already exists for this email.']);
        }

        $token = Str::random(64);
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => $normalizedEmail,
            'token_hash' => hash('sha256', $token),
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

    /**
     * Everyone being onboarded in one list: invitations sent for the employee to fill in, and staff HR is adding directly (drafts),
     * each with how far every stage has got and what to do next. Staff who have been saved but have no contract yet stay in
     * progress, waiting at "Add contract".
     */
    public function index(Request $request)
    {
        $this->authorizeStaffCreation();

        $rows = app(StaffOnboardingTracker::class)->rows();
        $counts = StaffOnboardingTracker::counts($rows);
        $show = in_array($request->query('show'), [StaffOnboardingTracker::GROUP_IN_PROGRESS, StaffOnboardingTracker::GROUP_FINISHED, StaffOnboardingTracker::GROUP_CLOSED], true)
            ? $request->query('show')
            : StaffOnboardingTracker::GROUP_IN_PROGRESS;

        $list = StaffOnboardingTracker::inGroup($rows, $show);
        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $onboardings = new LengthAwarePaginator($list->forPage($page, $perPage)->values(), $list->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('staff.onboarding.index', compact('onboardings', 'counts', 'show'));
    }

    public function show(string $token)
    {
        $invitation = $this->findUsableInvitation($token);
        return $this->privatePage('staff.onboarding.complete', [
            'invitation' => $invitation,
            'token' => $token,
            'banks' => Bank::orderBy('bank_name')->get(),
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $invitation = $this->findUsableInvitation($token);
        // The mobile number is kept with its country code (+254712345678)
        $contact = app(StaffContactInput::class)->prepare($request);
        $data = $request->validate($this->signatureRules() + [
            'full_names' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female',
            'birth_date' => 'required|date|before:today',
            'birth_place' => 'nullable|string|max:255',
            'national_id' => 'required|string|max:255|unique:sys_staff,national_id',
            'passport' => 'nullable|string|max:255',
            'marital' => 'required|in:Single,Married,Divorced,Widowed',
            'spouse_name' => 'nullable|string|max:255',
            'citizenship' => 'required|in:RESIDENT,NO',
            'disability' => 'required|in:YES,NO',
            'mobile_number' => array_merge(['required', 'string', 'max:30'], $contact['mobile_number'] ?? []),
            'personal_email' => 'required|email|max:255',
            'address' => 'nullable|string|max:1000',
            'district' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'sub_location' => 'nullable|string|max:255',
            'kins_names' => 'nullable|string|max:255',
            'kins_relationship' => 'required_with:kins_names|nullable|in:' . implode(',', Staff::KIN_RELATIONSHIPS),
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
            'staff_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [], ['kins_names' => 'next of kin names', 'kins_relationship' => 'relationship', 'mobile_number' => 'mobile number']);
        unset($data['documents'], $data['staff_photo'], $data['signature_file'], $data['signature_data']);
        $files = [];
        foreach (array_keys(StaffOnboardingInvitation::DOCUMENT_TYPES) as $type) {
            $files["documents.$type"] = $request->file("documents.$type");
        }
        $files['photo'] = $request->file('staff_photo');
        app(EmployeeUploadGuard::class)->validate($files);
        if ($request->hasFile('staff_photo')) {
            $data['staff_photo_path'] = $request->file('staff_photo')->store("staff_onboarding/{$invitation->id}", 'local');
        }
        $data['onboarding_documents'] = [];
        try {
            $data['signature_path'] = $this->storeSignature($request, "staff_onboarding/{$invitation->id}");
            foreach (StaffOnboardingInvitation::DOCUMENT_TYPES as $key => $title) {
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
            DB::transaction(function () use ($invitation, $data) {
                $lockedInvite = StaffOnboardingInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedInvite->status === 'sent' && $lockedInvite->expires_at->isFuture(), 410);
                $lockedInvite->update(['submission' => $data, 'status' => 'submitted', 'submitted_at' => now()]);
            });
        } catch (\Throwable $e) {
            foreach ($data['onboarding_documents'] as $document) Storage::disk('local')->delete($document['path']);
            if (!empty($data['staff_photo_path'])) Storage::disk('local')->delete($data['staff_photo_path']);
            if (!empty($data['signature_path'])) Storage::disk('local')->delete($data['signature_path']);
            throw $e;
        }
        return $this->privatePage('staff.onboarding.received');
    }

    public function review(StaffOnboardingInvitation $invitation)
    {
        $this->authorizeStaffCreation();
        abort_unless(in_array($invitation->status, ['submitted', 'reviewed'], true), 404);
        $data = $invitation->submission ?? [];
        $bank = !empty($data['bank_name']) ? Bank::whereKey($data['bank_name'])->value('bank_name') : null;
        $sections = [
            'Personal Information' => [
                'Full Names' => $data['full_names'] ?? null, 'Gender' => $data['gender'] ?? null,
                'Date of Birth' => $data['birth_date'] ?? null, 'Birth Place' => $data['birth_place'] ?? null,
                'National ID' => $data['national_id'] ?? null, 'Passport Number' => $data['passport'] ?? null,
                'Marital Status' => $data['marital'] ?? null, 'Spouse Name' => $data['spouse_name'] ?? null,
                'Citizenship' => $data['citizenship'] ?? null, 'Disability' => $data['disability'] ?? null,
            ],
            'Contact Information' => [
                'Mobile Number' => $data['mobile_number'] ?? null, 'Personal Email' => $data['personal_email'] ?? null,
                'Address' => $data['address'] ?? null, 'District' => $data['district'] ?? null,
                'Location' => $data['location'] ?? null, 'Sub Location' => $data['sub_location'] ?? null,
                'Next of Kin Names' => $data['kins_names'] ?? null,
                'Next of Kin Relationship' => $data['kins_relationship'] ?? null,
            ],
            'Banking & Statutory' => [
                'Bank' => $bank, 'Account Name' => $data['account_name'] ?? null,
                'Account Number' => $data['account_number'] ?? null, 'KRA PIN Number' => $data['pin_number'] ?? null,
                'SHA Number' => $data['nhif_number'] ?? null, 'NSSF Number' => $data['nssf_number'] ?? null,
            ],
        ];
        return view('staff.onboarding.review', [
            'invitation' => $invitation,
            'sections' => $sections,
            'files' => $invitation->submittedFiles(),
        ]);
    }

    public function reviewFile(StaffOnboardingInvitation $invitation, string $file, Request $request)
    {
        $this->authorizeStaffCreation();
        abort_unless(in_array($invitation->status, ['submitted', 'reviewed'], true), 404);
        $document = collect($invitation->submittedFiles())->first(fn ($item) => hash_equals(hash('sha256', $item['path']), $file));
        abort_unless($document && Storage::disk('local')->exists($document['path']), 404);
        $name = addslashes(basename($document['name']));
        return Storage::disk('local')->response($document['path'], $document['name'], [
            'Content-Type' => $document['mime'] ?? Storage::disk('local')->mimeType($document['path']) ?: 'application/octet-stream',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * HR accepts the submitted details and continues on the Add Staff form,
     * where the company details are filled in and the staff record is created.
     */
    public function approve(StaffOnboardingInvitation $invitation)
    {
        $this->authorizeStaffCreation();
        if ($invitation->status === 'submitted') {
            $invitation->update(['status' => 'reviewed', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        }
        abort_unless($invitation->status === 'reviewed', 409, 'This submission has already been processed.');
        return redirect()->route('staff.create', ['onboarding' => $invitation->id])
            ->with('success', 'Submission approved. Complete the company details below and save to create the staff record.');
    }

    public function revoke(StaffOnboardingInvitation $invitation)
    {
        $this->authorizeStaffCreation();
        abort_unless(in_array($invitation->status, ['sent', 'submitted', 'reviewed'], true), 409);
        $invitation->update(['status' => 'revoked', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'submission' => null]);
        Storage::disk('local')->deleteDirectory("staff_onboarding/{$invitation->id}");
        return redirect()->route('staff.onboarding.index')->with('success', 'The onboarding was cancelled and any submitted details and files were removed.');
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
