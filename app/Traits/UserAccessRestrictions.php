<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait UserAccessRestrictions
{

    protected function checkUserAccessRestrictions($user, $request)
    {
        // Check account date restrictions
        $dateCheck = $this->checkAccountDateRestrictions($user, $request);
        if ($dateCheck) return $dateCheck;

        // Check login days restriction
        $daysCheck = $this->checkLoginDaysRestriction($user, $request);
        if ($daysCheck) return $daysCheck;

        // Check login time restrictions
        $timeCheck = $this->checkLoginTimeRestrictions($user, $request);
        if ($timeCheck) return $timeCheck;

        // All checks passed
        return null;
    }


    protected function checkAccountDateRestrictions($user, $request)
    {
        $currentDate = Carbon::now()->toDateString();

        // Check start date
        if ($user->start_date && Carbon::parse($user->start_date)->isAfter($currentDate)) {
            Log::warning('Authentication attempt before account start date', [
                'user_id' => $user->id,
                'email' => $user->email,
                'start_date' => $user->start_date,
                'current_date' => $currentDate,
                'ip' => $request->ip()
            ]);
            return [
                'status' => 'error',
                'message' => 'Account access will begin on ' . Carbon::parse($user->start_date)->format('M d, Y') . '. Please contact admin if you need early access.'
            ];
        }

        // Check end date
        if ($user->end_date && Carbon::parse($user->end_date)->isBefore($currentDate)) {
            Log::warning('Authentication attempt after account end date', [
                'user_id' => $user->id,
                'email' => $user->email,
                'end_date' => $user->end_date,
                'current_date' => $currentDate,
                'ip' => $request->ip()
            ]);
            return [
                'status' => 'error',
                'message' => 'Account access expired on ' . Carbon::parse($user->end_date)->format('M d, Y') . '. Please contact admin to renew access.'
            ];
        }

        return null;
    }
    protected function checkLoginDaysRestriction($user, $request)
    {
        if ($user->login_days && is_array($user->login_days) && count($user->login_days) > 0) {
            $normalized = array_map('strtolower', $user->login_days);
            $normalized = array_values(array_unique($normalized));

            // If nothing valid after normalization, treat as no restriction
            if (count($normalized) === 0) {
                return null;
            }

            $currentDay = strtolower(Carbon::now()->format('l')); // e.g., "tuesday"
            if (!in_array($currentDay, $normalized)) {
                Log::warning('Authentication attempt on restricted day', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'current_day' => $currentDay,
                    'allowed_days' => $normalized,
                    'ip' => $request->ip()
                ]);

                // Capitalize the first letter of each allowed day
                $allowedDaysFormatted = array_map('ucfirst', $normalized);

                return [
                    'status' => 'error',
                    'message' => 'Access is only allowed on: ' . implode(', ', $allowedDaysFormatted) . '. Today is ' . ucfirst($currentDay) . '.'
                ];
            }
        }

        return null;
    }

    protected function checkLoginTimeRestrictions($user, $request)
    {
        if ($user->time_in && $user->time_out) {
            $currentTime = Carbon::now()->format('H:i:s');
            $timeIn = $user->time_in;
            $timeOut = $user->time_out;

            // Handle cases where time_out is next day (e.g., night shifts)
            if ($timeOut < $timeIn) {
                // Night shift case: e.g., 22:00 to 06:00
                $isInTimeRange = ($currentTime >= $timeIn) || ($currentTime <= $timeOut);
            } else {
                // Normal day shift: e.g., 09:00 to 17:00
                $isInTimeRange = ($currentTime >= $timeIn) && ($currentTime <= $timeOut);
            }

            if (!$isInTimeRange) {
                Log::warning('Authentication attempt outside allowed time', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'current_time' => $currentTime,
                    'allowed_time_in' => $timeIn,
                    'allowed_time_out' => $timeOut,
                    'ip' => $request->ip()
                ]);

                $timeInFormatted = Carbon::createFromFormat('H:i:s', $timeIn)->format('h:i A');
                $timeOutFormatted = Carbon::createFromFormat('H:i:s', $timeOut)->format('h:i A');

                return [
                    'status' => 'error',
                    'message' => 'Access is only allowed between ' . $timeInFormatted . ' and ' . $timeOutFormatted . '. Current time is ' . Carbon::createFromFormat('H:i:s', $currentTime)->format('h:i A') . '.'
                ];
            }
        }

        return null;
    }

    /**
     * Check if user can access during business hours (helper method)
     *
     * @param \App\Models\User $user
     * @return bool
     */
    protected function isWithinBusinessHours($user)
    {
        if (!$user->time_in || !$user->time_out) {
            return true; // No time restrictions
        }

        $currentTime = Carbon::now()->format('H:i:s');
        $timeIn = $user->time_in;
        $timeOut = $user->time_out;

        if ($timeOut < $timeIn) {
            // Night shift case
            return ($currentTime >= $timeIn) || ($currentTime <= $timeOut);
        } else {
            // Day shift case
            return ($currentTime >= $timeIn) && ($currentTime <= $timeOut);
        }
    }

    /**
     * Check if user can access on current day (helper method)
     *
     * @param \App\Models\User $user
     * @return bool
     */
    protected function isAllowedDay($user)
    {
        if (!$user->login_days || !is_array($user->login_days) || count($user->login_days) === 0) {
            return true; // No day restrictions
        }

        $currentDay = strtolower(Carbon::now()->format('l'));
        $allowedDays = array_map('strtolower', $user->login_days);

        return in_array($currentDay, $allowedDays);
    }

    /**
     * Get user's next allowed login time
     *
     * @param \App\Models\User $user
     * @return string|null
     */
    protected function getNextAllowedLoginTime($user)
    {
        if (!$user->login_days || !is_array($user->login_days) || count($user->login_days) === 0) {
            return null; // No restrictions
        }

        $currentDay = strtolower(Carbon::now()->format('l'));
        $allowedDays = array_map('strtolower', $user->login_days);

        // Find next allowed day
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentDayIndex = array_search($currentDay, $daysOfWeek);

        for ($i = 1; $i <= 7; $i++) {
            $nextDayIndex = ($currentDayIndex + $i) % 7;
            $nextDay = $daysOfWeek[$nextDayIndex];

            if (in_array($nextDay, $allowedDays)) {
                $nextDate = Carbon::now()->addDays($i)->format('M d, Y');
                $timeIn = $user->time_in ? Carbon::createFromFormat('H:i:s', $user->time_in)->format('h:i A') : '12:00 AM';
                return $nextDate . ' at ' . $timeIn;
            }
        }

        return null;
    }
}
