<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantMovementItem extends Model
{
    protected $table = 'sys_pm_movement_requests_190_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'parent_id',
        'item_id',
        'amount',
        'cycle_id',
        'flat_rate_vat',
    ];

    public function movement()
    {
        return $this->belongsTo(TenantMovement::class, 'parent_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(InvoiceableItem::class, 'item_id');
    }

    public function cycle()
    {
        return $this->belongsTo(InvoiceableItemCycle::class, 'cycle_id');
    }
}
