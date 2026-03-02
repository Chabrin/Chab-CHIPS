<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ConfirmationLog extends Model
{
    protected $table = 'confirmation_logs';

    protected $fillable = [
        'section',
        'model',
        'action',
        'ids',
        'confirmed_by',
        'confirmed_at',
        'approval_stage',
        'metadata',
        
    ];

    protected $casts = [
        'ids' => 'array',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}