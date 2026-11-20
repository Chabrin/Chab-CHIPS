<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use App\Services\DeductionLimits;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of scheduled deduction HR can choose (HELB, Sacco / Akiba loan, Salary advance...). HR can add more.
 * A type marked as a company loan is held to the half-of-what-is-left limit.
 */
class DeductionType extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_deduction_types';

    protected $fillable = ['name', 'is_company_loan', 'is_active', 'created_by'];

    protected $casts = [
        'is_company_loan' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** The kind DeductionLimits knows this type by. */
    public function limitKind(): string
    {
        return $this->is_company_loan ? DeductionLimits::COMPANY_LOAN_KINDS[0] : 'other';
    }
}
