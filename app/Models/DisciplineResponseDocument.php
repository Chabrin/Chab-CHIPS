<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineResponseDocument extends Model
{
    protected $fillable = [
        'response_id',
        'document_name',
        'document_path',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public $timestamps = false;

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    public function employeeResponse(): BelongsTo
    {
        return $this->belongsTo(DisciplineEmployeeResponse::class, 'response_id');
    }
}