<?php

namespace App\Jobs;

use App\Models\Company;
use App\Traits\EmailMessageTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\ErrorDispatcher;

class UserActivationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EmailMessageTrait;

    protected array $details;

    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function handle(): void
    {
        try {
            $details = $this->details;
            $username = $details['username'] ?? null;
            $user_email = $details['email'] ?? null;
            $activation_token = $details['token'] ?? null;

            $company = Company::Where('is_host', 1)->first();
            $application_url = DB::table('cb_system_configs')->where('setting', 'app_url')->value('value');

            $activation_url = $application_url . '/activate-account/' . ($activation_token ?? '');

            $replacements = $this->mergeReplacements([
                'username' => $username,
                'company_name' => $company->company_name ?? 'Pension Master',
                'activation_url' => $activation_url,
                'user_email' => $user_email
            ]);

            $email_message = $this->prepareMessage('account_activation', 'email', $replacements);
            $email_subject = $this->getEmailSubject('account_activation');

            $data = [
                'subject' => $email_subject,
                'content' => $email_message,
                'to' => $user_email
            ];

            Mail::send([], [], function ($message) use ($data) {
                $message->to($data['to']);
                $message->subject($data['subject']);
                $message->html($data['content']);
            });

            Log::info('User activation email queued/sent', ['email' => $user_email, 'username' => $username]);
        } catch (\Throwable $th) {
            Log::error('UserActivationJob failed: ' . $th->getMessage());
            ErrorDispatcher::dispatch($th);
            throw $th;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('User activation job failed', ['error' => $exception->getMessage(), 'details' => $this->details]);
    }
}
