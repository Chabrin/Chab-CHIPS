<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Submodule extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    use HasFactory;
    protected $table = 'cb_sub_modules';
    protected $fillable = [
        'sub_module_name',
        'sub_module_url',
        'icon',
        'order',
        'added_by',
        'added_on',
        'module_id',
    ];
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
