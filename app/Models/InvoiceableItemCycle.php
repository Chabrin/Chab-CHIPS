<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceableItemCycle extends Model
{
    protected $table = 'sys_set_invoiceable_items_cycles';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'cycle_name',
    ];

   
}
