<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

/** Someone HR may pick for the review committee. The same people sit for the joint review meeting and for appeals. */
class PerformanceCommitteeMember extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'performance_committee_members';

    protected $fillable = ['staff_id', 'added_by'];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
