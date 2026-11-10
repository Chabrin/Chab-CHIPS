<?php

namespace App\Services\Comms;

use App\Jobs\SendCommBatchChunk;
use App\Models\CommBatch;
use App\Models\EmailLog;
use App\Models\SmsLog;
use Illuminate\Support\Facades\DB;

/**
 * Creates a communication batch and pushes it out: resolves the audience, writes the batch row,
 * logs the recipients that have no contact for the channel as skipped, and dispatches the rest
 * to the comms queue in chunks so one blast never ties up a request or starves other queues.
 */
class CommBatchService
{
    const CHUNK_SIZE = 500;
    const QUEUE = 'comms';

    protected AudienceResolver $audience;

    /**
     * Inject the audience resolver.
     *
     * @param AudienceResolver $audience
     */
    public function __construct(AudienceResolver $audience) {
        $this->audience = $audience;
    }

    /**
     * Preview a send without sending: recipient counts, estimated SMS units, and the first
     * recipient's resolved message so the sender can see what will go out.
     *
     * @param string $channel email | sms
     * @param string $audienceType one of AudienceResolver::TYPES
     * @param array $params audience params
     * @param string $subject
     * @param string $body with {placeholders}
     * @return array total, sendable, skipped, sms_units, preview
     */
    public function estimate(string $channel, string $audienceType, array $params, string $subject, string $body): array {
        $recipients = $this->audience->resolve($audienceType, $params);
        [$sendable, $skipped] = $this->split($recipients, $channel);

        $units = 0;
        if ($channel === 'sms') {
            foreach ($sendable as $recipient) {
                $units += SmsService::units(self::substitute($body, $recipient));
            }
        }

        $first = $sendable->first();

        return [
            'total' => $recipients->count(),
            'sendable' => $sendable->count(),
            'skipped' => $skipped->count(),
            'sms_units' => $units,
            'audience_label' => $this->audience->label($audienceType, $params),
            'sample' => $first ? ['name' => $first['name'], 'property' => $first['property'], 'unit' => $first['unit'], 'reference' => $first['reference']] : null,
            'preview' => $first ? [
                'name' => $first['name'],
                'contact' => $channel === 'sms' ? $first['mobile'] : $first['email'],
                'subject' => self::substitute($subject, $first),
                'body' => self::substitute($body, $first),
            ] : null,
        ];
    }

    /**
     * Create the batch and dispatch it. Recipients without a contact for the channel are logged
     * as skipped immediately; the rest go out through queued chunk jobs.
     *
     * @param string $channel email | sms
     * @param string $audienceType one of AudienceResolver::TYPES
     * @param array $params audience params
     * @param string $subject empty for sms
     * @param string $body with {placeholders}
     * @param int $userId the sender
     * @param string|null $attachmentPath stored attachment path (email only)
     * @param string|null $attachmentName original attachment file name
     * @return array [$batch, $error] — batch null when there is nobody to send to
     */
    public function dispatch(string $channel, string $audienceType, array $params, string $subject, string $body, int $userId, ?string $attachmentPath = null, ?string $attachmentName = null): array {
        $recipients = $this->audience->resolve($audienceType, $params);

        if ($recipients->isEmpty()) {
            return [null, 'No recipients matched that audience.'];
        }

        [$sendable, $skipped] = $this->split($recipients, $channel);

        if ($sendable->isEmpty()) {
            return [null, 'Nobody in that audience has ' . ($channel === 'sms' ? 'a mobile number' : 'an email address') . ' on record.'];
        }

        $batch = DB::transaction(function () use ($channel, $audienceType, $params, $subject, $body, $userId, $attachmentPath, $attachmentName, $recipients, $sendable, $skipped) {
            $batch = CommBatch::create([
                'channel' => $channel,
                'subject' => $channel === 'email' ? $subject : null,
                'body' => $body,
                'audience_type' => $audienceType,
                'audience_params' => $params,
                'audience_label' => $this->audience->label($audienceType, $params),
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'recipient_count' => $recipients->count(),
                'skipped_count' => $skipped->count(),
                'status' => 'queued',
                'created_by' => $userId,
            ]);

            $this->logSkipped($batch, $skipped, $subject, $body, $userId);

            return $batch;
        });

        foreach ($sendable->chunk(self::CHUNK_SIZE) as $chunk) {
            SendCommBatchChunk::dispatch($batch->id, $chunk->values()->all())->onQueue(self::QUEUE);
        }

        return [$batch, null];
    }

