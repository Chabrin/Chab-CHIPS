<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The rules for the password a new system user chooses on the "set your password" page: at least 12 characters, long phrases
 * welcome (no forced capital letters or symbols), and the passwords people use most are refused. When the Password Security
 * settings are switched on and ask for more (a longer minimum, capitals and numbers), those apply too.
 *
 * The check is made here, on the server, so the page cannot be bypassed. It does not look passwords up on any outside service.
 */
class SetupPasswordRules
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 128;

    /** Passwords chosen most often (plus words this company's staff would guess). Compared in lower case, letters only. */
    private const COMMON = [
        'password', 'passwordpassword', 'passw0rd', 'welcome', 'welcomewelcome', 'letmein', 'qwerty', 'qwertyuiop', 'qwertyuiopasdfgh',
        'asdfghjkl', 'zxcvbnm', 'iloveyou', 'monkey', 'dragon', 'football', 'baseball', 'master', 'login', 'admin', 'administrator',
        'changeme', 'secret', 'princess', 'sunshine', 'shadow', 'superman', 'batman', 'trustno', 'trustnoone', 'whatever', 'freedom',
        'michael', 'jennifer', 'jesus', 'jesuschrist', 'godisgood', 'blessed', 'mungu', 'harambee', 'kenya', 'kenyan', 'nairobi',
        'mombasa', 'kisumu', 'nakuru', 'chabrin', 'chabrinagencies', 'chips', 'company', 'staff', 'employee', 'test', 'testing',
        'default', 'temporary', 'temppassword', 'newpassword', 'newuser', 'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december', 'summer', 'winter', 'spring', 'autumn', 'abcdefgh',
        'abcdefghijkl', 'letmein', 'starwars', 'hello', 'helloworld', 'internet', 'computer', 'security', 'unknown', 'access',
        'mypassword', 'yourpassword', 'ihaveapassword', 'iloveyou', 'loveyou', 'lovelove', 'family', 'friends', 'killer', 'soccer',
        'liverpool', 'arsenal', 'chelsea', 'manutd', 'manchester', 'barcelona', 'realmadrid',
    ];

    /** Letters people swap for look-alike digits and symbols, so P@ssw0rd is treated as password. */
    private const LOOK_ALIKES = ['@' => 'a', '4' => 'a', '0' => 'o', '1' => 'l', '!' => 'i', '3' => 'e', '$' => 's', '5' => 's', '7' => 't'];

    public function __construct(private PasswordSecuritySettings $settings)
    {
    }

    /**
     * The shortest password allowed now: 12, or longer when the Password Security settings ask for more.
     */
    public function minLength(): int
    {
        return $this->settings->enabled() ? max(self::MIN_LENGTH, $this->settings->minLength()) : self::MIN_LENGTH;
    }

    /**
     * What is wrong with this password, in words for the person typing it, or null when it is fine.
     */
    public function problem(string $password, ?User $user = null): ?string
    {
        $length = mb_strlen($password);
        if ($length < $this->minLength()) {
            return 'Use at least ' . $this->minLength() . ' characters. A phrase of several words works well.';
        }
        if ($length > self::MAX_LENGTH) {
            return 'Use no more than ' . self::MAX_LENGTH . ' characters.';
        }
        if ($this->isCommon($password)) {
            return 'This password is too common and easy to guess. Choose a longer phrase that only you would know.';
        }
        if ($this->isPattern($password)) {
            return 'This password is a simple pattern (repeated or in sequence). Choose a longer phrase that only you would know.';
        }
        if ($user && $this->containsPersonalDetail($password, $user)) {
            return 'Do not use your name, username, email or the company name in your password.';
        }
        if ($this->settings->requireComplexity()) {
            $check = Validator::make(['password' => $password], ['password' => [Password::min($this->minLength())->mixedCase()->numbers()]]);
            if ($check->fails()) {
                return $check->errors()->first('password');
            }
        }

        return null;
    }

    /**
     * The letters of the password in two readings: with every digit and symbol dropped (welcome@2026 is welcome), and with
     * the ending dropped and look-alikes turned back into letters (P@ssw0rd1! is password).
     *
     * @return string[]
     */
    private function lettersOnly(string $password): array
    {
        $lower = mb_strtolower($password);
        $plain = preg_replace('/[^a-z]+/', '', $lower) ?? '';
        $trimmed = preg_replace('/[^a-z]+$/', '', $lower) ?? '';
        $swapped = preg_replace('/[^a-z]+/', '', strtr($trimmed, self::LOOK_ALIKES)) ?? '';

        return array_values(array_unique(array_filter([$plain, $swapped], fn ($letters) => $letters !== '')));
    }

    private function isCommon(string $password): bool
    {
        foreach ($this->lettersOnly($password) as $letters) {
            if (in_array($letters, self::COMMON, true)) {
                return true;
            }
            // A word repeated, such as passwordpassword or welcome1welcome1
            foreach (self::COMMON as $word) {
                $size = strlen($word);
                if ($size >= 5 && strlen($letters) > $size && strlen($letters) % $size === 0 && $letters === str_repeat($word, intdiv(strlen($letters), $size))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * One character repeated, or a long run of the alphabet, the keyboard rows or the digits in order (abcdefghijkl, 123456789012).
     */
    private function isPattern(string $password): bool
    {
        $lower = mb_strtolower($password);
        if (preg_match('/^(.)\1+$/us', $lower)) {
            return true;
        }
        $runs = ['abcdefghijklmnopqrstuvwxyz', 'zyxwvutsrqponmlkjihgfedcba', 'qwertyuiopasdfghjklzxcvbnm', '01234567890123456789', '98765432109876543210'];
        $plain = preg_replace('/[^a-z0-9]+/', '', $lower) ?? '';
        if (strlen($plain) < 8) {
            return false;
        }
        foreach ($runs as $run) {
            if (str_contains($run, $plain)) {
                return true;
            }
        }

        return false;
    }

    private function containsPersonalDetail(string $password, User $user): bool
    {
        $lower = mb_strtolower($password);
        $words = [$user->username, strstr((string) $user->email, '@', true) ?: $user->email, 'chabrin'];
        foreach (preg_split('/[\s.\-_]+/', (string) $user->name) ?: [] as $part) {
            $words[] = $part;
        }
        foreach ($words as $word) {
            $word = mb_strtolower(trim((string) $word));
            if (mb_strlen($word) >= 4 && str_contains($lower, $word)) {
                return true;
            }
        }

        return false;
    }
}
