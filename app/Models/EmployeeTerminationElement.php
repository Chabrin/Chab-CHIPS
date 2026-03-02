<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmployeeTerminationElement extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'hr_employment_termination_305_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'element_id',
        'amount',
    ];

    public function termination()
    {
        return $this->belongsTo(EmployeeTermination::class, 'parent_id', 'id');
    }

    public function element()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }


}
