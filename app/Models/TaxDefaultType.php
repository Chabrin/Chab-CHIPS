<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class TaxDefaultType extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_tax_default_types';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'type_name',
        'current_status_id',
        'date_time',
        'created_by_id',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'current_status_id' => 'integer',
        'created_by_id' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
