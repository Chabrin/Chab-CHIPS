<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Staff password rules. Every number and switch comes from the Password Security settings, see PasswordSecuritySettings.
 * Password changes and their dates are always recorded, even while the switch is off, so switching it on later starts from real history.
 */
class StaffPasswordPolicy
{
    protected PasswordSecuritySettings $settings;

    public function __construct(PasswordSecuritySettings $settings) {
        $this->settings = $settings;
    }

    /**
     * Days a password stays valid, from the Password Security settings.
     */
    public function validityDays(): int {
        return $this->settings->expiryDays();
    }

    public function appliesTo(User $user): bool
    {
        return (bool) $user->staff_id || ($user->exists && $user->staffLink()->exists());
    }

    /**
     * Whether the rules are enforced on this account: security is switched on and the account belongs to staff.
     *
     * @param User $user
     */
    public function isActiveFor(User $user): bool {
        return $this->settings->enabled() && $this->appliesTo($user);
    }

    public function expiresAt(User $user): Carbon
    {
        // New staff links also receive a full first period if no date was recorded.
        DB::table('password_securities')->insertOrIgnore([
            'user_id' => $user->id, 'password_changed_at' => now(),
            'password_updated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = DB::table('password_securities')->where('user_id', $user->id)->first();

        return Carbon::parse($record->password_changed_at ?? $record->password_updated_at ?? $record->created_at)
            ->addDays($this->settings->expiryDays());
    }

    public function requiresChange(User $user): bool
    {
        return $this->isActiveFor($user) && ($user->requireReset || now()->gte($this->expiresAt($user)));
    }

    /** Called inside the user's save transaction, with the current user row locked. */
    public function validate(User $user, string $password, ?string $currentHash): void
    {
        if (!$this->isActiveFor($user)) {
            return;
        }
        $history = DB::table('password_histories')->where('user_id', $user->id);
        if ($this->settings->historyCount() > 0) {
            $history->orderByDesc('id')->limit($this->settings->historyCount());
        }
        $hashes = $history->pluck('password_hash');
        if ($currentHash) {
            $hashes->push($currentHash);
        }
        foreach ($hashes as $hash) {
            if (Hash::check($password, $hash)) {
                throw ValidationException::withMessages([
                    'password' => $this->settings->historyCount() > 0
                        ? 'You used this password recently. Please choose a different password.'
                        : 'You have used this password before. Please choose a password you have never used.',
                ]);
            }
        }
    }

    /**
     * Validation rules for a password someone types in: the configured length and complexity while security is on,
     * the form's old fixed length while it is off.
     *
     * @param int $legacyMin
     */
    public function passwordRules(int $legacyMin = 8): array {
        if (!$this->settings->enabled()) {
            return ['min:' . $legacyMin];
        }
        $rule = Password::min($this->settings->minLength());
        if ($this->settings->requireComplexity()) {
            $rule->mixedCase()->numbers();
        }

        return [$rule];
    }

    /**
     * Refuse a new password that is only a small change of the current one, such as Summer2025! to Summer2026!.
     * Only the current password can be compared, because older passwords are stored as hashes.
     *
     * @param User $user
     * @param string $current
     * @param string $new
     * @param string $field
     */
    public function assertNotSimilar(User $user, string $current, string $new, string $field = 'password'): void {
        if (!$this->isActiveFor($user) || !$this->settings->blockSimilar() || !$this->isSimilar($current, $new)) {
            return;
        }
        throw ValidationException::withMessages([$field => 'The new password is too similar to your current password. Please choose a different one.']);
    }

    /**
     * Same letters with only the ending changed (digits or symbols), or at most two characters different.
     *
     * @param string $a
     * @param string $b
     */
    protected function isSimilar(string $a, string $b): bool {
        $a = mb_strtolower($a);
        $b = mb_strtolower($b);
        $baseA = preg_replace('/[^\p{L}]+$/u', '', $a);
        $baseB = preg_replace('/[^\p{L}]+$/u', '', $b);
        if ($baseA !== '' && $baseA === $baseB) {
            return true;
        }

        return levenshtein($a, $b) <= 2;
    }

    public function record(User $user, ?string $previousHash): void
    {
        // Track all system accounts so linking an account to staff later retains its history.
        foreach (array_filter([$previousHash, $user->password]) as $hash) {
            DB::table('password_histories')->insertOrIgnore([
                'user_id' => $user->id, 'password_hash' => $hash, 'created_at' => now(),
            ]);
        }
        DB::table('password_securities')->updateOrInsert(['user_id' => $user->id], [
            'password_changed_at' => now(), 'password_updated_at' => now(), 'updated_at' => now(),
        ]);
        $this->useUpSetupLink($user);
    }

    /**
     * Someone who sets a password another way (Forgot password, say) no longer waits for the emailed setup link, so that link is
     * used up too: the System Users list stops showing them as waiting and the old link stops working.
     */
    protected function useUpSetupLink(User $user): void
    {
        // Passwords change rarely, so asking each time is cheap; it also keeps older databases (before the setup columns) working
        if (!Schema::hasColumn('josya_users', 'password_setup_used_at')) {
            return;
        }
        DB::table('josya_users')->where('id', $user->id)
            ->whereNotNull('password_setup_token')->whereNull('password_setup_used_at')
            ->update(['password_setup_used_at' => now()]);
    }
}
