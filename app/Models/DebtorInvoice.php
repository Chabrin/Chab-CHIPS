<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtorInvoice extends Model
{
    protected $table = 'sys_pm_debtors_invoices';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'date_due',
        'returns_period',
        'zone_id',
        'property_id',
        'unit_id',
        'lease_id',
        'tenant_id',
        'client_id',
        'sla_id',
        'created_by',
        'reference_number',
        'batch_number',
        'narration',
        'debtor_type_id',
        'total_amount',
        'trans_details',
        'current_status_id',
        'processed',
        'comm_ammount',
        'comm_per',
        'vat_ammount',
        'vat_per',
        'commisionable_amount',
        'payable_amount',
        'item_type_id',
        'return_type_id',
        'cycle_id',
        'current_balance',
        'occupancy_status_id',
        'field_officer_id',
        'zone_supervisor_id',
        'zone_manager_id',
        'vat_on_commission',
        'override_period_check',
        'created_date',
        'created_at'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'date_due' => 'datetime',
        'created_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'comm_per' => 'decimal:2',
        'comm_ammount' => 'decimal:2',
        'commisionable_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'vat_per' => 'decimal:2',
        'vat_ammount' => 'decimal:2',
        'vat_on_commission' => 'decimal:2',
        'created_at' => 'datetime'
    ];

    /**
     * Get the tenant that owns the invoice.
     */
    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    /**
     * Get the client that owns the invoice.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the lease associated with the invoice.
     */
    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id');
    }

    /**
     * Get the property associated with the invoice.
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the unit associated with the invoice.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Get the invoice line items.
     */
    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class, 'parent_id');
    }

    /**
     * Get the invoice workflow entries.
     */
    public function workflowEntries()
    {
        return $this->hasMany(InvoiceWorkflow::class, 'parent_id');
    }

    /**
     * Get the payment allocations.
     */
    public function paymentAllocations()
    {
        return $this->hasMany(TenantPaymentAllocation::class, 'invoice_id');
    }

    /**
     * Get payments for this invoice (alias for paymentAllocations).
     */
    public function payments()
    {
        return $this->hasMany(TenantPaymentAllocation::class, 'invoice_id');
    }

    /**
     * Get the invoice items.
     */
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceLineItem::class, 'parent_id');
    }

    /**
     * Scope for tenant invoices only.
     */
    public function scopeTenantInvoices($query)
    {
        return $query->where('debtor_type_id', 2);
    }

    /**
     * Scope for client invoices only.
     */
    public function scopeClientInvoices($query)
    {
        return $query->where('debtor_type_id', '!=', 2);
    }

    /**
     * Get status name.
     */
    public function getStatusNameAttribute()
    {
        $statuses = [
            1 => 'Pending',
            6 => 'Posted',
            8 => 'Partially Cleared',
            9 => 'Cleared'
        ];

        return $statuses[$this->current_status_id] ?? 'Unknown';
    }
}
