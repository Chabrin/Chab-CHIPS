<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveTypeTier extends Model
{
    protected $table = 'sys_hr_leave_type_tiers';
    public $timestamps = false;
    protected $fillable = ['leave_type_id', 'tier_order', 'label', 'days', 'pay_rate'];
}