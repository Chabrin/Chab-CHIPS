<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\EmailTemplates;

trait EmailMessageTrait
{
    public function prepareMessage(string $eventType, string $channeltype, array $replacements): string
    {
        try {
            $template = EmailTemplates::where('name', '=', $eventType)
                ->where('comtype', '=', $channeltype)
                ->where('confirmed', '=', 1)
                ->first();
            if (!$template) {
                $errorMessage = "Email template not found or not confirmed: {$eventType} - {$channeltype}";
                Log::error($errorMessage);
                throw new \Exception($errorMessage);
            }
            $templateVariables = $template->template_variable;
            if (is_string($templateVariables)) {
                $templateVariables = json_decode($templateVariables, true);
            }

            if (!is_array($templateVariables)) {
                Log::error("Invalid template variables format for: {$eventType}");
                return $template->body ?? '';
            }
            $message = $template->body;
            // Normalize replacement keys to lowercase for case-insensitive matching
            $normalizedReplacements = [];
            foreach ($replacements as $key => $value) {
                $normalizedReplacements[strtolower($key)] = $value;
            }

            // Replace each template variable with provided values
            foreach ($templateVariables as $key => $placeholders) {
                $normalizedKey = strtolower($key);

                if (!isset($normalizedReplacements[$normalizedKey])) {
                    continue;
                }

                $value = $normalizedReplacements[$normalizedKey];

                if (is_array($placeholders)) {
                    // New format: {"username": ["{{USERNAME}}"]}
                    foreach ($placeholders as $placeholder) {
                        $pattern = '/' . preg_quote($placeholder, '/') . '/i';
                        $message = preg_replace($pattern, $value, $message);
                    }
                } else {
                    // Legacy format: {"username": "default_value"} or string values
                    $placeholder = '{{' . $key . '}}';
                    $pattern = '/' . preg_quote($placeholder, '/') . '/i';
                    $message = preg_replace($pattern, $value, $message);
                }
            }

            // Handle SMS specific formatting
            if ($template->comtype === 'sms') {
                $message = strip_tags($message);
                $message = preg_replace('/[^A-Za-z0-9\.\%\,\-\@\s]/', ' ', $message);
                $message = preg_replace('/\s+/', ' ', $message);
                $message = trim($message);
                Log::info("SMS message prepared: " . $message);
            }

            return $message;
        } catch (\Throwable $th) {
            Log::error('Error in prepareMessage: ' . $th->getMessage());
            Log::error('Stack trace: ' . $th->getTraceAsString());
            throw $th; // Re-throw the exception instead of returning empty string
        }
    }

    public function getEmailSubject(string $eventType): string
    {
        try {
            $template = EmailTemplates::where('name', '=', $eventType)
                ->where('comtype', '=', 'email')
                ->first();

            return $template->subject ?? 'No Subject';
        } catch (\Throwable $th) {
            Log::error('Error getting email subject: ' . $th->getMessage());
            return 'No Subject';
        }
    }
    public function getSystemReplacements(): array
    {
        return [
            'current_year' => date('Y'),
            'current_date' => date('Y-m-d'),
            'current_time' => date('H:i:s'),
            'current_datetime' => date('Y-m-d H:i:s'),
        ];
    }

    public function mergeReplacements(array $userReplacements): array
    {
        // Normalize user replacements to lowercase
        $normalizedUserReplacements = [];
        foreach ($userReplacements as $key => $value) {
            $normalizedUserReplacements[strtolower($key)] = $value;
        }
        return array_merge($this->getSystemReplacements(), $normalizedUserReplacements);
    }
}
