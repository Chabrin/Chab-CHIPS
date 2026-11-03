<?php


namespace App\Http\Controllers\Auth;

use Exception;
use Carbon\Carbon;
use App\Models\User;

use GuzzleHttp\Client;
use App\Models\Company;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\AdminLoginLog;
use App\Jobs\PasswordResetJob;
use App\Models\PasswordExpiry;
use Torann\GeoIP\Facades\GeoIP;
use App\Models\PasswordSecurity;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use App\Traits\UserAccessRestrictions;
use Illuminate\Support\Facades\Session;
use PragmaRX\Google2FALaravel\Facades\Google2FA;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use App\Models\TwoFactorSession;
use App\Jobs\UserAccessLogJob;

class LoginController extends Controller
{
    use UserAccessRestrictions;


    public function show2faForm($two_factor_token)
    {

        $sessionTokenData = TwoFactorSession::where('token', $two_factor_token)->first();

        if (!$two_factor_token || !$sessionTokenData) {
            return redirect()->route('login')->with('error', 'Invalid or expired 2FA session. Please login again.');
        }

        // Check expiry
        if ($sessionTokenData->expires_at && now()->gt($sessionTokenData->expires_at)) {
            $sessionTokenData->delete();
            return redirect()->route('login')->with('error', '2FA session expired. Please login again.');
        }

        $user = User::find($sessionTokenData->user_id);
        if (!$user) {
            return redirect()->route('login')->with('error', 'User not found or session invalid. Please login again.');
        }

        $system2faEnabled = (int)(DB::table('cb_system_configs')
            ->where('setting', 'two_factor_enabled')
            ->value('value') ?? 1);

        if ($system2faEnabled) {
            if (!$user->google2fa_enabled || !$user->google2fa_secret) {
                $google2fa = app('pragmarx.google2fa');
                $secret = $google2fa->generateSecretKey();

                // Save secret in DB session
                $sessionTokenData->secret = $secret;
                $sessionTokenData->save();

                try {
                    $qrCode = $google2fa->getQRCodeInline(
                        config('app.name'),
                        $user->email,
                        $secret
                    );
                } catch (\Throwable $e) {
                    ErrorDispatcher::dispatch($e);
                    Log::info("Error Generating QR code: " . $e->getMessage());
                    $issuer = rawurlencode(config('app.name'));
                    $account = rawurlencode($user->email);
                    $otpauth = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";
                    $qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . rawurlencode($otpauth);
                    $qrCode = '<img src="' . $qrUrl . '" alt="QR Code" />';
                }

                return view('auth.2fa', [
                    'qrCode' => $qrCode,
                    'secret' => $secret,
                    'two_factor_token' => $two_factor_token
                ]);
            }

            return view('auth.2fa', ['two_factor_token' => $two_factor_token]);
        }

        return redirect()->route('dashboard');
    }

    public function verify2fa(Request $request)
    {
        try {
            $token = $request->input('two_factor_token');
            $sessionTokenData = TwoFactorSession::where('token', $token)->first();

            if (!$sessionTokenData) {
                return response()->json(['status' => 'error', 'message' => 'Session expired. Please login again.']);
            }

            $user = User::find($sessionTokenData->user_id);
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not found.']);
            }

            $code = $request->input('one_time_password');
            if (!$code) {
                return response()->json(['status' => 'error', 'message' => 'Authenticator code is required.']);
            }

            $google2fa = app('pragmarx.google2fa');

            // If setting up 2FA
            if (!$user->google2fa_enabled || !$user->google2fa_secret) {
                $secret = $sessionTokenData->secret;
                if (!$secret) {
                    return response()->json(['status' => 'error', 'message' => '2FA setup expired.']);
                }

                $valid = $google2fa->verifyKey($secret, $code);
                if ($valid) {
                    $user->google2fa_secret = $secret;
                    $user->google2fa_enabled = 1;
                    $user->save();

                    $sessionTokenData->delete();

                    Auth::login($user);

                    // Dispatch user access log job for 2FA setup completion
                    UserAccessLogJob::dispatch(
                        $user->id,
                        'login',
                        session()->getId(),
                        request()->ip(), // Note: this is in a different context, keeping as is for now
                        request()->userAgent(),
                        [
                            'login_method' => '2fa_setup',
                            'two_factor_required' => true
                        ]
                    );

                    try {
                        Cache::forever("onelogin_user_{$user->id}", session()->getId());
                    } catch (\Throwable $e) {
                        Log::warning('Failed to write onelogin cache after 2fa setup: ' . $e->getMessage());
                    }
                    $intendedUrl = $request->session()->pull('url.intended', route('dashboard'));
                    return response()->json(['status' => 'success', 'message' => '2FA setup complete!', 'redirect' => $intendedUrl]);
                }
                return response()->json(['status' => 'error', 'message' => 'Invalid code.']);
            }

            // If already enabled, verify
            $valid = $google2fa->verifyKey($user->google2fa_secret, $code);
            if ($valid) {
                $sessionTokenData->delete();
                Auth::login($user);

                // Dispatch user access log job for 2FA verification
                UserAccessLogJob::dispatch(
                    $user->id,
                    'login',
                    session()->getId(),
                    request()->ip(), // Note: this is in a different context, keeping as is for now
                    request()->userAgent(),
                    [
                        'login_method' => '2fa_verify',
                        'two_factor_required' => true
                    ]
                );

                try {
                    Cache::forever("onelogin_user_{$user->id}", session()->getId());
                } catch (\Throwable $e) {
                    Log::warning('Failed to write onelogin cache after 2fa verify: ' . $e->getMessage());
                }
                $intendedUrl = $request->session()->pull('url.intended', route('dashboard'));
                return response()->json(['status' => 'success', 'message' => '2FA verified!', 'redirect' => $intendedUrl]);
            }

            return response()->json(['status' => 'error', 'message' => 'Invalid code.']);
        } catch (Exception $e) {
            Log::error('2FA Verification error: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json(['status' => 'error', 'message' => '2FA service temporarily unavailable.']);
        }
    }

