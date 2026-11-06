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
use Illuminate\Validation\ValidationException;

class StaffMessagingController extends Controller
{
    public function index(Request $request)
    {
        $this->requireStaff($request);

        return response()->view('staff-messaging.index')->header('Cache-Control', 'no-store');
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
            ->with(['firstUser:id,name', 'secondUser:id,name', 'latestMessage'])
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
        $query = $conversation->messages();
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
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'client_token' => 'required|uuid',
        ]);
        $body = trim($validated['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Enter a message.']);
        }
        $userId = (int) $request->user()->id;
        $otherId = (int) $conversation->first_user_id === $userId
            ? $conversation->second_user_id : $conversation->first_user_id;
        abort_unless(User::findOrFail($otherId)->canUseStaffMessaging(), 422,
            'This staff member is unavailable for messaging.');

        $message = DB::transaction(function () use ($conversation, $userId, $validated, $body) {
            // Serialize sends and read markers in this conversation.
            StaffConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $message = StaffMessage::firstOrCreate([
                'sender_id' => $userId,
                'client_token' => $validated['client_token'],
            ], ['conversation_id' => $conversation->id, 'body' => $body]);

            abort_unless((int) $message->conversation_id === (int) $conversation->id
                && $message->body === $body, 409, 'This send token was already used for another message.');
            if ($message->wasRecentlyCreated) {
                $conversation->touch();
            }

            return $message;
        });

        if ($message->wasRecentlyCreated) {
            $this->notifyParticipants($conversation, 'message_created', $userId, (int) $message->id);
        }

        return $this->json(['message' => $this->messageData($message)], $message->wasRecentlyCreated ? 201 : 200);
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
