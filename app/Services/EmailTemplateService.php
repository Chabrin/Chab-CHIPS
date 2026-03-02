<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Traits\TemplateVariableHelper;

class EmailTemplateService
{
    use TemplateVariableHelper;

    /**
     * Get a confirmed template by name (no comtype filtering by default).
     */
    public function getConfirmedTemplateByName(string $name): ?EmailTemplate
    {
        return EmailTemplate::where('name', $name)
            ->where('confirmed', true)
            ->first();
    }

    /**
     * Process an email template with variable replacement.
     */
    public function buildProcessedTemplate(EmailTemplate $template, array $data = []): array
    {
        return $this->processEmailTemplate($template, $data);
    }

    /**
     * Wrap body content in a basic HTML layout for email rendering.
     */
    public static function formatEmailBody(string $body): string
    {
        $body = nl2br($body);

        return "\n        <!DOCTYPE html>\n        <html>\n        <head>\n            <meta charset='utf-8'>\n            <meta name='viewport' content='width=device-width, initial-scale=1'>\n            <title>Email Notification</title>\n            <style>\n                body {\n                    font-family: Arial, sans-serif;\n                    line-height: 1.6;\n                    color: #333;\n                    max-width: 600px;\n                    margin: 0 auto;\n                    padding: 20px;\n                }\n                .header {\n                    border-bottom: 2px solid #007bff;\n                    padding-bottom: 20px;\n                    margin-bottom: 30px;\n                }\n                .content {\n                    margin-bottom: 30px;\n                }\n                .footer {\n                    border-top: 1px solid #ddd;\n                    padding-top: 20px;\n                    font-size: 12px;\n                    color: #666;\n                    text-align: center;\n                }\n                .amount {\n                    font-weight: bold;\n                    color: #007bff;\n                }\n            </style>\n        </head>\n        <body>\n            <div class='header'>\n                <h2>" . config('app.company_name', 'Pension Administration System') . "</h2>\n            </div>\n            <div class='content'>\n                {$body}\n            </div>\n            <div class='footer'>\n                <p>This is an automated message. Please do not reply to this email.</p>\n                <p>For support, contact us at " . config('app.support_email', 'support@pensionsystem.com') . "</p>\n            </div>\n        </body>\n        </html>";
    }
}
