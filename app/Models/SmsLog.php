<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'cb_sms_logs';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * The batch this log row belongs to, when it was part of a bulk send.
     */
    public function batch() {
        return $this->belongsTo(CommBatch::class, 'batch_id');
    }

    /**
     * The user who triggered the send.
     */
    public function sender() {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
