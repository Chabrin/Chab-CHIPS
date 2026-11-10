<?php

namespace App\Services\Comms;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Turns the mail settings saved on the Communications settings page into a named mailer, so the
 * module sends through those credentials instead of the server's .env. When nothing usable is
 * saved it returns null and the module sends through the default mailer as before.
 *
 * It only registers its own named mailer and never changes the global mail.from, so other mail
 * sent by the same process (payslips, password resets) is not affected. The sender goes on each
 * message instead. The mailer is re-registered and its cached instance dropped, so a long-running
 * queue worker never keeps sending with a password that has since been changed.
 */
class MailConfigService
{
    const MAILER = 'comms';

    protected CommSettings $settings;
    protected ?array $config = null;
    protected bool $loaded = false;

    /**
     * Inject the settings store.
     *
     * @param CommSettings $settings
     */
    public function __construct(CommSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * The saved mail settings with the password decrypted, or null when none are usable.
     */
    public function config(): ?array {
        if (!$this->loaded) {
            $config = $this->settings->get('mail');
            $this->config = ($config && trim((string) ($config['host'] ?? '')) !== '') ? $config : null;
            $this->loaded = true;
        }

        return $this->config;
    }

    /**
     * Register the saved settings as the "comms" mailer and return its name; null means use the
     * default mailer (.env).
     */
    public function mailerName(): ?string {
        $config = $this->config();
        if (!$config) {
            return null;
        }

        Config::set('mail.mailers.' . self::MAILER, self::definition($config));
        Mail::purge(self::MAILER);

        return self::MAILER;
    }

    /**
     * The sender to put on each message: [address, name], or [null, null] to keep the default.
     */
    public function from(): array {
        $config = $this->config();
        $address = trim((string) ($config['from_address'] ?? ''));

        return $address === '' ? [null, null] : [$address, trim((string) ($config['from_name'] ?? '')) ?: config('app.company_name')];
    }

    /**
     * The reply-to to put on each message: [address, name], or [null, null] for none. The name
     * falls back to the sender name, then the company name, so replies never show a bare address.
     */
    public function replyTo(): array {
        $config = $this->config();
        $address = trim((string) ($config['reply_to'] ?? ''));

        if ($address === '') {
            return [null, null];
        }

        return [$address, trim((string) ($config['reply_to_name'] ?? '')) ?: (trim((string) ($config['from_name'] ?? '')) ?: config('app.company_name'))];
    }

    /**
     * The Laravel mailer definition for a set of saved settings. SSL means an implicit-TLS
     * connection (port 465, scheme smtps); TLS and none both use the smtp scheme, which upgrades
     * the connection with STARTTLS when the server offers it.
     *
     * @param array $config host, port, encryption, username, password
     */
    public static function definition(array $config): array {
        return [
            'transport' => 'smtp',
            'scheme' => ($config['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
            'host' => $config['host'],
            'port' => (int) ($config['port'] ?: 587),
            'username' => ($config['username'] ?? '') !== '' ? $config['username'] : null,
            'password' => ($config['password'] ?? '') !== '' ? $config['password'] : null,
            'timeout' => 30,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ];
    }
}
