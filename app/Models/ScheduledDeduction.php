<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Money a staff member owes, taken from their pay a set amount a month until it is paid off (UAT A6).
 *
 * Status: on_hold (no signed consent form yet, so nothing is taken), active, paused (HR stopped it for now, with a reason),
 * closed (stopped early, with a reason) and completed (paid off). Payroll takes only active deductions.
 */
class ScheduledDeduction extends Model
{
    public const ON_HOLD = 'on_hold';
    public const ACTIVE = 'active';
    public const PAUSED = 'paused';
    public const CLOSED = 'closed';
    public const COMPLETED = 'completed';

    public const STATUS_LABELS = [
        self::ON_HOLD => 'On hold',
        self::ACTIVE => 'Active',
        self::PAUSED => 'Paused',
        self::CLOSED => 'Closed early',
        self::COMPLETED => 'Paid off',
    ];

    /** Bootstrap colour for each status badge. */
    public const STATUS_COLOURS = [
        self::ON_HOLD => 'warning',
        self::ACTIVE => 'success',
        self::PAUSED => 'secondary',
        self::CLOSED => 'dark',
        self::COMPLETED => 'primary',
    ];

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_scheduled_deductions';

    protected $fillable = [
        'staff_id', 'type_id', 'description', 'total_amount', 'monthly_amount', 'months', 'start_month', 'end_month', 'balance', 'status',
        'consent_path', 'consent_name', 'consent_at', 'consent_by', 'paused_at', 'pause_reason', 'closed_at', 'closed_by', 'close_reason', 'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'monthly_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'months' => 'integer',
        'start_month' => 'date',
        'end_month' => 'date',
        'consent_at' => 'datetime',
        'paused_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function type()
    {
        return $this->belongsTo(DeductionType::class, 'type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function consenter()
    {
        return $this->belongsTo(User::class, 'consent_by');
    }

    public function repeats()
    {
        return $this->hasMany(ScheduledDeductionRepeat::class, 'deduction_id');
    }

    public function events()
    {
        return $this->hasMany(ScheduledDeductionEvent::class, 'deduction_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColourAttribute(): string
    {
        return self::STATUS_COLOURS[$this->status] ?? 'secondary';
    }

    public function hasConsent(): bool
    {
        return !empty($this->consent_path);
    }

    /** Still running: something can be done to it. Closed and paid-off ones are finished. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::ON_HOLD, self::ACTIVE, self::PAUSED], true);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::ON_HOLD, self::ACTIVE, self::PAUSED]);
    }
}
