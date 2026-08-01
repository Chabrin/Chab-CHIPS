<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitInterview extends Model
{
    protected $table = 'hr_exit_interviews';

    protected $fillable = [
        'termination_id',
        'reason_confirmation',
        'feedback',
        'assets_returned',
        'assets_notes',
        'would_recommend_rating',
        'conducted_by',
        'conducted_at',
    ];

    protected $casts = [
        'assets_returned' => 'boolean',
        'would_recommend_rating' => 'integer',
        'conducted_at' => 'datetime',
    ];

    public $timestamps = false;

    public function termination(): BelongsTo
    {
        return $this->belongsTo(EmployeeTermination::class, 'termination_id');
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