    public function login()
    {
        return view('auth.login');
    }


    public function authenticate(Request $request)
    {
        //Log::info('Authentication attempt for passw: ' . $request->input('user_password'));
        //Hash pass
        $hashed = Hash::make($request->input('user_password'));
        //Log::info('Hashed passw: ' . $hashed);

        try {
            if (!$request->has('user_email') || !$request->has('user_password')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email and password are required'
                ]);
            }

            $allowedFailedAttempts = DB::table('cb_system_configs')
                ->where('setting', 'lockout_attempts')
                ->value('value') ?? 3;

            $two_factor_enabled = (int)(DB::table('cb_system_configs')
                ->where('setting', 'two_factor_enabled')
                ->value('value') ?? 1);

            $email = $request->input('user_email');
            $password = $request->input('user_password');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid email format.'
                ]);
            }
            $user = User::where('email', $email)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid login, check your credentials.'
                ]);
            }
            if ($user->block == 1 || $user->activation == 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is not active, contact admin.'
                ]);
            }


            // "Remember me" keeps the user signed in for the configured number of days (converted to minutes)
            Auth::guard()->setRememberDuration(60 * 24 * config('app.remember_me_days'));

            if (!Auth::attempt(['email' => $email, 'password' => $password], $request->boolean('remember'))) {
                $user->resetCount = ($user->resetCount ?? 0) + 1;
                $user->save();

                $remaining = $allowedFailedAttempts - $user->resetCount;
                if ($remaining <= 0) {
                    $user->block = 1;
                    $user->save();

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Account locked, kindly contact admin.'
                    ]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid credentials. $remaining attempts remaining."
                ]);
            }

            if ($two_factor_enabled == 1 && $user->two_factor_enabled == 1) {
                if (!$user->google2fa_enabled || !$user->google2fa_secret) {
                    $token = Str::random(40);
                    $expiresAt = now()->addMinutes(10);

                    TwoFactorSession::create([
                        'user_id'    => $user->id,
                        'token'      => $token,
                        'expires_at' => $expiresAt,
                    ]);


                    $redirectUrl = route('authenticator.form', ['two_factor_token' => $token]);
                    Auth::logout(); // keep user out until 2FA is set up

                    return response()->json([
                        'status'   => '2fa-setup',
                        'message'  => 'Two-factor authentication setup required.',
                        'redirect' => $redirectUrl,
                        'two_factor_token'    => $token
                    ]);
                }
                if ($user->google2fa_enabled && $user->google2fa_secret) {
                    $token = Str::random(40);
                    $expiresAt = now()->addMinutes(10);

                    TwoFactorSession::create([
                        'user_id'    => $user->id,
                        'token'      => $token,
                        'expires_at' => $expiresAt,
                    ]);

                    Auth::logout();
                    $redirectUrl = route('authenticator.form', ['two_factor_token' => $token]);

                    return response()->json([
                        'status'   => '2fa',
                        'message'  => 'Two-factor authentication required.',
                        'redirect' => $redirectUrl,
                        'two_factor_token'    => $token
                    ]);
                }
            }
            $request->session()->regenerate();
            $user->resetCount = 0;
            //$user->last_login_at = now();
            $user->save();

            // Dispatch user access log job for login
            UserAccessLogJob::dispatch(
                $user->id,
                'login',
                session()->getId(),
                $this->getRealIpAddress($request),
                $request->userAgent(),
                [
                    'login_method' => 'standard',
                    'two_factor_required' => false
                ]
            );

            try {
                $minutes = config('session.lifetime');
                Cache::put("onelogin_user_{$user->id}", session()->getId(), now()->addMinutes($minutes));
            } catch (\Throwable $e) {
                Log::warning('Failed to write onelogin cache on login: ' . $e->getMessage());
            }

            $intendedUrl = $request->session()->pull('url.intended', route('dashboard'));

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged in!',
                'redirect' => $intendedUrl
            ]);
        } catch (\Exception $e) {
            Log::error('Auth error: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Authentication service temporarily unavailable.'
            ]);
        }
    }


    public function logout(Request $request)
    {
        try {
            // Log the logout action
            if (Auth::check()) {
                $user = Auth::user();
                $sessionId = session()->getId();
                $ipAddress = $this->getRealIpAddress($request);
                $userAgent = $request->userAgent();

                Log::info('User logout', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'logout_time' => now()
                ]);

                // Dispatch user access log job for logout
                UserAccessLogJob::dispatch(
                    $user->id,
                    'logout',
                    $sessionId,
                    $ipAddress,
                    $userAgent,
                    [
                        'logout_method' => 'manual'
                    ]
                );
            }

            // Logout the user
            if (Auth::check()) {
                try {
                    Cache::forget("onelogin_user_" . Auth::id());
                } catch (\Throwable $e) {
                    Log::warning('Failed to clear onelogin cache during logout: ' . $e->getMessage());
                }
            }
            Auth::logout();

            // Invalidate the session
            $request->session()->invalidate();

            // Regenerate the CSRF token
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', 'You have been logged out successfully');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error during logout: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Logout failed, please try again');
        }
    }

    public function profile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return redirect()->route('login')->with('error', 'Please login to view your profile');
            }
            $user = User::with('employee', 'staffLink.staff')->find($user->id);
            $assignedCompanies = [];
            if ($user->role_companies && count($user->role_companies) > 0) {
                $assignedCompanies = Company::whereIn('id', $user->role_companies)
                    ->pluck('company_name', 'id')
                    ->toArray();
            }

            $policy = app(\App\Services\StaffPasswordPolicy::class);
            $staffPasswordPolicyApplies = $policy->appliesTo($user);
            $passwordChangeRequired = $user->requireReset || $policy->requiresChange($user);
            $showSecurity = $passwordChangeRequired || $request->query('tab') === 'security';

            return view('auth.profile', compact('user', 'assignedCompanies', 'staffPasswordPolicyApplies', 'passwordChangeRequired', 'showSecurity'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user profile: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load profile information');
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if (!Hash::check($validated['current_password'], $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            if (Hash::check($validated['new_password'], $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'new_password' => ['New password must be different from the current password.'],
                ]);
            }

            $user->password = $validated['new_password'];
            $user->requireReset = false;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Password updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => collect($e->errors())->mapWithKeys(fn ($messages, $field) => [$field === 'password' ? 'new_password' : $field => $messages])->all()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating password: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update password'], 500);
        }
    }

    /**
     * Get the user's real IP address, handling proxies and load balancers.
     */
    private function getRealIpAddress(Request $request)
    {
        // Check for shared internet/proxy
        if (!empty($request->server('HTTP_CLIENT_IP'))) {
            $ip = $request->server('HTTP_CLIENT_IP');
        }
        // Check for remote address (could be proxy)
        elseif (!empty($request->server('HTTP_X_FORWARDED_FOR'))) {
            $ip = $request->server('HTTP_X_FORWARDED_FOR');
        }
        // Check for remote address from share via proxy
        elseif (!empty($request->server('HTTP_X_FORWARDED'))) {
            $ip = $request->server('HTTP_X_FORWARDED');
        }
        // Check for remote address from remote address
        elseif (!empty($request->server('HTTP_X_CLUSTER_CLIENT_IP'))) {
            $ip = $request->server('HTTP_X_CLUSTER_CLIENT_IP');
        } elseif (!empty($request->server('HTTP_FORWARDED_FOR'))) {
            $ip = $request->server('HTTP_FORWARDED_FOR');
        } elseif (!empty($request->server('HTTP_FORWARDED'))) {
            $ip = $request->server('HTTP_FORWARDED');
        }
        // Fall back to default
        else {
            $ip = $request->ip();
        }

        // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Validate the IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // Check if it's a public IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // If we only have a local/private IP, try to get the real public IP
        $publicIp = $this->getPublicIP();
        if ($publicIp) {
            return $publicIp;
        }

        // If all else fails, return the default IP
        return $request->ip();
    }

    /**
     * Get the real public IP address from external services.
     */
    private function getPublicIP()
    {
        try {
            // Try multiple services to get public IP
            $services = [
                'https://api.ipify.org',
                'https://ipinfo.io/ip',
                'https://icanhazip.com',
                'https://ident.me'
            ];

            foreach ($services as $service) {
                try {
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 3,
                            'method' => 'GET'
                        ]
                    ]);

                    $ip = @file_get_contents($service, false, $context);
                    if ($ip) {
                        $ip = trim($ip);
                        // Validate the IP
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            //Log::info("Got real public IP from {$service}: {$ip}");
                            return $ip;
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug("Failed to get IP from {$service}: " . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            Log::debug('Failed to get real public IP: ' . $e->getMessage());
        }

        return null;
    }
}
