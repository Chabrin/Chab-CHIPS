<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employee_contracts';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'staff_id',
        'date_start',
        'date_end',
        'current_status_id',
        'position_id',
        'created_by',
        'basic_salary',
        'staff_number',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The uploaded signed (PDF) copy of this contract, if any.
     */
    public function signedDocument()
    {
        return $this->hasOne(EmployeeDocument::class, 'contract_id')->latestOfMany();
    }

    public function elements()
    {
        return $this->hasMany(ContractElement::class, 'parent_id', 'id');
    }


}
