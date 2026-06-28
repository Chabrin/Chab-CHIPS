<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExitDateChange extends Model
{
    protected $table = 'cb_tenant_exit_date_changes';

    public $timestamps = false;

    protected $fillable = [
        'exit_id',
        'previous_date',
        'new_date',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'previous_date' => 'date',
        'new_date'      => 'date',
        'created_at'    => 'datetime',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TenantExit::class, 'exit_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
