<?php

namespace App\Services;

use Throwable;
use App\Jobs\ErrorDispatchJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ErrorDispatcher
{
    /**
     * Extracts useful info from the exception and dispatches the ErrorDispatchJob.
     * Usage: ErrorDispatcher::dispatch($e, $user);
     *
     * @param Throwable $e
     * @param mixed|null $user
     * @return void
     */
    public static function dispatch(Throwable $e, $user = null): void
    {
        try {
            $errorMsg = $e->getMessage();
            $stack = method_exists($e, 'getTraceAsString') ? $e->getTraceAsString() : null;
            $route = null;
            try {
                $route = request()->path();
            } catch (\Exception $ex) {
                $route = null;
            }

            $userToPass = $user;
            if (is_null($userToPass)) {
                try {
                    $userToPass = request()->user() ?? null;
                } catch (\Exception $ux) {
                    $userToPass = null;
                }

                if (is_null($userToPass)) {
                    try {
                        $userToPass = Auth::user();
                    } catch (\Exception $ux) {
                        $userToPass = null;
                    }
                }
            }


            $userSnapshot = null;
            if (is_object($userToPass)) {
                $userSnapshot = [
                    'id' => $userToPass->id ?? null,
                    'username' => $userToPass->username ?? ($userToPass->name ?? null),
                ];
            } else {
                $userSnapshot = $userToPass;
            }
            ErrorDispatchJob::dispatch($errorMsg, $stack, $route, $userSnapshot);
        } catch (\Exception $ex) {
            Log::error('ErrorDispatcher failed to dispatch: ' . $ex->getMessage());
        }
    }
}
