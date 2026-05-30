<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    protected $table = 'cb_ticket_activities';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'description',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The user who triggered this activity (null = system action).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}