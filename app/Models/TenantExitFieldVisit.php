<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitFieldVisit extends Model
{
    protected $table = 'cb_tenant_exit_field_visits';

    public $timestamps = false;

    protected $fillable = [
        'exit_id',
        'visit_date',
        'officer_name',
        'unit_status_found',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
