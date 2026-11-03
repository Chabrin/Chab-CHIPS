<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffPasswordPolicy
{
    public const VALIDITY_DAYS = 30;

    public function appliesTo(User $user): bool
    {
        return (bool) $user->staff_id || ($user->exists && $user->staffLink()->exists());
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
            ->addDays(self::VALIDITY_DAYS);
    }

    public function requiresChange(User $user): bool
    {
        return $this->appliesTo($user) && ($user->requireReset || now()->gte($this->expiresAt($user)));
    }

    /** Called inside the user's save transaction, with the current user row locked. */
    public function validate(User $user, string $password, ?string $currentHash): void
    {
        if (!$this->appliesTo($user)) {
            return;
        }
        $hashes = DB::table('password_histories')->where('user_id', $user->id)->pluck('password_hash');
        if ($currentHash) {
            $hashes->push($currentHash);
        }
        foreach ($hashes as $hash) {
            if (Hash::check($password, $hash)) {
                throw ValidationException::withMessages([
                    'password' => 'You have used this password before. Please choose a password you have never used.',
                ]);
            }
        }
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
    }
}
