<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PropertyMergeItem extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'cb_property_merge_items';
    protected $primaryKey = 'id';

    protected $fillable = [
        'property_merge_id',
        'source_property_id',
        'unit_id',
        'original_data',
        'date_time',
    ];

    protected $casts = [
        'original_data' => 'array',
        'date_time' => 'datetime',
    ];

    // Relationships

    public function merge()
    {
        return $this->belongsTo(PropertyMerge::class, 'property_merge_id');
    }

    public function sourceProperty()
    {
        return $this->belongsTo(Property::class, 'source_property_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
