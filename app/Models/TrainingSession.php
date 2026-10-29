<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function attendees()
    {
        return $this->hasMany(TrainingSessionAttendee::class);
    }
}