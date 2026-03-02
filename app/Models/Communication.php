<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    protected $fillable = [
        'subject',
        'message',
        'recipients',
        'type',
        'recipient_type',
        'status',
        'attachment',
        'created_by',
        'send_status',
        'sent_at',
        'send_error',
    ];
}