    /**
     * Re-queue the failed recipients of a batch. Their failed log rows stay as history; the
     * retry writes new rows, and the batch counters are reduced so they count again.
     *
     * @param CommBatch $batch
     * @return int how many were re-queued
     */
    public function resendFailed(CommBatch $batch): int {
        $logs = $batch->channel === 'sms'
            ? SmsLog::where('batch_id', $batch->id)->where('status', 'failed')->get()
            : EmailLog::where('batch_id', $batch->id)->where('status', 'failed')->get();

        if ($logs->isEmpty()) {
            return 0;
        }

        $recipients = $logs->map(fn ($log) => [
            'recipient_type' => $log->recipient_type,
            'recipient_id' => $log->recipient_id,
            'name' => $log->recipient_name,
            'email' => $batch->channel === 'email' ? $log->recipient_email : null,
            'mobile' => $batch->channel === 'sms' ? $log->recipient_phone : null,
            'reference' => null,
            'property' => null,
            'unit' => null,
        ])->values();

        $batch->update(['status' => 'queued', 'failed_count' => DB::raw('failed_count - ' . $logs->count())]);

        foreach ($recipients->chunk(self::CHUNK_SIZE) as $chunk) {
            SendCommBatchChunk::dispatch($batch->id, $chunk->values()->all())->onQueue(self::QUEUE);
        }

        return $logs->count();
    }

    /**
     * Replace the {placeholders} in a subject or body with one recipient's values.
     *
     * @param string $text
     * @param array $recipient an AudienceResolver recipient
     */
    public static function substitute(string $text, array $recipient): string {
        $name = (string) ($recipient['name'] ?? '');

        return str_replace(
            ['{name}', '{first_name}', '{property}', '{unit}', '{reference}', '{date}'],
            [$name, strtok($name, ' ') ?: $name, (string) ($recipient['property'] ?? ''), (string) ($recipient['unit'] ?? ''), (string) ($recipient['reference'] ?? ''), date('d M Y')],
            $text
        );
    }

    /**
     * Split recipients into those with a contact for the channel and those without.
     *
     * @param \Illuminate\Support\Collection $recipients
     * @param string $channel email | sms
     * @return array [$sendable, $skipped]
     */
    protected function split($recipients, string $channel): array {
        $key = $channel === 'sms' ? 'mobile' : 'email';
        [$sendable, $skipped] = $recipients->partition(fn ($r) => !empty($r[$key]));

        return [$sendable->values(), $skipped->values()];
    }

    /**
     * Write skipped log rows in bulk for the recipients that have no contact on record.
     *
     * @param CommBatch $batch
     * @param \Illuminate\Support\Collection $skipped
     * @param string $subject
     * @param string $body
     * @param int $userId
     */
    protected function logSkipped(CommBatch $batch, $skipped, string $subject, string $body, int $userId): void {
        if ($skipped->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($skipped->chunk(500) as $chunk) {
            $rows = $chunk->map(function ($r) use ($batch, $subject, $body, $userId, $now) {
                $base = [
                    'batch_id' => $batch->id,
                    'type' => 'general',
                    'recipient_type' => $r['recipient_type'],
                    'recipient_id' => $r['recipient_id'],
                    'recipient_name' => $r['name'],
                    'status' => 'skipped',
                    'sent_by' => $userId,
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($batch->channel === 'sms') {
                    return $base + ['recipient_phone' => '', 'message' => $body, 'units' => 0, 'error' => 'No mobile number on record'];
                }

                return $base + ['recipient_email' => '', 'subject' => $subject, 'body' => $body, 'error' => 'No email address on record'];
            })->all();

            $batch->channel === 'sms' ? DB::table('cb_sms_logs')->insert($rows) : DB::table('cb_email_logs')->insert($rows);
        }
    }
}
