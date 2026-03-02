<?php

namespace App\Models;

use App\Models\Submodule;
use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    use HasFactory;
    protected $table = 'cb_modules';
    protected $fillable = [
        'module_name',
        'module_url',
        'icon',
        'module_order',
        'added_by',
        'added_on',
        'deleted',
        'deleted_by',
        'deleted_at',
        'deleted_reason',
    ];
    public function submodules()
    {
        return $this->hasMany(Submodule::class, 'module_id');
    }
}
