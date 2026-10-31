<?php

namespace App\Services\Leave;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveHandoverService
{
    public const MAX_FILES = 5;
    public const MAX_FILE_KB = 5120;

    public function revision(LeaveRequest $leave): string
    {
        return hash('sha256', json_encode([
            $leave->staff_id, $leave->handover_staff_id, $leave->handover_notes,
            $leave->start_date?->format('Y-m-d H:i:s'), $leave->end_date?->format('Y-m-d H:i:s'),
            $leave->handover_attachments ?? [],
        ]));
    }

    /** Files stay private; failed saves remove only the new uploads from that attempt. */
    public function saveAndSubmit(LeaveRequest $leave, Request $request, callable $submit): array
    {
        $uploaded = [];
        try {
            return DB::transaction(function () use ($leave, $request, $submit, &$uploaded) {
                $previous = $leave->exists ? LeaveRequest::lockForUpdate()->findOrFail($leave->id) : null;
                if ($previous && (!in_array((int) $previous->current_status_id, [1, 24], true)
                    || $previous->getRawOriginal('date_time') !== $leave->getRawOriginal('date_time'))) {
                    throw ValidationException::withMessages(['handover_notes' => 'This application changed. Reload it before editing.']);
                }
                $files = $request->file('handover_documents', []);
                $attachments = $previous?->handover_attachments ?? [];
                if (!$leave->handover_staff_id) $attachments = [];
                if (count($attachments) + count($files) > self::MAX_FILES) {
                    throw ValidationException::withMessages(['handover_documents' => 'Relief arrangements can have up to 5 files in total. Existing files are retained when editing.']);
                }
                foreach ($files as $file) {
                    $id = (string) Str::uuid();
                    $path = $file->storeAs('leave-handovers', $id.'.bin', 'local');
                    if (!$path) throw new \RuntimeException('Could not store a handover attachment.');
                    $uploaded[] = $path;
                    $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'attachment';
                    $attachments[] = ['id' => $id, 'path' => $path, 'name' => $name, 'size' => $file->getSize()];
                }
                $leave->handover_attachments = $attachments ?: null;
                if (!$previous || $this->revision($previous) !== $this->revision($leave)) {
                    $leave->handover_acknowledged_at = null;
                    $leave->handover_acknowledged_by = null;
                    $leave->handover_acknowledgement_note = null;
                } else {
                    // Preserve an acknowledgement made while this edit form was open.
                    $leave->handover_acknowledged_at = $previous->handover_acknowledged_at;
                    $leave->handover_acknowledged_by = $previous->handover_acknowledged_by;
                    $leave->handover_acknowledgement_note = $previous->handover_acknowledgement_note;
                }
                $leave->save();
                return $submit($leave);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
    }

    public function isRecipient(User $user, LeaveRequest $leave): bool
    {
        $staff = $user->linkedStaff();
        return !$user->block && $user->activation != 0 && $user->roles()->exists()
            && $staff && (int) $staff->current_status_id === 1
            && (int) $staff->id === (int) $leave->handover_staff_id;
    }

    public function acknowledge(LeaveRequest $leave, User $user, string $revision, ?string $note): void
    {
        DB::transaction(function () use ($leave, $user, $revision, $note) {
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leave->id);
            abort_unless($this->isRecipient($user, $leave), 403);
            abort_unless(in_array((int) $leave->current_status_id, [1, 24, 5], true), 422, 'These relief arrangements are no longer active.');
            abort_unless(hash_equals($this->revision($leave), $revision), 409, 'The relief arrangements changed. Reload and review the latest notes and files before acknowledging.');
            if ($leave->handover_acknowledged_at) return;
            $leave->handover_acknowledged_at = now();
            $leave->handover_acknowledged_by = $user->id;
            $leave->handover_acknowledgement_note = $note;
            $leave->save();
            app(LeaveApplicationNotifications::class)->acknowledged($leave);
        });
    }

    public function attachments(LeaveRequest $leave): array
    {
        return array_map(fn ($file) => [
            'name' => $file['name'], 'size' => $file['size'],
            'url' => route('leave-handover.download', ['id' => $leave->id, 'attachment' => $file['id']]),
        ], $leave->handover_attachments ?? []);
    }
}
