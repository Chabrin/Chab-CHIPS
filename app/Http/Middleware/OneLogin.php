<?php

namespace App\Http\Middleware;

use App\Jobs\UserAccessLogJob;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OneLogin
{
    /**
     * Handle an incoming request.
     * If the user's stored session id does not match the current session id,
     * log the user out and redirect to login with a message.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!Auth::check()) {
                return $next($request);
            }

            $user = Auth::user();
            $currentSessionId = $request->session()->getId();

            // Signed back in by a "Remember me" cookie: re-check the account, record the login and make this the active session
            if (Auth::viaRemember()) {
                if ($user->block == 1 || $user->activation == 0) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')->with('error', 'Account is not active, contact admin.');
                }

                UserAccessLogJob::dispatch($user->id, 'login', $currentSessionId, $request->ip(), $request->userAgent(), [
                    'login_method' => 'remember_cookie',
                    'two_factor_required' => false,
                ]);
                Cache::put("onelogin_user_{$user->id}", $currentSessionId, now()->addMinutes(config('session.lifetime')));

                return $next($request);
            }
            $stored = Cache::get("onelogin_user_{$user->id}");

            if ($stored && $stored !== $currentSessionId) {
                Log::info('OneLogin: session mismatch, logging out user', ['user_id' => $user->id, 'current' => $currentSessionId, 'stored' => $stored]);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->with('error', 'You were logged out because your account was signed in from another location.');
            }

            return $next($request);
        } catch (\Throwable $e) {
            Log::error('OneLogin middleware error: ' . $e->getMessage());
            return $next($request);
        }
    }
}
