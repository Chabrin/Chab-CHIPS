<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseContractTemplate extends Model
{
    protected $table = 'cb_lease_contract_templates';

    protected $fillable = [
        'template_name',
        'description',
        'html_body',
        'is_default',
        'current_status_id',
        'created_by_id',
        'date_time',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'date_time' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeActive($query)
    {
        return $query->where('current_status_id', 1);
    }
}
