<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSessionAttendee extends Model
{
    protected $guarded = ['id'];
    
    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function staff()
    {
        // Explicitly linking to sys_staff table and staff_id foreign key
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }
}