<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one mailable every system email goes out as: a branded wrapper around a body the caller
 * supplies. Attachments come either from a path on disk or as raw bytes (base64-encoded so the
 * mailable survives queue serialization) for in-memory PDFs.
 */
class GenericCommMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $mailSubject;
    public string $mailBody;
    public ?string $attachmentPath;
    public ?string $attachmentName;
    public ?string $rawAttachmentData;
    public ?string $rawAttachmentName;
    public ?string $rawAttachmentMime;

    /**
     * Build the mailable.
     *
     * @param string $subject
     * @param string $body HTML body (already placeholder-resolved)
     * @param string $attachmentPath absolute path of a file on disk, or null
     * @param string $attachmentName download name of the disk attachment
     * @param string $rawAttachmentData base64-encoded bytes of an in-memory attachment, or null
     * @param string $rawAttachmentName download name of the in-memory attachment
     * @param string $rawAttachmentMime mime of the in-memory attachment
     */
    public function __construct(string $subject, string $body, ?string $attachmentPath = null, ?string $attachmentName = null, ?string $rawAttachmentData = null, ?string $rawAttachmentName = null, ?string $rawAttachmentMime = 'application/pdf') {
        $this->mailSubject = $subject;
        $this->mailBody = $body;
        $this->attachmentPath = $attachmentPath;
        $this->attachmentName = $attachmentName;
        $this->rawAttachmentData = $rawAttachmentData;
        $this->rawAttachmentName = $rawAttachmentName;
        $this->rawAttachmentMime = $rawAttachmentMime;
    }

    /**
     * The envelope: subject only; from/reply-to come from the mail config.
     */
    public function envelope(): Envelope {
        return new Envelope(subject: $this->mailSubject);
    }

    /**
     * The branded HTML wrapper around the body.
     */
    public function content(): Content {
        return new Content(view: 'emails.generic', with: ['body' => $this->mailBody, 'brand' => config('app.company_name')]);
    }

    /**
     * Disk and in-memory attachments, whichever were given.
     */
    public function attachments(): array {
        $attachments = [];

        if ($this->attachmentPath && file_exists($this->attachmentPath)) {
            $attachments[] = Attachment::fromPath($this->attachmentPath)->as($this->attachmentName ?? basename($this->attachmentPath));
        }

        if ($this->rawAttachmentData) {
            $bytes = base64_decode($this->rawAttachmentData);
            $attachments[] = Attachment::fromData(fn () => $bytes, $this->rawAttachmentName ?? 'attachment.pdf')->withMime($this->rawAttachmentMime ?? 'application/pdf');
        }

        return $attachments;
    }
}
