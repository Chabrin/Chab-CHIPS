<?php

namespace App\Http\Controllers;

use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\User;
use App\Events\StaffMessagingUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffMessagingController extends Controller
{
    public function index(Request $request)
    {
        $this->requireStaff($request);

        return response()->view('staff-messaging.index', ['uploadLimits' => $this->uploadLimits()])
            ->header('Cache-Control', 'no-store');
    }

    public function directory(Request $request): JsonResponse
    {
        $this->requireStaff($request);
        $validated = $request->validate(['search' => 'nullable|string|max:100']);
        $query = User::messagingEligible()->where('id', '!=', $request->user()->id);
        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $query->whereLike('name', '%' . $search . '%');
        }
        $users = $query->with('roles:id,display_name')->orderBy('name')->orderBy('id')->simplePaginate(20, ['id', 'name']);
        $items = collect($users->items())->map(fn ($user) => [
            'id' => $user->id, 'name' => $user->name,
            'role' => $user->roles->pluck('display_name')->implode(', ') ?: 'Staff',
        ]);

        return $this->json(['users' => $items, 'has_more' => $users->hasMorePages()]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $this->requireStaff($request);
        $userId = (int) $request->user()->id;
        $conversations = StaffConversation::forUser($userId)
            ->with(['firstUser:id,name', 'secondUser:id,name', 'latestMessage' => fn ($query) => $query->withCount('attachments')])
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->where('sender_id', '!=', $userId)->whereNull('read_at')])
            ->orderByDesc('updated_at')->orderByDesc('id')->simplePaginate(25);

        $items = collect($conversations->items())->map(function ($conversation) use ($userId) {
            $other = (int) $conversation->first_user_id === $userId
                ? $conversation->secondUser : $conversation->firstUser;

            return [
                'id' => $conversation->id,
                'other_user' => ['id' => $other->id, 'name' => $other->name],
                'unread_count' => $conversation->unread_count,
                'latest_message' => $conversation->latestMessage
                    ? $this->messageData($conversation->latestMessage) : null,
            ];
        });

        return $this->json([
            'conversations' => $items, 'has_more' => $conversations->hasMorePages(),
            'unread_count' => $this->unreadForUser($userId),
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $this->requireStaff($request);
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'min:1',
                'not_in:' . $user->id,
            ],
        ]);

        $recipient = User::findOrFail($validated['user_id']);

        abort_unless(
            $recipient->canUseStaffMessaging(),
            422,
            'This staff member is unavailable for messaging.'
        );

        $firstUserId = min((int) $user->id, (int) $recipient->id);
        $secondUserId = max((int) $user->id, (int) $recipient->id);

        $conversation = StaffConversation::firstOrCreate([
            'first_user_id' => $firstUserId,
            'second_user_id' => $secondUserId,
        ]);

        return $this->json([
            'conversation' => [
                'id' => $conversation->id,
                'other_user' => [
                    'id' => $recipient->id,
                    'name' => $recipient->name,
                ],
            ],
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->participantConversation($request, $conversation);
        $validated = $request->validate([
            'before_id' => 'nullable|integer|min:1|prohibits:after_id',
            'after_id' => 'nullable|integer|min:1|prohibits:before_id',
        ]);
        $query = $conversation->messages()->with('attachments');
        $forward = isset($validated['after_id']);
        if ($forward) {
            $query->where('id', '>', $validated['after_id']);
        } elseif (isset($validated['before_id'])) {
            $query->where('id', '<', $validated['before_id']);
        }
        $items = $query->orderBy('id', $forward ? 'asc' : 'desc')->limit(51)->get();
        $hasMore = $items->count() > 50;
        $items = $items->take(50);
        if (!$forward) {
            $items = $items->reverse();
        }
        $otherId = (int) $conversation->first_user_id === (int) $request->user()->id
            ? $conversation->second_user_id : $conversation->first_user_id;
        $other = User::findOrFail($otherId);

        return $this->json([
            'conversation' => [
                'id' => $conversation->id,
                'other_user' => ['id' => $other->id, 'name' => $other->name],
                'can_send' => $other->canUseStaffMessaging(),
            ],
            'messages' => $items->map(fn ($message) => $this->messageData($message))->values(),
            'has_more' => $hasMore,
        ]);
    }

    public function sendMessage(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->participantConversation($request, $conversation);
        $limits = $this->uploadLimits();
        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'client_token' => 'required|uuid',
            'attachments' => 'sometimes|array|list|max:'.$limits['count'],
            'attachments.*' => 'required|file|max:'.intdiv($limits['file'], 1024).'|mimes:pdf,docx,xlsx,jpeg,png|extensions:pdf,docx,xlsx,jpg,jpeg,png',
        ]);
        $body = trim($validated['body'] ?? '');
        $files = $validated['attachments'] ?? [];
        if (array_sum(array_map(fn ($file) => $file->getSize(), $files)) > $limits['total']) {
            throw ValidationException::withMessages(['attachments' => 'These files are too large to send together. Send them in separate messages.']);
        }
        if ($body === '' && !$files) {
            throw ValidationException::withMessages(['body' => 'Enter a message or attach a file.']);
        }
        $userId = (int) $request->user()->id;
        $otherId = (int) $conversation->first_user_id === $userId
            ? $conversation->second_user_id : $conversation->first_user_id;
        abort_unless(User::findOrFail($otherId)->canUseStaffMessaging(), 422,
            'This staff member is unavailable for messaging.');

        $metadata = array_map(static function ($file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $name = preg_replace('/[^\pL\pN ._()-]/u', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

            return [
                'original_name' => Str::substr(trim($name ?? '', ' .') ?: 'attachment', 0, 200).'.'.$extension,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getPathname()),
            ];
        }, $files);
        $stored = [];
        $disk = Storage::disk('staff-attachments');
        try {
            $message = DB::transaction(function () use ($conversation, $userId, $validated, $body, $files, $metadata, $disk, &$stored) {
            // Serialize sends and read markers in this conversation.
            StaffConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $message = StaffMessage::firstOrCreate([
                'sender_id' => $userId,
                'client_token' => $validated['client_token'],
            ], ['conversation_id' => $conversation->id, 'body' => $body]);

            abort_unless((int) $message->conversation_id === (int) $conversation->id
                && $message->body === $body, 409, 'This send token was already used for another message.');
            if ($message->wasRecentlyCreated) {
                foreach ($files as $index => $file) {
                    $path = (string) Str::uuid();
                    $stored[] = $path;
                    if ($disk->putFileAs('', $file, $path) === false) {
                        throw new \RuntimeException('Could not store staff message attachment.');
                    }
                    $message->attachments()->create($metadata[$index] + ['storage_path' => $path]);
                }
                $conversation->touch();
            }

            $message->load('attachments');
            abort_unless($message->attachments->map(fn ($file) => $file->only([
                'original_name', 'mime_type', 'size_bytes', 'checksum',
            ]))->all() === $metadata, 409, 'This send token was already used with different attachments.');

            return $message;
            });
        } catch (\Throwable $error) {
            if ($stored) {
                try {
                    $disk->delete($stored);
                } catch (\Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $error;
        }

        if ($message->wasRecentlyCreated) {
            $this->notifyParticipants($conversation, 'message_created', $userId, (int) $message->id);
        }

        return $this->json(['message' => $this->messageData($message)], $message->wasRecentlyCreated ? 201 : 200);
    }

    public function downloadAttachment(Request $request, int $conversation, int $message, int $attachment): StreamedResponse
    {
        $conversation = $this->participantConversation($request, $conversation);
        $file = $conversation->messages()->findOrFail($message)->attachments()->findOrFail($attachment);
        $disk = Storage::disk('staff-attachments');
        abort_unless($disk->exists($file->storage_path), 404);

        return $disk->download($file->storage_path, $file->original_name, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markRead(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->participantConversation($request, $conversation);
        $validated = $request->validate(['through_id' => 'required|integer|min:1']);
        $userId = (int) $request->user()->id;
        $updated = DB::transaction(function () use ($conversation, $validated, $userId) {
            StaffConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($conversation->messages()->whereKey($validated['through_id'])->exists(), 422,
                'The read marker must belong to this conversation.');

            return $conversation->messages()->where('id', '<=', $validated['through_id'])
                ->where('sender_id', '!=', $userId)->whereNull('read_at')->update(['read_at' => now()]);
        });
        if ($updated > 0) {
            $this->notifyParticipants($conversation, 'messages_read', $userId);
        }

        return $this->json(['updated' => $updated, 'unread_count' => $this->unreadForUser($userId)]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->requireStaff($request);
        $userId = (int) $request->user()->id;
        return $this->json(['unread_count' => $this->unreadForUser($userId)]);
    }

    private function unreadForUser(int $userId): int
    {
        return StaffMessage::whereNull('read_at')->where('sender_id', '!=', $userId)
            ->whereHas('conversation', fn ($query) => $query->forUser($userId))->count();
    }

    private function uploadLimits(): array
    {
        $post = ini_parse_quantity((string) ini_get('post_max_size'));
        $upload = ini_parse_quantity((string) ini_get('upload_max_filesize'));

        return [
            'file' => min(10 * 1024 * 1024, $upload > 0 ? $upload : PHP_INT_MAX),
            'total' => min(25 * 1024 * 1024, $post > 0 ? max(0, $post - 64 * 1024) : PHP_INT_MAX),
            'count' => min(20, max(0, (int) ini_get('max_file_uploads'))),
        ];
    }

    private function requireStaff(Request $request): void
    {
        abort_unless($request->user()->canUseStaffMessaging(), 403, 'Messaging requires an active staff account.');
    }

    private function participantConversation(Request $request, int $id): StaffConversation
    {
        $this->requireStaff($request);

        return StaffConversation::forUser((int) $request->user()->id)->findOrFail($id);
    }

    private function messageData(StaffMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'body' => $message->body,
            'attachment_count' => $message->attachments_count ?? ($message->relationLoaded('attachments') ? $message->attachments->count() : 0),
            'attachments' => $message->relationLoaded('attachments') ? $message->attachments->map(fn ($file) => [
                'name' => $file->original_name,
                'size_bytes' => $file->size_bytes,
                'url' => route('staff-messaging.attachments.download', [
                    'conversation' => $message->conversation_id, 'message' => $message->id, 'attachment' => $file->id,
                ], false),
            ])->all() : [],
            'created_at' => $message->created_at->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
        ];
    }

    private function notifyParticipants(StaffConversation $conversation, string $kind, int $actorId, ?int $messageId = null): void
    {
        try {
            event(new StaffMessagingUpdated(
                [(int) $conversation->first_user_id, (int) $conversation->second_user_id],
                $kind, (int) $conversation->id, $actorId, $messageId
            ));
        } catch (\Throwable $error) {
            // The message has committed. A live delivery failure must not invite a duplicate send.
            Log::warning('Staff messaging live update unavailable.', [
                'conversation_id' => $conversation->id, 'error_type' => get_class($error),
            ]);
        }
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }

}
