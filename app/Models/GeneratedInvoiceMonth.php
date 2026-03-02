<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedInvoiceMonth extends Model
{
    protected $table = 'sys_pm_generated_invoice_months';

    public $timestamps = false;

    protected $fillable = [
        'month_year',
        'invoice_count',
        'generated_by',
        'generated_at',
        'notes'
    ];

    protected $casts = [
        'invoice_count' => 'integer',
        'generated_by' => 'integer',
        'generated_at' => 'datetime'
    ];

    /**
     * Get the user who generated the invoices
     */
    public function generatedByUser()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Scope to get months for a specific year
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('month_year', 'LIKE', $year . '-%');
    }

    /**
     * Check if a specific month has been generated
     */
    public static function isMonthGenerated($monthYear)
    {
        return self::where('month_year', $monthYear)->exists();
    }

    /**
     * Get formatted month name
     */
    public function getFormattedMonthAttribute()
    {
        return date('F Y', strtotime($this->month_year . '-01'));
    }
}
