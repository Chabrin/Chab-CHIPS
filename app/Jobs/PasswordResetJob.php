<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\ErrorDispatcher;

class PasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $resetDetailsArray;

    /**
     * Create a new job instance.
     */
    public function __construct(array $resetDetailsArray)
    {
        $this->resetDetailsArray = $resetDetailsArray;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $resetDetailsArray = $this->resetDetailsArray;
            $username = $resetDetailsArray['username'];
            $user_email = $resetDetailsArray['email'];
            $token = $resetDetailsArray['token'];
            $company = Company::Where('is_host', 1)->first();

            $application_url = DB::table('cb_system_configs')->where('setting', 'app_url')->value('value');
            $password_reset_link_validity = DB::table('cb_system_configs')->where('setting', 'password_reset_link_validity')->value('value') ?? '4';

            $url = $application_url . '/reset-password/' . $token;
            $company_name = $company->company_name ?? 'Pension Master';

            // Build HTML email content
            $email_message = $this->buildPasswordResetEmail($username, $company_name, $url, $password_reset_link_validity);

            $data = [
                'subject' => 'Password Reset Request',
                'content' => $email_message,
                'to' => $user_email
            ];

            Mail::send([], [], function ($message) use ($data) {
                $message->to($data['to']);
                $message->subject($data['subject']);
                $message->html($data['content']);
            });

            Log::info('Password reset email sent successfully', [
                'email' => $user_email,
                'username' => $username
            ]);
        } catch (\Throwable $th) {
            Log::error('Password reset email job failed: ' . $th->getMessage());
            ErrorDispatcher::dispatch($th);
            throw $th;
        }
    }

    /**
     * Build password reset email HTML
     */
    private function buildPasswordResetEmail($username, $company_name, $reset_url, $validity_hours)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Reset Request</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f4f4f4;
                }
                .container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: #ffffff;
                    border-radius: 5px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .header {
                    background: #3498db;
                    color: #ffffff;
                    padding: 20px;
                    text-align: center;
                }
                .content {
                    padding: 30px;
                }
                .button {
                    display: inline-block;
                    padding: 12px 30px;
                    margin: 20px 0;
                    background: #3498db;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                }
                .footer {
                    background: #f8f8f8;
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .alert {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 12px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Password Reset Request</h2>
                </div>
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
                    
                    <p>We received a request to reset your password for your ' . htmlspecialchars($company_name) . ' account.</p>
                    
                    <p>Click the button below to reset your password:</p>
                    
                    <div style="text-align: center;">
                        <a href="' . htmlspecialchars($reset_url) . '" class="button">Reset Password</a>
                    </div>
                    
                    <p>Or copy and paste this link into your browser:</p>
                    <p style="word-break: break-all; background: #f8f8f8; padding: 10px; border-radius: 3px;">
                        ' . htmlspecialchars($reset_url) . '
                    </p>
                    
                    <div class="alert">
                        <strong>⚠️ Important:</strong> This password reset link will expire in ' . htmlspecialchars($validity_hours) . ' hours.
                    </div>
                    
                    <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                    
                    <p>For security reasons, never share this link with anyone.</p>
                    
                    <p>Best regards,<br>
                    <strong>' . htmlspecialchars($company_name) . ' Team</strong></p>
                </div>
                <div class="footer">
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($company_name) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    public function failed(\Throwable $exception): void
    {
        $resetDetailsArray = $this->resetDetailsArray;
        Log::error('Password reset email job failed', [
            'email' => $resetDetailsArray['email'] ?? 'unknown',
            'username' => $resetDetailsArray['username'] ?? 'unknown',
            'error' => $exception->getMessage()
        ]);
    }
}
