<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * A staff member's appeal against their finalised review. The committee hears it and decides; the decision is final. A review
 * has at most one appeal.
 */
class PerformanceAppeal extends Model
{
    public const STATUS_FILED = 'filed';

    public const STATUS_DECIDED = 'decided';

    public const OUTCOME_UPHELD = 'upheld';

    public const OUTCOME_PARTLY = 'partly_upheld';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_LABELS = [
        self::OUTCOME_UPHELD => 'Upheld',
        self::OUTCOME_PARTLY => 'Partly upheld',
        self::OUTCOME_REJECTED => 'Rejected',
    ];

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_appeals';

    protected $fillable = [
        'review_id', 'filed_by_user_id', 'on_behalf', 'late_reason', 'reason', 'status', 'outcome', 'decision_reason',
        'score_before', 'score_after', 'decided_by', 'decided_at',
    ];

    protected $casts = [
        'on_behalf' => 'boolean',
        'score_before' => 'float',
        'score_after' => 'float',
        'decided_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function items()
    {
        return $this->hasMany(PerformanceAppealItem::class, 'appeal_id')->orderBy('id');
    }

    public function isDecided(): bool
    {
        return $this->status === self::STATUS_DECIDED;
    }
}
