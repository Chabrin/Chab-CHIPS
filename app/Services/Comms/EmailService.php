<?php

namespace App\Services\Comms;

use App\Mail\GenericCommMail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;

/**
 * The one way the system sends an email. Sends through the default mailer as the branded
 * GenericCommMail and writes a cb_email_logs row for every attempt — sent or failed — so the
 * caller cannot forget to log. Never throws at the caller: returns [$ok, $error].
 */
class EmailService
{
    /**
     * Send one email and log the outcome.
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $body HTML body, already placeholder-resolved
     * @param string $type what kind of email this is: general, payslip, invoice, tenant_exit ...
     * @param int|null $batchId cb_comm_batches id when part of a bulk send
     * @param string|null $recipientType tenant | landlord | staff | user
     * @param int|null $recipientId
     * @param string|null $attachPath absolute path of a disk attachment
     * @param string|null $attachName download name of the disk attachment
     * @param string|null $rawAttachmentData raw bytes of an in-memory attachment (e.g. a generated PDF)
     * @param string|null $rawAttachmentName download name of the in-memory attachment
     * @param string $rawAttachmentMime mime of the in-memory attachment
     * @param string|null $entityType the record that caused the email: invoice, return, exit ...
     * @param int|null $entityId
     * @param int|null $sentBy user id; defaults to the logged-in user
     * @return array [$ok, $error]
     */
    public function send(string $toEmail, string $toName, string $subject, string $body, string $type = 'general', ?int $batchId = null, ?string $recipientType = null, ?int $recipientId = null, ?string $attachPath = null, ?string $attachName = null, ?string $rawAttachmentData = null, ?string $rawAttachmentName = null, string $rawAttachmentMime = 'application/pdf', ?string $entityType = null, ?int $entityId = null, ?int $sentBy = null): array {
        $status = 'sent';
        $error = null;

        try {
            $mailable = new GenericCommMail($subject, $body, $attachPath, $attachName, $rawAttachmentData ? base64_encode($rawAttachmentData) : null, $rawAttachmentName, $rawAttachmentMime);
            Mail::to($toEmail, $toName)->send($mailable);
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 2000);
        }

        EmailLog::create([
            'batch_id' => $batchId,
            'type' => $type,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'recipient_name' => $toName,
            'recipient_email' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'has_attachment' => $attachPath !== null || $rawAttachmentData !== null,
            'status' => $status,
            'error' => $error,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sent_by' => $sentBy ?? auth()->id(),
            'sent_at' => now(),
        ]);

        return [$status === 'sent', $error];
    }
}
