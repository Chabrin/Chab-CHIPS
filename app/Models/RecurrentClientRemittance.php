<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class RecurrentClientRemittance extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_fin_reccurent_client_remittances';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'client_id',
        'property_id',
        'sla_id',
        'remittance_type_id',
        'created_by_id',
        'current_status_id',
        'interest_rate',
        'start_date',
        'every_date',
        'amount',
        'narration',
        'begin_interest_date',
        'item_id',
        'supplier_id',
        'bank_id',
        'bank_branch_id',
        'account_number',
        'bill_account_number',
        'caretaker_id',
        'last_created_period',
    ];

    protected $casts = [
        'date_time'           => 'datetime',
        'begin_interest_date' => 'date',
        'start_date'          => 'date',
        'last_created_period' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function sla()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    public function itemType()
    {
        return $this->belongsTo(InvoiceableItem::class, 'item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Provider::class, 'supplier_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function bankBranch()
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    public function caretaker()
    {
        return $this->belongsTo(Staff::class, 'caretaker_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getRemittanceTypeLabelAttribute(): string
    {
        return match ((int) $this->remittance_type_id) {
            1 => 'Clients Bills',
            2 => 'Property Team Remittance',
            default => 'Unknown',
        };
    }
}