<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAdvance extends Model
{
    protected $table = 'sys_pm_clients_advances_requisitions';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'requisition_date',
        'narration',
        'created_by',
        'zone_id',
        'client_id',
        'property_id',
        'current_status_id',
        'amount',
        'repayment_months',
        'interest_rate',
        'principal_start_date',
        'disbursement_date',
        'interest_amount',
        'total_amount',
        'reference_number',
        'advance_type_id',
        'previous_advance_id',
        'top_up',
        'miscallenous_charge_id',
        'charge_amount',
        'current_balance',
        'one_off',
        'voucher_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'requisition_date' => 'datetime',
        'principal_start_date' => 'datetime',
        'disbursement_date' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function invoiceableitems()
    {
        return $this->belongsTo(InvoiceableItem::class, 'miscallenous_charge_id', 'id');
    }

    public function schedule()
    {
        return $this->hasMany(AdvanceSchedule::class, 'parent_id')->orderBy('payment_date', 'asc');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'current_status_id');
    }

    public function pendingSchedules()
    {
        return $this->hasMany(AdvanceSchedule::class, 'parent_id')
            ->where('current_status_id', 1)
            ->orderBy('payment_date', 'asc');
    }

    public function clearedSchedules()
    {
        return $this->hasMany(AdvanceSchedule::class, 'parent_id')
            ->where('current_status_id', 2)
            ->orderBy('payment_date', 'asc');
    }

    public function getTotalPaidAttribute()
    {
        return $this->schedule()->where('current_status_id', 2)->sum('total_amount');
    }

    public function getPercentagePaidAttribute()
    {
        if ($this->total_amount == 0) {
            return 0;
        }
        $totalPaid = $this->schedule()->where('current_status_id', 2)->sum('total_amount');
        return round(($totalPaid / $this->total_amount) * 100, 2);
    }
}
