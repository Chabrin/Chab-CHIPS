<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ControlCategory extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'control_categories';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'category_name',
        'display_name',
        'precedence'
    ];

    public function controls()
    {
        return $this->hasMany(SystemControl::class, 'category_id');
    }
}
