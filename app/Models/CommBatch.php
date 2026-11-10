<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommBatch extends Model
{
    protected $table = 'cb_comm_batches';

    protected $guarded = [];

    protected $casts = [
        'audience_params' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * The user who created (sent) the batch.
     */
    public function sender() {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The per-recipient email log rows of this batch.
     */
    public function emailLogs() {
        return $this->hasMany(EmailLog::class, 'batch_id');
    }

    /**
     * The per-recipient SMS log rows of this batch.
     */
    public function smsLogs() {
        return $this->hasMany(SmsLog::class, 'batch_id');
    }
}
