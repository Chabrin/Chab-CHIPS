<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMessage extends Model
{
    protected $table = 'cb_staff_messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
        'client_token',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StaffMessageAttachment::class, 'message_id')->orderBy('id');
    }
}
