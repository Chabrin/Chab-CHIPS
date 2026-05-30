<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketAttachment;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    // ═══════════════════════════════════════════════════════════════
    //  SHARED CONSTANTS
    // ═══════════════════════════════════════════════════════════════

    private const CATEGORIES = [
        'Technical Issue',
        'Billing & Payments',
        'Account Access',
        'Feature Request',
        'Data / Reports',
        'General Enquiry',
        'Other',
    ];

    // ═══════════════════════════════════════════════════════════════
    //  USER-FACING — My Tickets list
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /tickets
     * Show the authenticated user's own tickets.
     */
    public function myTickets(Request $request)
    {
        $query = Ticket::where('user_id', Auth::id())
            ->withCount('replies')
            ->withCount(['replies as unread_user_count' => function ($q) {
                $q->where('sender_role', 'admin')
                  ->where('is_internal_note', false)
                  ->where('read_by_user', false);
            }])
            ->latest('updated_at');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('ticket_number', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(15);

        return view('tickets.my', compact('tickets'));
    }

    // ═══════════════════════════════════════════════════════════════
    //  USER-FACING — Create ticket
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /tickets/create
     */
    public function create()
    {
        $categories = self::CATEGORIES;
        return view('tickets.create', compact('categories'));
    }

    /**
     * POST /tickets
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject'        => 'required|string|max:150',
            'category'       => 'required|string|in:' . implode(',', self::CATEGORIES),
            'description'    => 'required|string|max:3000',
            'priority'       => 'required|in:low,medium,high,urgent',
            'attachments'    => 'nullable|array|max:5',
            'attachments.*'  => 'file|max:5120|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip,csv',
        ]);

        $ticket = Ticket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'user_id'       => Auth::id(),
            'subject'       => $request->subject,
            'category'      => $request->category,
            'description'   => $request->description,
            'priority'      => $request->priority,
            'status'        => 'open',
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storeAttachment($file, $ticket->id, null);
            }
        }

        // Activity log
        $this->logActivity($ticket, 'Ticket created by ' . Auth::user()->name);

        // return response()->json([
        //     'success'  => true,
        //     'message'  => 'Ticket #' . $ticket->ticket_number . ' submitted. Our team will get back to you shortly.',
        //     'redirect' => route('tickets.show', $ticket->encrypted_id),
        // ]);

        // return redirect()->route('tickets.show', $ticket->encrypted_id)->with('success', 'Ticket submitted successfully.');
        return redirect()->route('tickets.my')->with('success', 'Ticket submitted successfully. You can view it in your ticket list.');
    }

    // ═══════════════════════════════════════════════════════════════
    //  USER-FACING — View single ticket (user's view)
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /tickets/{encryptedId}
     */
    public function show(string $encryptedId)
    {
        $ticket = $this->resolveTicket($encryptedId);

        // Ensure the authenticated user owns this ticket
        abort_if($ticket->user_id !== Auth::id(), 403);

        $ticket->load([
            'user',
            'assignedTo',
            'replies' => fn ($q) => $q->where('is_internal_note', false)->with('sender', 'attachments')->orderBy('created_at'),
            'attachments',
        ]);

        $adminUsers = User::orderBy('name')->get();

        return view('tickets.show', compact('ticket', 'adminUsers'));
    }

    /**
     * POST /tickets/{encryptedId}/reply  (user reply)
     */
    public function reply(Request $request, string $encryptedId)
    {
        $ticket = $this->resolveTicket($encryptedId);
        abort_if($ticket->user_id !== Auth::id(), 403);
        abort_if($ticket->status === 'closed', 403, 'This ticket is closed.');

        $request->validate([
            'body'           => 'required|string|max:5000',
            'attachments'    => 'nullable|array|max:5',
            'attachments.*'  => 'file|max:5120|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip,csv',
        ]);

        $reply = TicketReply::create([
            'ticket_id'       => $ticket->id,
            'sender_id'       => Auth::id(),
            'sender_role'     => 'user',
            'body'            => $request->body,
            'is_internal_note'=> false,
            'read_by_admin'   => false,
            'read_by_user'    => true,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storeAttachment($file, $ticket->id, $reply->id);
            }
        }

        // If ticket was resolved, reopen it when the user replies
        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'in_progress']);
            $this->logActivity($ticket, 'Status changed to In Progress (user replied after resolved)');
        }

        $ticket->touch();
        $this->logActivity($ticket, Auth::user()->name . ' sent a reply');

        return response()->json(['success' => true, 'message' => 'Reply sent.']);
    }

    /**
     * POST /tickets/{encryptedId}/mark-read
     */
    public function markRead(string $encryptedId)
    {
        $ticket = $this->resolveTicket($encryptedId);
        abort_if($ticket->user_id !== Auth::id(), 403);

        TicketReply::where('ticket_id', $ticket->id)
            ->where('sender_role', 'admin')
            ->where('read_by_user', false)
            ->update(['read_by_user' => true]);

        return response()->json(['ok' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ADMIN — Ticket index
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /admin/tickets
     */
    public function adminIndex(Request $request)
    {
        $query = Ticket::with('user', 'assignedTo')
            ->withCount('replies')
            ->withCount(['replies as unread_admin_count' => function ($q) {
                $q->where('sender_role', 'user')->where('read_by_admin', false);
            }])
            ->latest('updated_at');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('ticket_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%' . $request->search . '%'));
            });
        }

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('priority'))    $query->where('priority', $request->priority);
        if ($request->filled('category'))    $query->where('category', $request->category);

        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        $tickets    = $query->paginate(20);
        $adminUsers = User::orderBy('name')->get();
        $categories = self::CATEGORIES;
        $stats      = [
            'open'        => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'resolved'    => Ticket::where('status', 'resolved')->count(),
            'closed'      => Ticket::where('status', 'closed')->count(),
        ];

        return view('tickets.index', compact('tickets', 'adminUsers', 'categories', 'stats'));
    }

    /**
     * GET /admin/tickets/export  (simple CSV)
     */
    public function adminExport(Request $request)
    {
        $query = Ticket::with('user', 'assignedTo')->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);

        $tickets = $query->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tickets_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ticket #', 'Subject', 'Category', 'Priority', 'Status', 'Submitted By', 'Assigned To', 'Replies', 'Created', 'Updated']);
            foreach ($tickets as $t) {
                fputcsv($handle, [
                    $t->ticket_number,
                    $t->subject,
                    $t->category,
                    $t->priority,
                    $t->status,
                    $t->user->name ?? '',
                    $t->assignedTo->name ?? '',
                    $t->replies_count ?? 0,
                    $t->created_at->format('d M Y H:i'),
                    $t->updated_at->format('d M Y H:i'),
                ]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'tickets.csv', $headers);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ADMIN — View & handle a single ticket
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /admin/tickets/{encryptedId}
     */
    public function adminShow(string $encryptedId)
    {
        $ticket = $this->resolveTicket($encryptedId);

        $ticket->load([
            'user',
            'assignedTo',
            'replies'     => fn ($q) => $q->with('sender', 'attachments')->orderBy('created_at'),
            'attachments',
            'activities'  => fn ($q) => $q->latest()->limit(20),
        ]);

        // Mark user messages as read by admin
        TicketReply::where('ticket_id', $ticket->id)
            ->where('sender_role', 'user')
            ->where('read_by_admin', false)
            ->update(['read_by_admin' => true]);

        $adminUsers = User::orderBy('name')->get();

        return view('tickets.admin.show', compact('ticket', 'adminUsers'));
    }

    /**
     * POST /admin/tickets/{encryptedId}/reply  (admin reply)
     */
    public function adminReply(Request $request, string $encryptedId)
    {
        $ticket = $this->resolveTicket($encryptedId);
        abort_if($ticket->status === 'closed', 403, 'This ticket is closed.');

        $request->validate([
            'body'             => 'required|string|max:10000',
            'is_internal_note' => 'nullable|boolean',
            'change_status'    => 'nullable|in:in_progress,resolved',
            'attachments'      => 'nullable|array|max:5',
            'attachments.*'    => 'file|max:5120|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip,csv',
        ]);

        $isNote = (bool) $request->is_internal_note;

        $reply = TicketReply::create([
            'ticket_id'        => $ticket->id,
            'sender_id'        => Auth::id(),
            'sender_role'      => 'admin',
            'body'             => $request->body,
            'is_internal_note' => $isNote,
            'read_by_admin'    => true,
            'read_by_user'     => false,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storeAttachment($file, $ticket->id, $reply->id);
            }
        }

        // Optionally change status on reply
        $newStatus = null;
        if ($request->filled('change_status') && !$isNote) {
            $newStatus = $request->change_status;
            $ticket->update(['status' => $newStatus]);
            $this->logActivity($ticket, 'Status changed to ' . ucwords(str_replace('_', ' ', $newStatus)) . ' by ' . Auth::user()->name);
        }

        $ticket->touch();

        $action = $isNote ? 'added an internal note' : 'sent a reply';
        $this->logActivity($ticket, Auth::user()->name . ' ' . $action);

        return response()->json(['success' => true, 'message' => $isNote ? 'Internal note added.' : 'Reply sent.']);
    }

    /**
     * PATCH /admin/tickets/{encryptedId}/status
     * Quick status change (Mark Resolved / Close Ticket buttons).
     */
    public function adminUpdateStatus(Request $request, string $encryptedId)
    {
        $request->validate(['status' => 'required|in:open,in_progress,resolved,closed']);

        $ticket = $this->resolveTicket($encryptedId);
        $old    = $ticket->status;
        $ticket->update(['status' => $request->status]);

        // Auto-system reply on close/resolve
        if (in_array($request->status, ['closed', 'resolved'])) {
            $messages = [
                'closed'   => 'This ticket has been closed. If you need further assistance, please open a new ticket.',
                'resolved' => 'This ticket has been marked as resolved. If the issue persists, you can reply to reopen it.',
            ];
            TicketReply::create([
                'ticket_id'        => $ticket->id,
                'sender_id'        => null,
                'sender_role'      => 'system',
                'body'             => $messages[$request->status],
                'is_internal_note' => false,
                'read_by_admin'    => true,
                'read_by_user'     => false,
            ]);
        }

        $this->logActivity($ticket, 'Status changed from ' . $old . ' to ' . $request->status . ' by ' . Auth::user()->name);

        return response()->json(['success' => true, 'message' => 'Ticket status updated to ' . ucwords(str_replace('_', ' ', $request->status)) . '.']);
    }

    /**
     * PATCH /admin/tickets/{encryptedId}/assign
     * Update assignment and/or priority.
     */
    public function adminAssign(Request $request, string $encryptedId)
    {
        $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'priority'    => 'required|in:low,medium,high,urgent',
        ]);

        $ticket = $this->resolveTicket($encryptedId);

        $oldAssignee = $ticket->assigned_to;
        $oldPriority = $ticket->priority;

        $ticket->update([
            'assigned_to' => $request->assigned_to ?: null,
            'priority'    => $request->priority,
        ]);

        if ($oldAssignee != $request->assigned_to) {
            $assigneeName = $request->assigned_to
                ? (User::find($request->assigned_to)->name ?? 'Unknown')
                : 'Unassigned';
            $this->logActivity($ticket, 'Ticket assigned to ' . $assigneeName . ' by ' . Auth::user()->name);
        }

        if ($oldPriority !== $request->priority) {
            $this->logActivity($ticket, 'Priority changed to ' . $request->priority . ' by ' . Auth::user()->name);
        }

        // Auto set to in_progress when first assigned
        if (!$oldAssignee && $request->assigned_to && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
            $this->logActivity($ticket, 'Status auto-changed to In Progress on assignment');
        }

        return response()->json(['success' => true, 'message' => 'Assignment updated.']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  SHARED — Download attachment
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /tickets/attachments/{encryptedId}/download
     */
    public function downloadAttachment(string $encryptedId)
    {
        $attachment = TicketAttachment::where(
            'id', decrypt($encryptedId)
        )->firstOrFail();

        // Check access: user must own the ticket, or be admin
        $ticket = $attachment->ticket;
        $isOwner = $ticket->user_id === Auth::id();
        $isAdmin = Auth::user()->is_admin;

        abort_if(!$isOwner && !$isAdmin, 403);

        abort_if(!Storage::disk('private')->exists($attachment->path), 404);

        return Storage::disk('private')->download($attachment->path, $attachment->original_name);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function resolveTicket(string $encryptedId): Ticket
    {
        return Ticket::findOrFail(decrypt($encryptedId));
    }

    private function generateTicketNumber(): string
    {
        $prefix = 'TKT-' . date('Y');
        $last   = Ticket::where('ticket_number', 'like', $prefix . '-%')
                        ->orderByDesc('id')
                        ->value('ticket_number');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;
        return $prefix . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function storeAttachment($file, int $ticketId, ?int $replyId): void
    {
        $path = $file->store('tickets/' . $ticketId, 'private');

        TicketAttachment::create([
            'ticket_id'     => $ticketId,
            'reply_id'      => $replyId,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);
    }

    private function logActivity(Ticket $ticket, string $description): void
    {
        TicketActivity::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => Auth::id(),
            'description' => $description,
        ]);
    }
}