<?php

namespace App\Services\Comms;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;

/**
 * The one way the system sends an SMS. Provider-ready: the gateway is picked by
 * services.sms.provider and each provider is one private method; until credentials are
 * configured every send fails cleanly and is still logged with its reason. Writes a
 * cb_sms_logs row for every attempt. Never throws at the caller: returns [$ok, $error].
 */
class SmsService
{
    const GSM_SINGLE = 160;
    const GSM_MULTI = 153;

    protected CommSettings $settings;

    /**
     * Inject the store of the credentials saved on the settings page.
     *
     * @param CommSettings $settings
     */
    public function __construct(CommSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * Send one SMS and log the outcome.
     *
     * @param string $to recipient phone number
     * @param string $recipientName
     * @param string $message already placeholder-resolved, plain text
     * @param string $type what kind of SMS this is: general, arrears, tenant_exit ...
     * @param int|null $batchId cb_comm_batches id when part of a bulk send
     * @param string|null $recipientType tenant | landlord | staff | user
     * @param int|null $recipientId
     * @param string|null $entityType the record that caused the SMS
     * @param int|null $entityId
     * @param int|null $sentBy user id; defaults to the logged-in user
     * @return array [$ok, $error]
     */
    public function send(string $to, string $recipientName, string $message, string $type = 'general', ?int $batchId = null, ?string $recipientType = null, ?int $recipientId = null, ?string $entityType = null, ?int $entityId = null, ?int $sentBy = null): array {
        $gateway = $this->gatewayConfig();
        $provider = $gateway['provider'];

        [$ok, $error, $gatewayResponse, $messageId] = match ($provider) {
            'africastalking' => $this->sendViaAfricasTalking($to, $message, $gateway),
            default => [false, 'SMS gateway is not configured yet. Set SMS_PROVIDER and its credentials.', null, null],
        };

        SmsLog::create([
            'batch_id' => $batchId,
            'type' => $type,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'recipient_name' => $recipientName,
            'recipient_phone' => $to,
            'message' => $message,
            'units' => self::units($message),
            'status' => $ok ? 'sent' : 'failed',
            'error' => $error ? mb_substr($error, 0, 2000) : null,
            'gateway_response' => $gatewayResponse,
            'message_id' => $messageId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sent_by' => $sentBy ?? auth()->id(),
            'sent_at' => now(),
        ]);

        return [$ok, $error];
    }

    /**
     * Billable SMS segments of a message: 160 chars for one segment, 153 per segment after that
     * (GSM-7; a message with non-GSM characters may cost more at the gateway).
     *
     * @param string $message
     */
    public static function units(string $message): int {
        $length = mb_strlen($message);

        return $length <= self::GSM_SINGLE ? 1 : (int) ceil($length / self::GSM_MULTI);
    }

    /**
     * The gateway to use: the credentials saved on the settings page when a provider is chosen
     * there, otherwise the server's .env (services.sms).
     *
     * @return array provider, username, api_key, sender_id
     */
    protected function gatewayConfig(): array {
        $saved = $this->settings->get('sms');

        if ($saved && ($saved['provider'] ?? 'none') !== 'none' && ($saved['provider'] ?? '') !== '') {
            return ['provider' => $saved['provider'], 'username' => $saved['username'] ?? '', 'api_key' => $saved['api_key'] ?? '', 'sender_id' => $saved['sender_id'] ?? ''];
        }

        return ['provider' => config('services.sms.provider', 'none'), 'username' => config('services.sms.username'), 'api_key' => config('services.sms.api_key'), 'sender_id' => config('services.sms.sender_id')];
    }

    /**
     * Send through Africa's Talking.
     *
     * @param string $to
     * @param string $message
     * @param array $gateway username, api_key, sender_id
     * @return array [$ok, $error, $gatewayResponse, $messageId]
     */
    private function sendViaAfricasTalking(string $to, string $message, array $gateway): array {
        $username = $gateway['username'];
        $apiKey = $gateway['api_key'];
        $from = $gateway['sender_id'];

        if (!$username || !$apiKey) {
            return [false, 'Africa\'s Talking credentials are incomplete.', null, null];
        }

        try {
            $response = Http::withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])->asForm()->post('https://api.africastalking.com/version1/messaging', array_filter(['username' => $username, 'to' => $to, 'message' => $message, 'from' => $from]));

            $recipient = $response->json('SMSMessageData.Recipients.0');
            $ok = $response->successful() && ($recipient['statusCode'] ?? 0) === 101;

            return [$ok, $ok ? null : ($recipient['status'] ?? $response->body()), mb_substr($response->body(), 0, 2000), $recipient['messageId'] ?? null];
        } catch (\Throwable $e) {
            return [false, $e->getMessage(), null, null];
        }
    }
}
