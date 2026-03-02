<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class SystemControl extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_system_configs';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'category_id',
        'display_name',
        'precedence',
        'value',
        'setting',
        'toggle_boolean'
    ];

    public function category()
    {
        return $this->belongsTo(ControlCategory::class, 'category_id');
    }

    /**
     * Get the decimal places setting for general formatting
     * Returns the value from general_decimal_places setting, defaults to 3
     */
    public static function getDecimalPlaces()
    {
        $setting = self::where('setting', 'general_decimal_places')->first();
        return $setting ? (int) $setting->value : 3;
    }
}
