<?php

namespace App\Http\Controllers;

use App\Models\StaffDraft;
use App\Models\StaffOnboardingInvitation;
use App\Services\StaffNumbers;
use App\Traits\HandlesStaffSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Save as Draft on the Add Staff form. A part-finished record, with the files attached so far, is kept on the server so it can
 * be reopened from any computer, and nothing typed is lost if the page is closed or the session ends. A draft belongs to the
 * person who started it. No passwords are kept in it, and its files are on the private disk.
 */
class StaffDraftsController extends Controller
{
    use HandlesStaffSignature;

    /** Never kept as typed fields: secrets, and the files, which are saved separately. */
    private const NEVER_KEPT = ['_token', '_method', 'password', 'password_confirmation', 'signature_data', 'signature_file', 'photo', 'documents', 'draft_id', 'last_tab', 'stages'];

    /** Fields the form fills in by itself, so a form with only these has nothing worth saving. */
    private const FILLED_BY_THE_FORM = ['employment_type', 'staff_number', 'onboarding_invitation_id', 'signature_mode'];

    private const MAX_FIELD_LENGTH = 5000;

    private function authorizeStaffCreation(): void
    {
        abort_unless(auth()->user()?->hasPermission('create_staff'), 403);
    }

    private function mine(int $id): StaffDraft
    {
        return StaffDraft::visibleTo(auth()->id())->findOrFail($id);
    }

    public function index(): JsonResponse
    {
        $this->authorizeStaffCreation();

        return response()->json([
            'success' => true,
            'drafts' => StaffDraft::visibleTo(auth()->id())->orderByDesc('updated_at')->get()->map(fn (StaffDraft $draft) => [
                'id' => $draft->id,
                'title' => $draft->title ?: 'Untitled draft',
                'updated_at' => $draft->updated_at->toIso8601String(),
                'updated_label' => $draft->updated_at->timezone(config('app.timezone'))->format('d M Y, H:i'),
                'files' => count($draft->files()),
                'last_tab' => $draft->last_tab,
                'staff_number' => $draft->staff_number,
                'stages' => $draft->stageStatuses(),
                'resume_url' => route('staff.create', ['draft' => $draft->id]),
            ])->values(),
        ]);
    }

    /**
     * Create the draft, or update the one given in draft_id. Only the files chosen this time are replaced; the ones saved
     * earlier stay.
     */
    public function save(Request $request): JsonResponse
    {
        $this->authorizeStaffCreation();

        $request->validate($this->signatureRules() + [
            'draft_id' => 'nullable|integer',
            'last_tab' => 'nullable|string|max:40',
            'stages' => 'nullable|string|max:600',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'documents' => 'nullable|array',
            'documents.national_id' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'documents.kra_pin' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'documents.contract' => 'nullable|file|mimes:pdf|max:5120',
            'documents.cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'documents.academic' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ], [
            'photo.uploaded' => 'The staff photo could not be received. Choose a smaller file.',
            'documents.*.uploaded' => 'A document could not be received. Choose a smaller file.',
        ]);

        $draft = null;
        if ($request->filled('draft_id')) {
            $draft = StaffDraft::visibleTo(auth()->id())->find((int) $request->input('draft_id'));
            if (!$draft) {
                return response()->json(['success' => false, 'message' => 'That draft is no longer there. It was discarded, or it has become a staff member.'], 404);
            }
        }

        $fields = $this->cleanFields($request->except(self::NEVER_KEPT));
        $typedSomething = count(array_diff_key($fields, array_flip(self::FILLED_BY_THE_FORM))) > 0;
        $attachedSomething = $request->hasFile('photo') || $request->hasFile('documents.national_id') || $request->hasFile('documents.kra_pin')
            || $request->hasFile('documents.contract') || $request->hasFile('documents.cv') || $request->hasFile('documents.academic')
            || $this->hasSignatureInput($request) || ($draft && $draft->files());
        if (!$typedSomething && !$attachedSomething) {
            return response()->json(['success' => false, 'message' => 'There is nothing to save yet. Fill in something first.'], 422);
        }

        $created = $draft === null;
        $draft ??= new StaffDraft(['created_by' => auth()->id()]);

        try {
            // The staff number is handed out under a lock, so two people saving at once cannot be given the same one
            StaffNumbers::locked(fn () => DB::transaction(function () use ($request, $draft, $fields) {
                $draft->payload = ['fields' => $fields, 'files' => $draft->exists ? $draft->files() : []];
                $draft->title = $this->titleFrom($fields);
                $draft->last_tab = $request->input('last_tab');
                $draft->stages = $this->cleanStages($request->input('stages'), $draft->stages);
                $draft->onboarding_invitation_id = isset($fields['onboarding_invitation_id']) && ctype_digit($fields['onboarding_invitation_id'])
                    ? (int) $fields['onboarding_invitation_id'] : null;
                $draft->updated_by = auth()->id();
                // The number is held from the first save, in the category chosen on the form
                StaffNumbers::reserve($draft, StaffNumbers::categoryFor($fields, $draft));
                $draft->save();

                $files = $draft->files();
                $files = $this->replaceUploads($request, $draft, $files);
                $draft->payload = ['fields' => $fields, 'files' => $files];
                $draft->save();
            }));
        } catch (\Throwable $e) {
            // A new draft that could not be finished leaves nothing behind
            if ($created && $draft->exists) {
                $draft->discard();
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'draft_id' => $draft->id,
            'staff_number' => $draft->staff_number,
            'stages' => $draft->stageStatuses(),
            'saved_at' => $draft->updated_at->toIso8601String(),
            'saved_at_label' => $draft->updated_at->timezone(config('app.timezone'))->format('H:i'),
            'message' => 'Draft saved. You can come back to it from any computer.',
        ]);
    }

