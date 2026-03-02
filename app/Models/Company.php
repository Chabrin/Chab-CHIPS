<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'companies';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'company_name',
        'address',
        'town',
        'fax',
        'tel',
        'tel2',
        'mobile',
        'email',
        'website',
        'physical_add',
        'reg_cert',
        'logo',
        'vat_no',
        'pin_no',
        'contact_name',
        'fin_start',
        'other_names',
        'company_code',
        'fund_manager_id'
    ];

    /**
     * Relation: company belongs to a fund manager
     */
    public function fund_manager()
    {
        return $this->belongsTo(FundManager::class, 'fund_manager_id');
    }
}
