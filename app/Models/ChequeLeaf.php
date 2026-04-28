<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ChequeLeaf extends Model
{
    // current_status_id values
    const STATUS_AVAILABLE = 1;
    const STATUS_LOCKED    = 2;
    const STATUS_USED      = 3;
    const STATUS_CANCELLED = 4;

    protected $table = 'sys_fin_cheque_books_261_repeat';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'parent_id',
        'cheque_number',
        'current_status_id',
        'bank_account_id',
        'locked_by_id',
        'locked_at',
        'lock_token',
        'used_by_id',
        'used_at',
        'used_for',
        'cancelled_by_id',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'current_status_id' => 'integer',
        'parent_id'         => 'integer',
        'bank_account_id'   => 'integer',
        'locked_at'         => 'datetime',
        'used_at'           => 'datetime',
        'cancelled_at'      => 'datetime',
        'locked_by_id'      => 'integer',
        'used_by_id'        => 'integer',
        'cancelled_by_id'   => 'integer',
    ];

    public function chequeBook()
    {
        return $this->belongsTo(ChequeBook::class, 'parent_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('current_status_id', self::STATUS_AVAILABLE);
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('current_status_id', self::STATUS_LOCKED);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->current_status_id) {
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_LOCKED    => 'Locked',
            self::STATUS_USED      => 'Used',
            self::STATUS_CANCELLED => 'Cancelled',
            default                => 'Unknown',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->current_status_id) {
            self::STATUS_AVAILABLE => 'badge-light-success',
            self::STATUS_LOCKED    => 'badge-light-warning',
            self::STATUS_USED      => 'badge-light-secondary',
            self::STATUS_CANCELLED => 'badge-light-danger',
            default                => 'badge-light-secondary',
        };
    }
}
