<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened to a review, who did it, and for whom (when HR entered something on someone's behalf). Never edited. */
class PerformanceReviewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'performance_review_events';

    protected $fillable = ['review_id', 'action', 'from_status', 'to_status', 'actor_user_id', 'on_behalf_of_staff_id', 'note', 'details'];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function record(PerformanceReview $review, string $action, ?int $actorUserId, array $extra = []): self
    {
        return static::create([
            'review_id' => $review->id,
            'action' => $action,
            'from_status' => $extra['from_status'] ?? null,
            'to_status' => $extra['to_status'] ?? $review->status,
            'actor_user_id' => $actorUserId,
            'on_behalf_of_staff_id' => $extra['on_behalf_of_staff_id'] ?? null,
            'note' => $extra['note'] ?? null,
            'details' => $extra['details'] ?? null,
        ]);
    }
}
