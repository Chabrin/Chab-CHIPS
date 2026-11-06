<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StaffConversation extends Model
{
    protected $table = 'cb_staff_conversations';

    protected $fillable = [
        'first_user_id',
        'second_user_id',
    ];

    public function scopeForUser($query, int $userId)
    {
        return $query->where(fn ($participants) => $participants
            ->where('first_user_id', $userId)->orWhere('second_user_id', $userId));
    }

    public function firstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_user_id');
    }

    public function secondUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StaffMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(StaffMessage::class, 'conversation_id')
            ->latestOfMany('id');
    }

    public function hasParticipant(int $userId): bool
    {
        return (int) $this->first_user_id === $userId
            || (int) $this->second_user_id === $userId;
    }
}
