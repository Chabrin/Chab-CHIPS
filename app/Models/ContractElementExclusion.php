<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * A company-wide item (one the payroll run applies to every staff member) left out for one person, with the reason. It belongs
 * to a contract, so it only ever affects the person on that contract.
 */
class ContractElementExclusion extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'contract_element_exclusions';

    protected $fillable = ['contract_id', 'element_id', 'reason', 'created_by'];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function element()
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }
}
