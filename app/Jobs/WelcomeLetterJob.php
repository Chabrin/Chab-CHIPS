<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\MembersHost;
use App\Models\Account;
use App\Models\Company;
use App\Traits\EmailMessageTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;

class WelcomeLetterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EmailMessageTrait;

    protected $member;
    protected $isHost;

    /**
     * Create a new job instance.
     */
    public function __construct($member, bool $isHost = false)
    {
        $this->member = $member;
        $this->isHost = $isHost;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $member = $this->member;

            if (!$member || !$member->e_mail) {
                Log::warning('Welcome letter email skipped - no member email', [
                    'member_id' => $member->id ?? 'unknown',
                    'member_no' => $member->member_no ?? 'unknown'
                ]);
                return;
            }

            // Get company details
            $company = Company::find($member->company_id);

            // Get account details (if available)
            $account = Account::where('member_no', $member->member_no)->first();

            // Prepare email content using the EmailMessageTrait FIRST
            $replacements = $this->mergeReplacements([
                'member_name' => trim($member->first_name . ' ' . $member->last_name),
                'member_first_name' => $member->first_name,
                'member_no' => $member->member_no,
                'account_no' => $account ? $account->account_no : 'N/A',
                'company_name' => $company ? $company->company_name : 'Pension Master',
                'confirmed_by' => $member->confirmed_by,
                'current_year' => date('Y'),
                'current_date' => date('Y-m-d')
            ]);

            // Check template exists (will throw exception if not found)
            $emailMessage = $this->prepareMessage("welcome_letter_host", "email", $replacements);
            $emailSubject = $this->getEmailSubject("welcome_letter_host");

            // Send welcome email (no PDF attachment - just email message)
            Mail::send([], [], function ($message) use ($member, $emailSubject, $emailMessage) {
                $message->to($member->e_mail, trim($member->first_name . ' ' . $member->last_name));
                $message->subject($emailSubject);
                $message->html($emailMessage);
            });

            Log::info('Welcome letter email sent successfully', [
                'member_id' => $member->id,
                'member_no' => $member->member_no,
                'member_email' => $member->e_mail,
                'is_host' => $this->isHost
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome letter email: ' . $e->getMessage(), [
                'member_id' => $this->member->id ?? 'unknown',
                'member_no' => $this->member->member_no ?? 'unknown',
                'is_host' => $this->isHost,
                'trace' => $e->getTraceAsString()
            ]);

            ErrorDispatcher::dispatch($e);
            throw $e;
        }
    }

    /**
     * Handle failed job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Welcome letter email job failed', [
            'member_id' => $this->member->id ?? 'unknown',
            'member_no' => $this->member->member_no ?? 'unknown',
            'is_host' => $this->isHost,
            'error' => $exception->getMessage()
        ]);
    }
}
