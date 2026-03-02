<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class ContractElement extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_employee_contracts_279_repeat';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'element_id',
        'entry_type_id',
        'amount',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'parent_id', 'id');
    }

    public function payrollElement()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id', 'id');
    }


}
