<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplates extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    // Laravel will automatically use 'email_templates' with the 'um_' prefix = 'um_email_templates'
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'added_by',
        'template_variable',
        'comtype',
        'confirmed_by',
        'confirmed',
        'confirmed_date'
    ];

    protected $casts = [
        'template_variable' => 'array',
        'confirmed_date' => 'datetime'
    ];
}
