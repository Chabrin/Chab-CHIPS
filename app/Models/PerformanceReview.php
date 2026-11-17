<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * One staff member's review in one cycle. It holds a copy of the criteria (items), who the supervisor is, and where the
 * review has got to. Who may see which part of it is decided in App\Services\Performance\PerformanceAccess and the
 * PerformanceReviewPresenter, never in a view.
 */
class PerformanceReview extends Model
{
    public const STATUS_AWAITING_SELF = 'awaiting_self';

    /** The self-rating is in: the joint review meeting (staff member, supervisor, HR and the committee) has to be held next. */
    public const STATUS_AWAITING_MEETING = 'awaiting_meeting';

    public const STATUS_AWAITING_SUPERVISOR = 'awaiting_supervisor';

    public const STATUS_AWAITING_HR = 'awaiting_hr';

    public const STATUS_FINALISED = 'finalised';

    public const STATUS_LABELS = [
        self::STATUS_AWAITING_SELF => 'Waiting for self-rating',
        self::STATUS_AWAITING_MEETING => 'Waiting for the joint review meeting',
        self::STATUS_AWAITING_SUPERVISOR => 'Waiting for supervisor',
        self::STATUS_AWAITING_HR => 'Waiting for HR',
        self::STATUS_FINALISED => 'Finalised',
    ];

    public const ROUTE_SUPERVISOR = 'supervisor';

    public const ROUTE_HR = 'hr';

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_reviews';

    protected $fillable = [
        'cycle_id', 'staff_id', 'position_id', 'position_name', 'reports_to_position_id', 'supervisor_staff_id',
        'route', 'route_reason', 'status', 'self_skipped', 'self_summary', 'supervisor_summary', 'final_comment', 'final_score',
        'self_submitted_at', 'supervisor_submitted_at', 'finalised_at', 'finalised_by', 'metrics', 'metrics_taken_at',
    ];

    protected $casts = [
        'self_skipped' => 'boolean',
        'final_score' => 'float',
        'metrics' => 'array',
        'self_submitted_at' => 'datetime',
        'supervisor_submitted_at' => 'datetime',
        'finalised_at' => 'datetime',
        'metrics_taken_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Staff::class, 'supervisor_staff_id');
    }

    public function items()
    {
        return $this->hasMany(PerformanceReviewItem::class, 'review_id')->orderBy('sort_order')->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(PerformanceReviewEvent::class, 'review_id')->orderBy('id');
    }

    public function meetings()
    {
        return $this->hasMany(PerformanceMeeting::class, 'review_id');
    }

    /** The joint review meeting, when there is one. */
    public function reviewMeeting()
    {
        return $this->hasOne(PerformanceMeeting::class, 'review_id')->where('kind', PerformanceMeeting::KIND_REVIEW);
    }

    /** The hearing of the appeal, when there is one. */
    public function hearing()
    {
        return $this->hasOne(PerformanceMeeting::class, 'review_id')->where('kind', PerformanceMeeting::KIND_APPEAL);
    }

    public function appeal()
    {
        return $this->hasOne(PerformanceAppeal::class, 'review_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isFinalised(): bool
    {
        return $this->status === self::STATUS_FINALISED;
    }
}
