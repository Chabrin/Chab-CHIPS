<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/** One criterion in one review: its name and weight as they were when the cycle was published, and the three ratings. */
class PerformanceReviewItem extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_review_items';

    protected $fillable = [
        'review_id', 'kpi_id', 'name', 'description', 'weight', 'sort_order',
        'self_rating', 'self_comment', 'supervisor_rating', 'supervisor_comment', 'hr_rating', 'hr_comment',
    ];

    protected $casts = [
        'weight' => 'float',
        'self_rating' => 'integer',
        'supervisor_rating' => 'integer',
        'hr_rating' => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }
}
