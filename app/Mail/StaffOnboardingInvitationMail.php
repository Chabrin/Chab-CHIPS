<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffOnboardingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $onboardingUrl) {}

    public function build(): self
    {
        return $this->subject('Complete your staff onboarding')
            ->view('emails.staff-onboarding-invitation');
    }
}
