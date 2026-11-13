<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/** A review round for a period (for example "Q1 2027"). Draft until HR publishes it, then staff are rated, then closed. */
class PerformanceCycle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_cycles';

    protected $fillable = ['name', 'period_start', 'period_end', 'open_date', 'deadline', 'status', 'published_at', 'closed_at', 'created_by'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'open_date' => 'date',
        'deadline' => 'date',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function reviews()
    {
        return $this->hasMany(PerformanceReview::class, 'cycle_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
