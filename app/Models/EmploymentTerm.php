<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmploymentTerm extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'employment_terms';
    protected $fillable = [
        'name',
        'description',
        'created_at',
        'updated_at',
    ];
}
