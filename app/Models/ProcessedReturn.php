<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProcessedReturn extends Model
{
    use HasFactory;

    protected $table = 'sys_reports_returns_properties';

    protected $fillable = [
        'date_time',
        'period_id',
        'period_date',
        'client_id',
        'property_id',
        'sla_id',
        'return_type_id',
        'reference_number',
        'batch_id',
        'field_officer_id',
        'total_gross_amount',
        'total_commissionable',
        'total_commission',
        'vat_on_commission',
        'total_vat',
        'total_recoveries',
        'total_net_amount',
        'properties_net',
        'other_transactions',
        'current_status_id',
        'created_by',
        'approved_by',
        'approval_date',
        'narration',
        'total_featured_items_amount',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'period_date' => 'date',
        'approval_date' => 'datetime',
        'total_gross_amount' => 'decimal:2',
        'total_commissionable' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'vat_on_commission' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_recoveries' => 'decimal:2',
        'total_net_amount' => 'decimal:2',
        'properties_net' => 'decimal:2',
        'other_transactions' => 'decimal:2',
        'total_featured_items_amount' => 'decimal:2',
    ];

    public $timestamps = false;

    /**
     * Relationship to Client/Landlord
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relationship to Property
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Relationship to Period
     */
    public function period()
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    /**
     * Relationship to Service Agreement (SLA)
     */
    public function sla()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    /**
     * Relationship to Return Type
     */
    public function returnType()
    {
        return $this->belongsTo(ReturnType::class, 'return_type_id');
    }

    /**
     * Relationship to Status
     */
    public function status()
    {
        return $this->belongsTo(Status::class, 'current_status_id');
    }

    /**
     * Relationship to Field Officer
     */
    public function fieldOfficer()
    {
        return $this->belongsTo(Staff::class, 'field_officer_id');
    }

    /**
     * Relationship to Returns Batch
     */
    public function batch()
    {
        return $this->belongsTo(ReturnsBatch::class, 'batch_id');
    }

    /**
     * Relationship to Created By User
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship to Approved By User
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for approved/processed returns only
     */
    public function scopeProcessed($query)
    {
        return $query->whereIn('current_status_id', [5, 17]); // 5=Approved, 17=Processed
    }

    /**
     * Get formatted date
     */
    public function getFormattedDateAttribute()
    {
        return $this->date_time ? $this->date_time->format('d-M-Y') : '-';
    }

    /**
     * Get client full name
     */
    public function getClientNameAttribute()
    {
        if ($this->client) {
            return trim(implode(' ', array_filter([
                $this->client->names,
                $this->client->second_name,
                $this->client->last_name
            ])));
        }
        return '-';
    }

    /**
     * Get property display name (code + name)
     */
    public function getPropertyDisplayAttribute()
    {
        if ($this->property) {
            return ($this->property->reference_number ?? '') . ' - ' . ($this->property->property_name ?? '');
        }
        return '-';
    }

    /**
     * Get status badge HTML
     */
    public function getStatusBadgeAttribute()
    {
        $statusMap = [
            1 => '<span class="badge badge-light-info">Pending</span>',
            5 => '<span class="badge badge-light-success">Approved</span>',
            17 => '<span class="badge badge-light-success">Processed</span>',
        ];

        return $statusMap[$this->current_status_id] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
}
