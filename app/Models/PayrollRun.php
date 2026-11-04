<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_WITH_ACCOUNTANT = 'with_accountant';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVERSED = 'reversed';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_WITH_ACCOUNTANT => 'With Accountant',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REVERSED => 'Reversed',
    ];

    /**
     * Log changes to payroll runs in the audit trail.
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'payroll_runs';

    protected $fillable = [
        'period_id',
        'status',
        'processed_by',
        'processed_at',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'return_comment',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * The payroll period this run belongs to.
     */
    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    /**
     * The run's history, newest first.
     */
    public function logs()
    {
        return $this->hasMany(PayrollRunLog::class, 'payroll_run_id')->orderByDesc('id');
    }

    /**
     * Add an entry to the run's history.
     */
    public function record(string $action, ?int $userId, ?string $comment = null): void
    {
        $this->logs()->create([
            'action' => $action,
            'comment' => $comment,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * The status as shown to users, for example "With Accountant".
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
