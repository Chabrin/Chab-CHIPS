<?php

namespace App\Services\Comms;

use App\Models\CommSetting;
use App\Traits\ChecksMenuAccess;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Stores the mail and SMS credentials of the Communications module in cb_comm_settings. Secrets
 * (the mail password, the SMS API key) are encrypted with the app key, are only ever decrypted
 * for sending, and are never handed to a page: the screen gets a "has a saved password" flag.
 * Saving with a blank secret keeps the one already stored. When nothing is saved the module falls
 * back to the server's .env settings, so it keeps working before anyone configures it.
 */
class CommSettings
{
    use ChecksMenuAccess;

    const PAGE = '/communications/settings';

    /** The fields each kind may hold; anything else posted is dropped. */
    const FIELDS = [
        'mail' => ['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'reply_to', 'reply_to_name'],
        'sms' => ['provider', 'username', 'api_key', 'sender_id'],
    ];

    /** The field of each kind that is a secret. */
    const SECRETS = ['mail' => 'password', 'sms' => 'api_key'];

    /**
     * Whether the signed-in user may open the credentials page.
     */
    public function canManage(): bool {
        return $this->menuAllowed(self::PAGE);
    }

    /**
     * The saved settings of a kind with the secret decrypted, for sending. Null when nothing is
     * saved, or when the saved secret can no longer be decrypted (the app key changed).
     *
     * @param string $key mail | sms
     */
    public function get(string $key): ?array {
        $stored = $this->raw($key);
        if (!$stored) {
            return null;
        }

        $secretField = self::SECRETS[$key];
        if (!empty($stored[$secretField])) {
            try {
                $stored[$secretField] = Crypt::decryptString($stored[$secretField]);
            } catch (DecryptException $e) {
                Log::warning("CommSettings: the saved {$key} secret cannot be decrypted; falling back to the server defaults");
                return null;
            }
        }

        return $stored;
    }

    /**
     * The settings of a kind as the settings page may see them: no secret, only whether one is saved.
     *
     * @param string $key mail | sms
     */
    public function view(string $key): array {
        $stored = $this->raw($key);
        $secretField = self::SECRETS[$key];

        $view = $stored ?: [];
        unset($view[$secretField]);

        return $view + ['saved' => $stored !== null, 'has_secret' => !empty($stored[$secretField] ?? null)];
    }

    /**
     * Save a kind. Only the allowed fields are kept; a blank secret keeps the stored one and a
     * given secret is encrypted.
     *
     * @param string $key mail | sms
     * @param array $data validated input
     * @param int|null $userId who is saving
     */
    public function save(string $key, array $data, ?int $userId): void {
        $secretField = self::SECRETS[$key];
        $existing = $this->raw($key) ?? [];

        $value = [];
        foreach (self::FIELDS[$key] as $field) {
            $value[$field] = isset($data[$field]) ? trim((string) $data[$field]) : '';
        }

        if ($value[$secretField] !== '') {
            $value[$secretField] = Crypt::encryptString($value[$secretField]);
        } else {
            $value[$secretField] = $existing[$secretField] ?? '';
        }

        CommSetting::updateOrCreate(['setting_key' => $key], ['value_json' => $value, 'updated_by' => $userId]);

        Log::info("CommSettings: {$key} settings saved", ['user_id' => $userId]);
    }

    /**
     * Forget the saved settings of a kind, so the module goes back to the server's .env.
     *
     * @param string $key mail | sms
     * @param int|null $userId who is clearing
     */
    public function clear(string $key, ?int $userId): void {
        CommSetting::where('setting_key', $key)->delete();

        Log::info("CommSettings: {$key} settings cleared, using the server defaults", ['user_id' => $userId]);
    }

    /**
     * The stored JSON of a kind, secret still encrypted. A failed read is logged and treated as
     * "nothing saved" so a database hiccup cannot stop mail that .env could still send.
     *
     * @param string $key mail | sms
     */
    protected function raw(string $key): ?array {
        try {
            $row = CommSetting::where('setting_key', $key)->first();
        } catch (\Throwable $e) {
            Log::warning('CommSettings: could not read the saved ' . $key . ' settings: ' . $e->getMessage());
            return null;
        }

        return $row && is_array($row->value_json) ? $row->value_json : null;
    }
}
