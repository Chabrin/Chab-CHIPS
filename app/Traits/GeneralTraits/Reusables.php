<?php

namespace App\Traits\GeneralTraits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


trait Reusables
{

    public function checkGlobalRole(int $userId)
    {
        try {
            $hasGlobalRole = DB::table('cb_role_user as ru')
                ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->where('r.is_global', true)
                ->exists();
            return $hasGlobalRole;
        } catch (\Throwable $e) {
            // Log the error for debugging
            Log::error("Error checking global role for user_id={$userId}: " . $e->getMessage());
            // Return false as a safe default if there's an error
            return false;
        }
    }
}
