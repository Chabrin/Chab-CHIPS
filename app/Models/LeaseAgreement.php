<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseAgreement extends Model
{
    protected $table = 'sys_pm_lease_agreements';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'client_id',
        'property_id',
        'unit_id',
        'tenant_id',
        'date_start',
        'date_end',
        'lease_status',
        'ref_number',
        'created_by_id',
        'sla_id',
        'zone_id',
        'current_status_id',
        'auto_renewal',
        'years',
        'open_ended',
        'initial_water_meter_reading',
        'escalation_rate',
        'service_charges_amount',
        'escalation_frequency',
        'documents',
        'linked_lease_id',
        'contract_template_id',
        'unsigned_pdf_path',
        'signed_pdf_path',
        'contract_sign_token',
        'signer_name',
        'contract_sent_at',
        'contract_signed_at',
        'tenant_verified_at',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'open_ended' => 'boolean',
        'escalation_rate' => 'decimal:2',
        'service_charges_amount' => 'decimal:2',
        'contract_sent_at' => 'datetime',
        'contract_signed_at' => 'datetime',
        'tenant_verified_at' => 'datetime',
    ];

    /**
     * The service agreement linked directly on this lease, if any.
     */
    public function serviceAgreement() {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    /**
     * The service agreement this lease depends on: the one linked on the lease, otherwise the
     * property's most recent one (latest start date), the same rule used across the app.
     */
    public function resolvedServiceAgreement(): ?ServiceAgreement {
        $own = $this->sla_id ? ServiceAgreement::find($this->sla_id) : null;
        if ($own) {
            return $own;
        }

        return $this->property_id
            ? ServiceAgreement::where('property_id', $this->property_id)->orderByRaw('date_start DESC NULLS LAST')->orderByDesc('id')->first()
            : null;
    }

    /**
     * Whether this lease has a service agreement and it is approved.
     */
    public function hasApprovedServiceAgreement(): bool {
        $sla = $this->resolvedServiceAgreement();

        return $sla !== null && $sla->isApproved();
    }

    /**
     * Why this lease cannot be approved yet, or null when it can. Pass an already-resolved
     * service agreement to avoid looking it up again.
     *
     * @param ServiceAgreement|null $sla
     */
    public function approvalBlockReason(?ServiceAgreement $sla = null): ?string {
        $sla = $sla ?? $this->resolvedServiceAgreement();

        if (!$sla) {
            return 'No service agreement exists for this lease. Create and approve one first.';
        }
        if (!$sla->isApproved()) {
            return 'Service agreement ' . ($sla->reference_number ?? '') . ' is not approved yet. Approve the SLA first.';
        }

        return null;
    }

    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function invoiceableItems()
    {
        return $this->hasMany(LeaseAgreementInvoiceableItem::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(LeaseAgreementDocument::class, 'parent_id');
    }

    public function contractTemplate()
    {
        return $this->belongsTo(LeaseContractTemplate::class, 'contract_template_id');
    }
}
