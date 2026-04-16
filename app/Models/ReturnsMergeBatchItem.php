<?php

namespace App\Models;

use App\Models\ReturnsMergeBatch;
use Illuminate\Database\Eloquent\Model;

class ReturnsMergeBatchItem extends Model
{
    protected $table = 'cb_returns_merge_batch_items';

    protected $fillable = [
        'batch_id',
        'property_id',
        'property_ref',
        'property_name',
        'landlord_id',
        'landlord_ref',
        'landlord_name',
        'date_time',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ReturnsMergeBatch::class, 'batch_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
