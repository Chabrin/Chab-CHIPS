<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ChequeBook extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_fin_cheque_books';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'bank_account_id',
        'first_cheque_number',
        'leafs',
        'reference_number',
        'current_status_id',
        'created_by_id',
        'notes',
    ];

    protected $casts = [
        'date_time'         => 'datetime',
        'bank_account_id'   => 'integer',
        'leafs'             => 'integer',
        'current_status_id' => 'integer',
        'created_by_id'     => 'integer',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'bank_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function leaves()
    {
        return $this->hasMany(ChequeLeaf::class, 'parent_id');
    }

    public function getAvailableCountAttribute(): int
    {
        return $this->leaves()->where('current_status_id', ChequeLeaf::STATUS_AVAILABLE)->count();
    }

    public function getUsedCountAttribute(): int
    {
        return $this->leaves()->where('current_status_id', ChequeLeaf::STATUS_USED)->count();
    }

    public function getCancelledCountAttribute(): int
    {
        return $this->leaves()->where('current_status_id', ChequeLeaf::STATUS_CANCELLED)->count();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->current_status_id) {
            1 => 'Active',
            2 => 'Exhausted',
            3 => 'Cancelled',
            default => 'Unknown',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ((int) $this->current_status_id) {
            1 => 'badge-light-success',
            2 => 'badge-light-secondary',
            3 => 'badge-light-danger',
            default => 'badge-light-secondary',
        };
    }
}
