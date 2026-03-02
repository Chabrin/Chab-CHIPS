<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class PayrollElementPosition extends Model
{
    protected $table = 'sys_hr_payroll_elements_repeat_staff_positions_id';
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'staff_positions_id',
        'params',
    ];

    // Relationship to payroll element
    public function payrollElement()
    {
        return $this->belongsTo(PayrollElement::class, 'parent_id');
    }

    // Relationship to position
    public function position()
    {
        return $this->belongsTo(Position::class, 'staff_positions_id');
    }
}
