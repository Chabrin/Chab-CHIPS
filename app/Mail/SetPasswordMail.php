<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The email that lets a new system user choose their own password. It carries a one-time link and never a password.
 */
class SetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $url,
        public int $hours,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Set your password for ' . config('app.name', 'CHIPS'))
            ->view('emails.set-password');
    }
}
