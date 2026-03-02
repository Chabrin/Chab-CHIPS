<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class EmployeeTerminationDocumentTracker extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'hr_employment_termination_repeat_documents';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'documents',
        'params',
    ];

    public function termination()
    {
        return $this->belongsTo(EmployeeTermination::class, 'parent_id', 'id');
    }

}
