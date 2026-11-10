<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantPayment extends Model
{
    use HasFactory;

    protected $table = 'sys_pm_tenants_payments';

    protected $fillable = [
        'date_time',
        'payment_date',
        'reference',
        'narration',
        'created_by',
        'zone_id',
        'client_id',
        'property_id',
        'unit_id',
        'lease_id',
        'tenant_id',
        'current_status_id',
        'amount',
        'invoice_id',
        'trans_details',
        'sla_id',
        'channel_id',
        'receipt_number',
        'recovery_type_id',
        'field_officer_id',
        'zone_supervisor_id',
        'zone_manager_id',
        'extract_id',
        'tenant_aid'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'payment_date' => 'datetime',
        'amount' => 'decimal:2'
    ];

    public $timestamps = false;

    /**
     * Get the tenant associated with this payment
     */
    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    /**
     * Get the property associated with this payment
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the unit associated with this payment
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Get the lease associated with this payment
     */
    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    /**
     * Get the status associated with this payment
     */
    public function status()
    {
        return $this->belongsTo(Status::class, 'current_status_id');
    }

    /**
     * Get the payment channel
     */
    public function channel()
    {
        return $this->belongsTo(PaymentChannel::class, 'channel_id');
    }

    /**
     * Get all allocations for this payment
     */
    public function allocations()
    {
        return $this->hasMany(TenantPaymentAllocation::class, 'payment_id');
    }

    /**
     * Get the total allocated amount
     */
    public function getTotalAllocatedAttribute()
    {
        return $this->allocations()->sum('amount_allocated') ?? 0;
    }

    /**
     * Get the unallocated amount
     */
    public function getUnallocatedAmountAttribute()
    {
        return $this->amount - $this->total_allocated;
    }

    /**
     * Check if payment is fully allocated
     */
    public function getIsFullyAllocatedAttribute()
    {
        return $this->unallocated_amount <= 0;
    }

    /**
     * Check if payment is partially allocated
     */
    public function getIsPartiallyAllocatedAttribute()
    {
        $allocated = $this->total_allocated;
        return $allocated > 0 && $allocated < $this->amount;
    }

    /**
     * Check if payment is unallocated
     */
    public function getIsUnallocatedAttribute()
    {
        return $this->total_allocated == 0;
    }

    /**
     * Get encrypted ID
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}