    /**
     * A file saved with a draft, for the Preview link beside it. The hash in the address is of the stored path, as it is for
     * onboarding files.
     */
    public function file(int $draft, string $hash)
    {
        $this->authorizeStaffCreation();
        $draft = $this->mine($draft);

        foreach ($draft->files() as $file) {
            if (hash('sha256', $file['path']) === $hash && Storage::disk('local')->exists($file['path'])) {
                return Storage::disk('local')->response($file['path'], $file['name'] ?? basename($file['path']), [
                    'Content-Disposition' => 'inline',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store',
                ]);
            }
        }

        abort(404);
    }

    /** Discard a draft: from the form it answers JSON, from the Staff onboarding page it goes back there. */
    public function destroy(Request $request, int $draft)
    {
        $this->authorizeStaffCreation();
        $this->mine($draft)->discard();

        if (!$request->expectsJson()) {
            return redirect()->route('staff.onboarding.index')->with('success', 'The draft was discarded.');
        }

        return response()->json(['success' => true, 'message' => 'The draft was discarded.']);
    }

    /**
     * Store the files chosen this time under the draft's folder, replacing the ones they stand in for.
     *
     * @param array<string, array> $files the draft's files so far
     */
    private function replaceUploads(Request $request, StaffDraft $draft, array $files): array
    {
        $keep = function (string $field, string $path, ?string $name, ?int $size, ?string $mime) use (&$files, $draft) {
            if (isset($files[$field])) {
                Storage::disk('local')->delete($files[$field]['path']);
            }
            $entry = ['path' => $path, 'name' => $name ?: basename($path), 'size' => $size, 'mime' => $mime];
            $label = Str::after($field, 'documents.');
            if ($field !== 'photo' && $field !== 'signature' && isset(StaffOnboardingInvitation::DOCUMENT_TYPES[$label])) {
                $entry['type_name'] = StaffOnboardingInvitation::DOCUMENT_TYPES[$label];
            }
            $files[$field] = $entry;
        };

        $uploads = ['photo' => 'photo'];
        foreach (array_keys(StaffOnboardingInvitation::DOCUMENT_TYPES) as $key) {
            $uploads["documents.$key"] = "documents.$key";
        }
        foreach ($uploads as $field => $inputName) {
            $file = $request->file($inputName);
            if ($file && $file->isValid()) {
                $path = $file->store($draft->directory(), 'local');
                $keep($field, $path, Str::limit(basename($file->getClientOriginalName()), 120, ''), $file->getSize(), $file->getMimeType());
            }
        }

        $signature = $this->storeSignature($request, $draft->directory());
        if ($signature) {
            $keep('signature', $signature, basename($signature), Storage::disk('local')->size($signature), 'image/png');
        }

        return $files;
    }

    /**
     * What was typed, as it can be kept: plain text and lists of plain text, each field short enough to be reasonable.
     */
    private function cleanFields(array $input): array
    {
        $clean = [];
        foreach ($input as $name => $value) {
            if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,80}$/', $name)) {
                continue;
            }
            if (is_array($value)) {
                $list = array_values(array_filter($value, fn ($item) => is_scalar($item) && strlen((string) $item) <= 255 && (string) $item !== ''));
                if ($list) {
                    $clean[$name] = array_map('strval', $list);
                }
                continue;
            }
            if (is_scalar($value) && (string) $value !== '' && strlen((string) $value) <= self::MAX_FIELD_LENGTH) {
                $clean[$name] = (string) $value;
            }
        }

        return $clean;
    }

    /**
     * The stages the form sent, as JSON text: only the known stages, each with a known status. When nothing usable came, the
     * stages saved before are kept rather than wiped.
     *
     * @param array<string, string>|null $before
     * @return array<string, string>|null
     */
    private function cleanStages(?string $sent, ?array $before): ?array
    {
        $decoded = $sent === null || $sent === '' ? null : json_decode($sent, true);
        if (!is_array($decoded)) {
            return $before;
        }

        $clean = [];
        foreach (array_keys(StaffDraft::STAGES) as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && in_array($decoded[$key], StaffDraft::STAGE_STATUSES, true)) {
                $clean[$key] = $decoded[$key];
            }
        }

        return $clean ?: $before;
    }

    private function titleFrom(array $fields): ?string
    {
        $name = trim((string) ($fields['full_names'] ?? ''));

        return $name === '' ? null : Str::limit(mb_strtoupper($name), 255, '');
    }
}
