<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnsBatchConfigPropertyMapping extends Model
{
    protected $table = 'sys_eports_returns_batches_configurations_298_repeat_repeat_prs';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'prs',
    ];

    /**
     * Get the batch configuration this mapping belongs to
     */
    public function batchConfig()
    {
        return $this->belongsTo(ReturnsBatchConfiguration::class, 'parent_id');
    }

    /**
     * Get the property for this mapping
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'prs');
    }
}
