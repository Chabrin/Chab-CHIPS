<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLogs extends Model
{
    use HasFactory;
    protected $table = 'cb_audit_logs';
    protected $fillable = [
        'table_name',
        'record_id',
        'operation',
        'old_data',
        'new_data',
        'user_id',
    ];

    protected $casts = [
        'old_data' => 'json',
        'new_data' => 'json',
    ];
}
