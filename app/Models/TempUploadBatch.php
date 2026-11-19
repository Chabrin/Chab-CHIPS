<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TempUploadBatch extends Model
{
    use SoftDeletes;

    protected $table = 'temp_upload_batches';

    protected $fillable = [
        'property_id',
        'client_id',
        'file_name',
        'mode',
        'status',
        'total_units',
        'total_tenants',
        'created_by',
        'submitted_at',
        'submitted_by',
        'cancelled_at',
        'cancelled_by',
    ];

    public const STATUS_STAGED = 'staged';
    public const MODE_NEW = 'new_units';
    public const MODE_EXISTING = 'existing_units';
    public const STATUS_SUBMITTING = 'submitting';
    public const STATUS_SUBMITTED = 'submitted';

    protected $casts = [
        'submitted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * The property this batch was uploaded for.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Staged unit rows.
     */
    public function units()
    {
        return $this->hasMany(TempBatchUnit::class, 'batch_id')->orderBy('row_no');
    }

    /**
     * Staged tenant rows.
     */
    public function tenants()
    {
        return $this->hasMany(TempBatchTenant::class, 'batch_id')->orderBy('row_no');
    }
}
