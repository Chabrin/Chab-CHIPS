<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Salutation extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_salutations';

    protected $fillable = [
        'name',
        'description',
        'added_by',
        'deleted_at',
        'deleted_by',
    ];

    public function tenantRecords()
    {
        return $this->hasMany(TenantRecord::class);
    }
}
