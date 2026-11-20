<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordSetupLink;
use App\Services\SetupPasswordRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The public page a new system user opens from the emailed link to choose their own password. Anyone can reach it, so every
 * answer here says only what the holder of the link already knows, and the password rules are checked on the server.
 */
class PasswordSetupController extends Controller
{
    /** A new link may not be asked for more often than this (minutes), so a stale link cannot be used to flood a mailbox. */
    private const RESEND_GAP_MINUTES = 2;

    public function __construct(private PasswordSetupLink $links, private SetupPasswordRules $rules)
    {
    }

    public function show(string $token)
    {
        $user = $this->links->find($token);
        if ($problem = $this->linkProblem($user)) {
            return $this->problemPage($problem, $token);
        }

        return view('auth.set-password', [
            'token' => $token,
            'email' => $user->email,
            'name' => $user->name,
            'minLength' => $this->rules->minLength(),
            'maxLength' => SetupPasswordRules::MAX_LENGTH,
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $user = $this->links->find($token);
        if ($problem = $this->linkProblem($user)) {
            return response()->json(['status' => 'error', 'link_problem' => $problem, 'message' => $this->problemText($problem)], 410);
        }

        $password = (string) $request->input('password', '');
        if ($password !== (string) $request->input('password_confirmation', '')) {
            return response()->json(['status' => 'error', 'message' => 'The two passwords do not match.'], 422);
        }
        if ($message = $this->rules->problem($password, $user)) {
            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        try {
            $this->links->complete($user, $password);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'error', 'link_problem' => 'used', 'message' => $e->getMessage()], 410);
        }

        session()->flash('success', 'Your password has been set. You can now sign in.');

        return response()->json(['status' => 'success', 'message' => 'Your password has been set.', 'redirect' => route('login')]);
    }

    /**
     * Send a new link to the address on the account. Only someone holding this link (used up links are refused) can ask,
     * and the answer never shows the address.
     */
    public function resend(string $token)
    {
        $user = $this->links->find($token);
        $problem = $this->linkProblem($user, allowExpired: true);
        if ($problem) {
            return $this->problemPage($problem, $token);
        }

        $sentAt = $user->password_setup_sent_at;
        if ($sentAt && now()->diffInMinutes($sentAt, true) < self::RESEND_GAP_MINUTES && $this->links->stateOf($user) === PasswordSetupLink::WAITING) {
            return view('auth.set-password-problem', ['kind' => 'sent', 'message' => 'A new link was sent a moment ago. Please check your email, including the spam folder.', 'token' => null]);
        }

        $sent = $this->links->send($user);

        return view('auth.set-password-problem', [
            'kind' => $sent ? 'sent' : 'not_sent',
            'message' => $sent
                ? 'A new link has been sent to the email address on your account. It works once and expires in ' . PasswordSetupLink::VALID_HOURS . ' hours. The old link no longer works.'
                : 'We could not send the email just now. Please tell HR so they can send you a new link.',
            'token' => null,
        ]);
    }

    /**
     * Why this link cannot be used, or null when it can: invalid (not a link we made), used, expired or suspended.
     */
    private function linkProblem(?User $user, bool $allowExpired = false): ?string
    {
        if (!$user) {
            return 'invalid';
        }
        $state = $this->links->stateOf($user);
        if ($state === PasswordSetupLink::NONE) {
            return 'used';
        }
        if ($user->block) {
            return 'suspended';
        }
        if ($state === PasswordSetupLink::EXPIRED && !$allowExpired) {
            return 'expired';
        }

        return null;
    }

    private function problemText(string $problem): string
    {
        return match ($problem) {
            'used' => 'This link has already been used. If you have set your password, sign in. If you have forgotten it, use "Forgot password" on the sign-in page.',
            'expired' => 'This link has expired. You can ask for a new one below.',
            'suspended' => 'This account is suspended. Please contact HR.',
            default => 'This link is not valid. Check that you copied the whole address from your email, or ask HR to send you a new link.',
        };
    }

    private function problemPage(string $problem, string $token)
    {
        return response()->view('auth.set-password-problem', [
            'kind' => $problem,
            'message' => $this->problemText($problem),
            'token' => $problem === 'expired' ? $token : null,
        ], $problem === 'invalid' ? 404 : 410);
    }
}
