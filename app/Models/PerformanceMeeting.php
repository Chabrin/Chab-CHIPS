<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * A meeting about one review: the joint review meeting before the supervisor rates (kind review), or the hearing of an appeal
 * (kind appeal). A review has at most one of each. It is "held" once someone records who came and what was said.
 */
class PerformanceMeeting extends Model
{
    public const KIND_REVIEW = 'review';

    public const KIND_APPEAL = 'appeal';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_HELD = 'held';

    /** The four areas the joint review covers. Anything else discussed is added as an extra item (section "other"). */
    public const SECTIONS = [
        'skill_gaps' => 'Skill gaps',
        'training_needs' => 'Training needs',
        'performance_feedback' => 'Performance feedback',
        'goals' => 'Goals and targets going forward',
    ];

    public const OTHER = 'other';

    /** The areas the committee approves (the rest are feedback). */
    public const APPROVABLE = ['training_needs', 'goals'];

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_meetings';

    protected $fillable = ['review_id', 'kind', 'appeal_id', 'status', 'scheduled_at', 'venue', 'hr_user_id', 'reschedule_count', 'scheduled_by', 'held_at', 'recorded_by'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'held_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function attendees()
    {
        return $this->hasMany(PerformanceMeetingAttendee::class, 'meeting_id')->orderBy('id');
    }

    public function items()
    {
        return $this->hasMany(PerformanceMeetingItem::class, 'meeting_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isHeld(): bool
    {
        return $this->status === self::STATUS_HELD;
    }
}
