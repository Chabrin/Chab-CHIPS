<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $table = 'cb_ticket_attachments';

    protected $fillable = [
        'ticket_id',
        'reply_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // ── Encrypted ID accessor (used in download URLs) ─────────────────
    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'reply_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Human-readable file size  e.g. "2.3 MB"
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size ?? 0;

        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 1) . ' MB';
        if ($bytes >= 1_024)     return round($bytes / 1_024, 0)     . ' KB';

        return $bytes . ' B';
    }

    /**
     * True if the attachment is an image type.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}