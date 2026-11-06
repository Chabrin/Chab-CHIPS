<?php

namespace App\Models;

use App\Models\ReturnsMergeBatchItem;
use Illuminate\Database\Eloquent\Model;

class ReturnsMergeBatch extends Model
{
    protected $table = 'cb_returns_merge_batches';

    protected $fillable = [
        'batch_reference',
        'anchor_client_id',
        'landlord_name',
        'landlord_name_normalized',
        'landlord_id',
        'landlord_ref',
        'notes',
        'bank_detail_id',
        'created_by',
        'updated_by',
        'date_time',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ReturnsMergeBatchItem::class, 'batch_id');
    }

    public function bankDetail()
    {
        return $this->belongsTo(ClientBankDetail::class, 'bank_detail_id');
    }

    public function anchorClient()
    {
        return $this->belongsTo(Client::class, 'anchor_client_id');
    }
}
