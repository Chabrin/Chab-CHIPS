<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads the Password Security settings kept on the System Controls page (cb_system_configs).
 *
 * Every rule has a default, so a missing, blank or invalid row never breaks a login: the default is used instead.
 * The master switch ships off. While it is off none of the rules below are enforced.
 */
class PasswordSecuritySettings
{
    public const CATEGORY = 'password_security';

    /** Type, default and allowed range of every setting. */
    public const SETTINGS = [
        'password_security_enabled' => ['label' => 'Enforce password security', 'type' => 'toggle', 'default' => '0'],
        'password_expiry_days' => ['label' => 'Password expires after (days)', 'type' => 'int', 'default' => '30', 'min' => 1, 'max' => 365],
        'password_reminder_days' => ['label' => 'Remind before expiry (days, comma separated)', 'type' => 'days', 'default' => '3,1'],
        'password_min_length' => ['label' => 'Minimum password length', 'type' => 'int', 'default' => '8', 'min' => 8, 'max' => 64],
        'password_require_complexity' => ['label' => 'Require upper case, lower case and a number', 'type' => 'toggle', 'default' => '1'],
        'password_history_count' => ['label' => 'Recent passwords that cannot be reused (0 = all)', 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 50],
        'password_block_similar' => ['label' => 'Block a password similar to the current one', 'type' => 'toggle', 'default' => '1'],
    ];

    protected ?array $values = null;

    /**
     * Whether password security is switched on.
     */
    public function enabled(): bool {
        return $this->toggle('password_security_enabled');
    }

    /**
     * Days a password stays valid.
     */
    public function expiryDays(): int {
        return $this->int('password_expiry_days');
    }

    /**
     * Days before expiry on which a reminder is sent, largest first. An empty list means no reminders.
     *
     * @return int[]
     */
    public function reminderDays(): array {
        $days = $this->days($this->raw('password_reminder_days'));
        $days = array_values(array_filter($days, fn ($day) => $day < $this->expiryDays()));
        rsort($days);

        return $days;
    }

    /**
     * Minimum password length while security is on; $legacy (the form's old fixed rule) while it is off.
     *
     * @param int $legacy
     */
    public function minLength(int $legacy = 8): int {
        return $this->enabled() ? $this->int('password_min_length') : $legacy;
    }

    /**
     * Whether new passwords need upper and lower case letters and a number.
     */
    public function requireComplexity(): bool {
        return $this->enabled() && $this->toggle('password_require_complexity');
    }

    /**
     * How many recent passwords cannot be reused. Zero means every past password.
     */
    public function historyCount(): int {
        return $this->int('password_history_count');
    }

    /**
     * Whether a new password may not be a small variation of the current one.
     */
    public function blockSimilar(): bool {
        return $this->enabled() && $this->toggle('password_block_similar');
    }

    /**
     * Check a value typed on the System Controls page. Returns the problem in plain words, or null when it is fine.
     * Settings that are not password settings are never rejected here.
     *
     * @param string $setting
     * @param string|null $value
     */
    public static function validate(string $setting, ?string $value): ?string {
        $rule = self::SETTINGS[$setting] ?? null;
        if (!$rule) {
            return null;
        }
        $value = trim((string) $value);
        switch ($rule['type']) {
            case 'toggle':
                return in_array($value, ['0', '1'], true) ? null : $rule['label'] . ' must be on or off.';
            case 'int':
                return self::wholeNumber($value, $rule['min'], $rule['max']) !== null ? null
                    : $rule['label'] . ' must be a whole number from ' . $rule['min'] . ' to ' . $rule['max'] . '.';
            default:
                return $value === '' || self::parseDays($value) !== null ? null
                    : 'Reminder days must be whole numbers from 1 to 60 separated by commas, up to 5 of them, for example 7,3,1. Leave it empty for no reminders.';
        }
    }

    /**
     * Read the raw stored value of a setting, or its default when no row exists.
     *
     * @param string $key
     */
    protected function raw(string $key): string {
        if ($this->values === null) {
            try {
                $this->values = DB::table('cb_system_configs')->whereIn('setting', array_keys(self::SETTINGS))->pluck('value', 'setting')->all();
            } catch (\Throwable $e) {
                $this->values = [];
            }
        }

        return array_key_exists($key, $this->values) ? trim((string) $this->values[$key]) : self::SETTINGS[$key]['default'];
    }

    /**
     * A stored on/off setting; anything unreadable counts as its default.
     *
     * @param string $key
     */
    protected function toggle(string $key): bool {
        $value = strtolower($this->raw($key));
        if (in_array($value, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return self::SETTINGS[$key]['default'] === '1';
    }

    /**
     * A stored whole-number setting; anything outside its range counts as its default.
     *
     * @param string $key
     */
    protected function int(string $key): int {
        $rule = self::SETTINGS[$key];

        return self::wholeNumber($this->raw($key), $rule['min'], $rule['max']) ?? (int) $rule['default'];
    }

    /**
     * Reminder days from a stored list; an unreadable list counts as the default list.
     *
     * @param string $value
     * @return int[]
     */
    protected function days(string $value): array {
        if ($value === '') {
            return [];
        }

        return self::parseDays($value) ?? self::parseDays(self::SETTINGS['password_reminder_days']['default']);
    }

    /**
     * A whole number inside the range, or null.
     *
     * @param string $value
     * @param int $min
     * @param int $max
     */
    protected static function wholeNumber(string $value, int $min, int $max): ?int {
        if (!preg_match('/^\d{1,4}$/', $value)) {
            return null;
        }
        $number = (int) $value;

        return $number >= $min && $number <= $max ? $number : null;
    }

    /**
     * Turn "7, 3,1" into [7, 3, 1]. Null when an entry is not a whole number from 1 to 60 or there are more than 5.
     *
     * @param string $value
     * @return int[]|null
     */
    protected static function parseDays(string $value): ?array {
        $days = [];
        foreach (explode(',', $value) as $part) {
            $day = self::wholeNumber(trim($part), 1, 60);
            if ($day === null) {
                return null;
            }
            $days[] = $day;
        }
        $days = array_values(array_unique($days));

        return count($days) <= 5 ? $days : null;
    }
}
