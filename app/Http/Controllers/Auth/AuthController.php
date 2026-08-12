<?php

namespace App\Http\Controllers\Auth;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\PasswordResetJob;
use App\Jobs\AccountActivationJob;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Show the forgot password form
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request
     */
    public function forgotPassword(Request $request)
    {
        try {
            // Validate email
            $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please enter a valid email address'
                ]);
            }

            $email = $request->input('email');

            // Check if user exists
            $user = User::where('email', $email)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No account found with this email address'
                ]);
            }

            // Check if user is active
            if ($user->block == 1 || $user->activation != 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is not active. Please contact administrator'
                ]);
            }

            // Generate reset token
            $token = Str::random(64);

            // Get password reset link validity from system controls (in hours)
            $resetValidityHours = (int)(DB::table('cb_system_configs')
                ->where('setting', 'password_reset_link_validity')
                ->value('value') ?? 1); // Default to 1 hour if not set

            // Store reset token in josya_users table
            $user->reset_token = Hash::make($token);
            $user->reset_token_expires_at = Carbon::now()->addHours($resetValidityHours);
            $user->save();

            // Dispatch password reset email job
            $resetDetailsArray = [
                'username' => $user->name ?? $user->username ?? $user->email,
                'email' => $user->email,
                'token' => $token
            ];

            PasswordResetJob::dispatch($resetDetailsArray);

            Log::info('Password reset requested', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent to your email'
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in forgot password: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to process request. Please try again later'
            ]);
        }
    }

    /**
     * Show password reset form
     */
    public function showResetPasswordForm(Request $request, $token)
    {
        try {
            Log::info('Password reset form requested', [
                'token' => substr($token, 0, 10) . '...', // Log partial token for debugging
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Validate token parameter
            if (empty($token)) {
                Log::warning('Password reset form accessed without token', [
                    'ip' => $request->ip()
                ]);
                return view('auth.reset-password-error', [
                    'errorTitle' => 'Invalid Reset Link',
                    'errorMessage' => 'The reset link is missing required information. Please request a new password reset.'
                ]);
            }

            // Find users with valid reset tokens
            $users = User::whereNotNull('reset_token')
                ->whereNotNull('reset_token_expires_at')
                ->get();

            Log::info('Found users with reset tokens', ['count' => $users->count()]);

            if ($users->isEmpty()) {
                Log::warning('No password reset requests found');
                return view('auth.reset-password-error', [
                    'errorTitle' => 'No Reset Request Found',
                    'errorMessage' => 'No password reset requests found in the system. Please request a new password reset to continue.'
                ]);
            }

            $validUser = null;
            $expiredUser = null;
            $tokenFound = false;

            foreach ($users as $user) {
                // Check if this user's token matches
                if (Hash::check($token, $user->reset_token)) {
                    $tokenFound = true;
                    Log::info('Matching token found', [
                        'email' => $user->email,
                        'expires_at' => $user->reset_token_expires_at
                    ]);

                    Log::info('Token expiry check', [
                        'expires_at' => $user->reset_token_expires_at->toDateTimeString(),
                        'current_time' => Carbon::now()->toDateTimeString(),
                        'is_valid' => Carbon::now()->lessThanOrEqualTo($user->reset_token_expires_at)
                    ]);

                    if (Carbon::now()->lessThanOrEqualTo($user->reset_token_expires_at)) {
                        $validUser = $user;
                        break;
                    } else {
                        $expiredUser = $user;
                    }
                }
            }

            if (!$tokenFound) {
                Log::warning('Password reset token not found', [
                    'token_prefix' => substr($token, 0, 10) . '...',
                    'ip' => $request->ip()
                ]);
                return view('auth.reset-password-error', [
                    'errorTitle' => 'Invalid Reset Token',
                    'errorMessage' => 'The reset token is not valid. Please request a new password reset to continue.'
                ]);
            }

            if (!$validUser && $expiredUser) {
                Log::warning('Password reset token has expired', [
                    'email' => $expiredUser->email,
                    'expires_at' => $expiredUser->reset_token_expires_at,
                    'ip' => $request->ip()
                ]);
                return view('auth.reset-password-error', [
                    'errorTitle' => 'Reset Link Expired',
                    'errorMessage' => 'This reset link has expired. Please request a new password reset.'
                ]);
            }

            if (!$validUser) {
                Log::error('Unexpected case: token found but no valid or expired user', [
                    'token_prefix' => substr($token, 0, 10) . '...',
                    'ip' => $request->ip()
                ]);
                return view('auth.reset-password-error', [
                    'errorTitle' => 'Unable to Process Reset Link',
                    'errorMessage' => 'There was an issue processing your reset link. Please request a new password reset to continue.'
                ]);
            }

            Log::info('Password reset form displayed successfully', [
                'email' => $validUser->email,
                'ip' => $request->ip()
            ]);

            return view('auth.reset-password', [
                'token' => $token,
                'email' => $validUser->email
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in showResetPasswordForm', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'token_prefix' => isset($token) ? substr($token, 0, 10) . '...' : 'null',
                'ip' => $request->ip()
            ]);

            return view('auth.reset-password-error', [
                'errorTitle' => 'System Error',
                'errorMessage' => 'Unable to load password reset form due to a system error. Please try again later or request a new reset link.'
            ]);
        }
    }

    /**
     * Handle password reset
     */
    public function resetPassword(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
                'password_confirmation' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ]);
            }

            $token = $request->input('token');
            $email = $request->input('email');
            $password = $request->input('password');

            // Find user with reset token
            $user = User::where('email', $email)->first();

            if (!$user || !$user->reset_token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid reset token'
                ]);
            }

            // Check if token has expired
            if (Carbon::now()->greaterThan($user->reset_token_expires_at)) {
                // Token has expired, clear it
                $user->reset_token = null;
                $user->reset_token_expires_at = null;
                $user->save();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Reset token has expired. Please request a new password reset'
                ]);
            }

            // Verify the token
            if (!Hash::check($token, $user->reset_token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid reset token'
                ]);
            }

            //Update password and clear reset token
            $user->password = $password;
            $user->reset_token = null;
            $user->reset_token_expires_at = null;
            $user->save();

            // Update password security record (if table exists)
            try {
                DB::table('password_securities')
                    ->updateOrInsert(
                        ['user_id' => $user->id],
                        [
                            'password_changed_at' => Carbon::now(),
                            'password_updated_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ]
                    );
            } catch (\Exception $securityException) {
                // Table may not exist or have different schema, log but continue
                Log::warning('Could not update password_securities table', [
                    'user_id' => $user->id,
                    'error' => $securityException->getMessage()
                ]);
            }

            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successful. You can now login with your new password'
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in reset password: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to reset password. Please try again later'
            ]);
        }
    }

    /**
     * Show account activation form
     */
    // Render activation form (token provided in URL)
    public function showAccountActivationForm(Request $request, $token = null)
    {
        $userEmail = null;

        if ($token) {
            // Find user with this token to show their email
            $candidates = User::whereNotNull('activation_token')->get();
            $user = $candidates->first(function ($u) use ($token) {
                return Hash::check($token, $u->activation_token);
            });

            if ($user) {
                $userEmail = $user->email;
            }
        }

        return view('auth.activate-account', [
            'token' => $token,
            'userEmail' => $userEmail
        ]);
    }

    /**
     * Send account activation email
     */
    public function sendActivationEmail(Request $request)
    {
        try {
            // Validate email
            $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please enter a valid email address'
                ]);
            }

            $email = $request->input('email');

            // Check if user exists
            $user = User::where('email', $email)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No account found with this email address'
                ]);
            }

            // Check if already active
            if ($user->is_active == 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is already active'
                ]);
            }

            // Generate activation token
            $token = Str::random(64);
            $expiresAt = Carbon::now()->addHours(24); // Token expires in 24 hours

            // Store activation token
            $user->activation_token = Hash::make($token);
            $user->activation_token_expires = $expiresAt;
            $user->save();

            // Dispatch activation email job
            AccountActivationJob::dispatch($user, $token);

            Log::info('Account activation email sent', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Account activation link sent to your email'
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in send activation email: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to send activation email. Please try again later'
            ]);
        }
    }

    /**
     * Activate account
     */
    // POST activation: set password and activate account
    public function activateAccount(Request $request, $token)
    {
        try {
            // Validate password with strong policy
            $validator = Validator::make($request->all(), [
                'password' => ['required', 'string', 'min:10', 'confirmed']
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $password = $request->input('password');

            // Get activation link validity from system controls (in hours)
            $activationValidityHours = (int)(DB::table('cb_system_configs')
                ->where('setting', 'account_activation_link_validity')
                ->value('value') ?? 24); // Default to 24 hours if not set

            // Find candidate users with non-expired token
            $candidates = User::whereNotNull('activation_token')
                ->whereNotNull('token_generation_date')
                ->get();

            $user = $candidates->first(function ($u) use ($token, $activationValidityHours) {
                // Check if token matches
                if (!Hash::check($token, $u->activation_token)) {
                    return false;
                }

                // Check if token has expired based on token_generation_date + validity hours
                $tokenGeneratedAt = Carbon::parse($u->token_generation_date);
                $tokenExpiresAt = $tokenGeneratedAt->addHours($activationValidityHours);

                return Carbon::now()->lessThanOrEqualTo($tokenExpiresAt);
            });

            if (!$user) {
                // Check if we can find a user with this token that has expired
                $expiredUser = $candidates->first(function ($u) use ($token, $activationValidityHours) {
                    if (!Hash::check($token, $u->activation_token)) {
                        return false;
                    }

                    $tokenGeneratedAt = Carbon::parse($u->token_generation_date);
                    $tokenExpiresAt = $tokenGeneratedAt->addHours($activationValidityHours);

                    return Carbon::now()->greaterThan($tokenExpiresAt);
                });

                if ($expiredUser) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Activation link has expired. Please contact admin to request a new activation link.'
                    ], 410);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid activation link'
                ], 404);
            }

            // Update password and activation flags
            $user->password = $password;
            $user->user_activated = 1;
            $user->activated_at = Carbon::now();
            $user->is_active = 1; // ensure user can login
            $user->activation_token = null;
            $user->activation_token_expires = null;
            $user->save();

            Log::info('Account activated via web form', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Account activated successfully. You can now login.'
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in activate account: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to activate account. Please try again later'
            ], 500);
        }
    }
}
