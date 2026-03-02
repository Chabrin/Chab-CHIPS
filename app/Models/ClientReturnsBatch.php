<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReturnsBatch extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sys_pm_clients_returns_batches';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'batch_number',
        'batch_id',
        'property_id',
        'returns_count',
        'total_trans_amount',
        'total_comm_amount',
        'total_payable_amount',
        'generated_by',
        'generated_at',
        'approval_status',
        'approved_by',
        'approved_at',
        'remarks',
        'is_active'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_trans_amount' => 'decimal:2',
        'total_comm_amount' => 'decimal:2',
        'total_payable_amount' => 'decimal:2',
        'approval_status' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Get the property associated with the batch.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the user who generated the batch.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the user who approved/rejected the batch.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the approval item for this batch.
     */
    public function approvalItem()
    {
        return $this->hasOne(\App\Models\ApprovalItem::class, 'item_id', 'id')
            ->where('item_type', 'returns');
    }

    /**
     * Get the approval status name.
     *
     * @return string
     */
    public function getApprovalStatusNameAttribute(): string
    {
        return match ($this->approval_status) {
            0 => 'Pending',
            1 => 'Approved',
            2 => 'Rejected',
            default => 'Unknown'
        };
    }

    /**
     * Get the approval status badge class.
     *
     * @return string
     */
    public function getApprovalStatusBadgeAttribute(): string
    {
        return match ($this->approval_status) {
            0 => 'badge-warning',
            1 => 'badge-success',
            2 => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Scope a query to only include active batches.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope a query to only include batches with a specific approval status.
     */
    public function scopeApprovalStatus($query, $status)
    {
        return $query->where('approval_status', $status);
    }
}
