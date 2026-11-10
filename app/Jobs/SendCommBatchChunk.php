<?php

namespace App\Jobs;

use App\Models\CommBatch;
use App\Services\Comms\CommBatchService;
use App\Services\Comms\EmailService;
use App\Services\Comms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sends one chunk (up to 500 recipients) of a communication batch. Each recipient gets the batch
 * body with their own placeholders resolved; every outcome is logged by the services. When all
 * recipients of the batch are accounted for, the batch is marked done.
 */
class SendCommBatchChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $batchId;
    public array $recipients;

    public $timeout = 1800;
    public $tries = 1;

    /**
     * Hold the batch id and this chunk's recipients (plain arrays from AudienceResolver).
     *
     * @param int $batchId
     * @param array $recipients
     */
    public function __construct(int $batchId, array $recipients) {
        $this->batchId = $batchId;
        $this->recipients = $recipients;
    }

    /**
     * Send the chunk and update the batch counters.
     *
     * @param EmailService $email
     * @param SmsService $sms
     */
    public function handle(EmailService $email, SmsService $sms): void {
        $batch = CommBatch::find($this->batchId);
        if (!$batch) {
            return;
        }

        if ($batch->status === 'queued') {
            $batch->update(['status' => 'sending']);
        }

        $attachPath = null;
        if ($batch->attachment_path && Storage::disk('local')->exists($batch->attachment_path)) {
            $attachPath = Storage::disk('local')->path($batch->attachment_path);
        }

        $sent = 0;
        $failed = 0;

        foreach ($this->recipients as $recipient) {
            $body = CommBatchService::substitute($batch->body, $recipient);

            if ($batch->channel === 'sms') {
                [$ok] = $sms->send((string) $recipient['mobile'], (string) $recipient['name'], $body, 'general', $batch->id, $recipient['recipient_type'] ?? null, $recipient['recipient_id'] ?? null, null, null, (int) $batch->created_by);
            } else {
                $subject = CommBatchService::substitute((string) $batch->subject, $recipient);
                [$ok] = $email->send((string) $recipient['email'], (string) $recipient['name'], $subject, nl2br(e($body)), 'general', $batch->id, $recipient['recipient_type'] ?? null, $recipient['recipient_id'] ?? null, $attachPath, $batch->attachment_name, null, null, 'application/pdf', null, null, (int) $batch->created_by);
            }

            $ok ? $sent++ : $failed++;
        }

        DB::table('cb_comm_batches')->where('id', $batch->id)->update([
            'sent_count' => DB::raw('sent_count + ' . $sent),
            'failed_count' => DB::raw('failed_count + ' . $failed),
            'updated_at' => now(),
        ]);

        $fresh = CommBatch::find($batch->id);
        if ($fresh && $fresh->sent_count + $fresh->failed_count + $fresh->skipped_count >= $fresh->recipient_count) {
            $fresh->update(['status' => $fresh->failed_count > 0 && $fresh->sent_count === 0 ? 'failed' : 'done', 'sent_at' => now()]);
        }
    }
}
