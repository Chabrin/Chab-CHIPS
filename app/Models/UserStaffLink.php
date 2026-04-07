<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStaffLink extends Model
{
    protected $table = 'sys_user_staff_links';
    public $timestamps = false;

    protected $fillable = ['user_id', 'staff_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
