<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Observers\AuditLogsObserver;

class TaxBand extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'um_tax_bands';

    protected $fillable = [
        'range_from',
        'range_to',
        'tax_rate',
        'is_last',
        'added_by',
        'updated_by'
    ];

    protected $casts = [
        'range_from' => 'decimal:2',
        'range_to' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_last' => 'boolean',
    ];

    /**
     * Scope to order by range from (ascending)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('range_from');
    }

    /**
     * Get display text for range_to
     */
    public function getRangeToDisplayAttribute()
    {
        return $this->is_last ? 'And Above' : number_format($this->range_to, 2);
    }

    /**
     * Get formatted range_from
     */
    public function getRangeFromDisplayAttribute()
    {
        return number_format($this->range_from, 2);
    }

    /**
     * Get formatted tax_rate
     */
    public function getTaxRateDisplayAttribute()
    {
        return number_format($this->tax_rate, 2) . '%';
    }

    /**
     * Get formatted range display
     */
    public function getRangeDisplayAttribute()
    {
        if ($this->is_last) {
            return number_format($this->range_from, 0) . ' And Above';
        } elseif ($this->range_to) {
            return number_format($this->range_from, 0) . ' - ' . number_format($this->range_to, 0);
        } else {
            return 'Over ' . number_format($this->range_from, 0);
        }
    }
}
