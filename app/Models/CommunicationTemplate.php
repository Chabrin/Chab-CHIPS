<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'sys_hr_communication_templates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'template_name',
        'template_type',
        'communication_method',
        'subject',
        'body',
        'general_config',
        'attachment_path',
        'current_status_id',
        'created_by_id',
        'date_time',
    ];

    protected $casts = [
        'general_config' => 'array',
        'date_time' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}