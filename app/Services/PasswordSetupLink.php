<?php

namespace App\Services;

use App\Mail\SetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The emailed "set your own password" link for a new system user.
 *
 * Nobody but the new user ever sees or chooses their password: the account is made with a long random password that is never
 * shown or sent, and the user is emailed a link to choose their own. The link:
 * - is sent only by email. It is never returned to the page, so an administrator cannot use it to set the password;
 * - works once and stops working after 48 hours;
 * - is replaced when a new one is sent, which cancels the older one;
 * - is kept only as a SHA-256 hash, so reading the database does not give anyone a working link.
 */
class PasswordSetupLink
{
    public const VALID_HOURS = 48;

    /** No link outstanding: the account is active, or was made before this way of setting passwords existed. */
    public const NONE = 'none';
    public const WAITING = 'waiting';
    public const EXPIRED = 'expired';

    /**
     * A password nobody knows. The account cannot be signed in to until the person sets their own.
     */
    public static function unusablePassword(): string
    {
        return Str::random(64);
    }

    /**
     * Make a new link for this user and cancel any earlier one. Returns the secret that goes in the emailed link, and nowhere else.
     */
    public function issue(User $user): string
    {
        $token = Str::random(64);
        User::whereKey($user->id)->update([
            'password_setup_token' => hash('sha256', $token),
            'password_setup_expires_at' => now()->addHours(self::VALID_HOURS),
            'password_setup_sent_at' => null,
            'password_setup_used_at' => null,
        ]);

        return $token;
    }

    /**
     * Make a new link and email it. Returns false when the email could not be sent; the link then stays and Resend makes a new one.
     */
    public function send(User $user): bool
    {
        try {
            $token = $this->issue($user);
            Mail::to($user->email)->send(new SetPasswordMail(
                name: (string) $user->name,
                email: (string) $user->email,
                url: route('password.setup', ['token' => $token]),
                hours: self::VALID_HOURS,
            ));
            User::whereKey($user->id)->update(['password_setup_sent_at' => now()]);
        } catch (\Throwable $e) {
            // The link is not logged: it is the secret. A problem here must never undo or fail the save that came before it.
            Log::warning('Password setup link not sent: ' . $e->getMessage(), ['user_id' => $user->id]);

            return false;
        }

        return true;
    }

    /**
     * The user a link belongs to, whether or not it is still good (so a used or expired link can say so). Null for anything else.
     */
    public function find(string $token): ?User
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            return null;
        }

        return User::where('password_setup_token', hash('sha256', $token))->first();
    }

    /**
     * Where this user's link stands: NONE, WAITING (sent, not used, not expired) or EXPIRED (not used and too old).
     *
     * @param  string|\DateTimeInterface|null  $expiresAt
     * @param  string|\DateTimeInterface|null  $usedAt
     */
    public static function state(?string $tokenHash, $expiresAt, $usedAt): string
    {
        if (!$tokenHash || $usedAt) {
            return self::NONE;
        }

        return $expiresAt && now()->gt(Carbon::parse($expiresAt)) ? self::EXPIRED : self::WAITING;
    }

    public function stateOf(User $user): string
    {
        return self::state($user->password_setup_token, $user->password_setup_expires_at, $user->password_setup_used_at);
    }

    /**
     * Save the password the person chose and use the link up. The link is checked again with the user's row locked, so two
     * people opening it at once cannot both succeed.
     *
     * @throws \DomainException when the link is no longer good (used, expired, replaced) or the account is suspended
     */
    public function complete(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (!$locked || $this->stateOf($locked) !== self::WAITING) {
                throw new \DomainException('This link can no longer be used.');
            }
            if ($locked->block) {
                throw new \DomainException('This account is suspended. Please contact HR.');
            }
            $locked->password = $password;
            $locked->requireReset = false;
            $locked->save();
            User::whereKey($locked->id)->update(['password_setup_used_at' => now()]);
        });
    }
}
