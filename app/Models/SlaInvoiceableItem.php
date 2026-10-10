<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class SlaInvoiceableItem extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_sla_invoiceable_items';
    protected $primaryKey = 'id';

    protected $fillable = [
        'sla_id',
        'item_id',
        'amount',
        'percentage_of_rent',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage_of_rent' => 'decimal:2',
    ];

    public function serviceAgreement()
    {
        return $this->belongsTo(ServiceAgreement::class, 'sla_id');
    }

    public function item()
    {
        return $this->belongsTo(InvoiceableItem::class, 'item_id');
    }
}
