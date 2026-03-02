<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class StaffDisciplineDocumentTracker extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_staff_discipline_tracker_repeat_documents';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'documents',
        'params',
    ];

    public function staffDiscipline()
    {
        return $this->belongsTo(StaffDiscipline::class, 'parent_id');
    }

}
