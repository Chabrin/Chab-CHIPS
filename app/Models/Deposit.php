<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $table = 'sys_pm_deposits';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'tenant_id',
        'client_id',
        'property_id',
        'unit_id',
        'lease_id',
        'invoice_id',
        'amount',
        'payment_mode',
        'reference_number',
        'narration',
        'created_by',
        'created_date'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'created_date' => 'datetime',
        'amount' => 'decimal:2'
    ];

    /**
     * Get the tenant that owns the deposit.
     */
    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    /**
     * Get the client that owns the deposit.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the property associated with the deposit.
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the unit associated with the deposit.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Get the lease associated with the deposit.
     */
    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    /**
     * Get the invoices linked to this deposit.
     * Assumes there's a deposit_allocations table linking deposits to invoices
     */
    public function invoices()
    {
        return $this->belongsToMany(
            DebtorInvoice::class,
            'sys_pm_deposit_allocations',
            'deposit_id',
            'invoice_id'
        )->withPivot('amount_allocated', 'date_allocated');
    }

    /**
     * Get the deposit allocations.
     */
    public function allocations()
    {
        return $this->hasMany(DepositAllocation::class, 'deposit_id');
    }

    /**
     * Scope for deposits with date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        if ($from) {
            $query->whereDate('date_time', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_time', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope for tenant deposits.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get formatted amount attribute.
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }
}
