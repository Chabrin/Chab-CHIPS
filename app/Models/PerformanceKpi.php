<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing a position is rated on (for example "Attendance"). HR edits these; a published cycle copies them into each
 * review, so changing or deleting one here never changes a review that was already made.
 */
class PerformanceKpi extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_kpis';

    protected $fillable = ['position_id', 'name', 'description', 'weight', 'sort_order', 'is_active', 'created_by'];

    protected $casts = [
        'weight' => 'float',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /** Whether any review was made from this criterion, which is why it can only be switched off, not deleted. */
    public function isUsed(): bool
    {
        return PerformanceReviewItem::where('kpi_id', $this->id)->exists();
    }
}
