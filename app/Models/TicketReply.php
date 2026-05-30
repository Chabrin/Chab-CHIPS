<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketReply extends Model
{
    protected $table = 'cb_ticket_replies';

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'sender_role',
        'body',
        'is_internal_note',
        'read_by_admin',
        'read_by_user',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
        'read_by_admin'    => 'boolean',
        'read_by_user'     => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The user who sent this reply (null for system messages).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'reply_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function isFromAdmin(): bool
    {
        return $this->sender_role === 'admin';
    }

    public function isFromUser(): bool
    {
        return $this->sender_role === 'user';
    }

    public function isSystemMessage(): bool
    {
        return $this->sender_role === 'system';
    }

    public function isInternalNote(): bool
    {
        return (bool) $this->is_internal_note;
    }
}