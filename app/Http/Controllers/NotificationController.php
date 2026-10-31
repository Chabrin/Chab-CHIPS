<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Poll unread arrivals for this user. The first request establishes a baseline
     * so signing in does not replay every historical notification as a new toast.
     */
    public function feed(Request $request)
    {
        $validated = $request->validate(['after_id' => 'nullable|integer|min:0']);
        $user = $request->user();
        $query = Notification::where('notifiable_type', get_class($user))->where('notifiable_id', $user->id);
        $latest = (int) (clone $query)->max('id');
        $items = collect();
        if (isset($validated['after_id'])) {
            $items = (clone $query)->unread()->where('id', '>', $validated['after_id'])
                ->orderBy('id')->limit(51)->get(['id', 'type', 'data', 'title', 'message', 'action_url', 'read_at']);
        }
        $hasMore = $items->count() > 50;
        $items = $items->take(50)->values();
        $this->personalizeLeaveMessages($items, $user);
        $items->each(fn ($item) => $item->makeHidden(['type', 'data']));

        return response()->json([
            'notifications' => $items,
            'unread_count' => (clone $query)->unread()->count(),
            'cursor' => $hasMore ? (int) $items->last()->id : max($latest, (int) ($items->last()?->id ?? 0), (int) ($validated['after_id'] ?? 0)),
            'has_more' => $hasMore,
        ])->header('Cache-Control', 'no-store');
    }

    /** Get the authenticated user's notification history. */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by read/unread
        if ($request->has('unread_only') && $request->unread_only) {
            $query->unread();
        }

        $notifications = $query->paginate(20);
        $this->personalizeLeaveMessages($notifications->getCollection(), $user);

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $this->getUnreadCount(),
        ]);
    }

    private function personalizeLeaveMessages($items, $user): void
    {
        $legacy = $items->filter(fn ($item) => isset($item->data['staff_id'])
            && str_starts_with((string) $item->type, 'Leave')
            && str_contains((string) $item->message, ' applied for leave ('));
        if ($legacy->isEmpty()) return;
        $staffId = $user->linkedStaff()?->id;
        foreach ($legacy as $item) {
            $isApplicant = $staffId && (int) $item->data['staff_id'] === (int) $staffId;
            // The saved private link identifies alerts addressed to the relief recipient.
            $path = parse_url((string) $item->action_url, PHP_URL_PATH) ?: '';
            $isRelief = (bool) preg_match('#^/self-service/leave-handovers/[0-9]+$#', $path);
            $copy = \App\Services\Leave\LeaveNotificationMessage::legacyDisplay($item->type, $item->message, (bool) $isApplicant, $isRelief);
            if ($copy) {
                $item->title = $copy[0];
                $item->message = $copy[1];
            }
        }
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        
        return Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        
        $notification = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'unread_count' => $this->getUnreadCount(),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        
        Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        $notification = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
            'unread_count' => $this->getUnreadCount(),
        ]);
    }

    /**
     * Get notification details
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $notification = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->findOrFail($id);

        // Mark as read when viewed
        if (!$notification->isRead()) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'notification' => $notification,
            'unread_count' => $this->getUnreadCount(),
        ]);
    }
}
