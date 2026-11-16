<?php

namespace App\Http\Middleware;

use App\Services\StaffPasswordPolicy;
use Closure;
use Illuminate\Http\Request;

class EnsureStaffPasswordIsCurrent
{
    protected StaffPasswordPolicy $policy;

    public function __construct(StaffPasswordPolicy $policy) {
        $this->policy = $policy;
    }

    /**
     * Send staff with an expired or reset-required password to the security tab until they change it.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $this->policy->requiresChange($request->user())
            && !$request->routeIs('staff.password.*', 'user.profile', 'user.password.update', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your password has expired or must be changed. Please set a new password.',
                    'redirect' => route('user.profile', ['tab' => 'security']),
                ], 403);
            }
            return redirect()->route('user.profile', ['tab' => 'security']);
        }

        return $next($request);
    }
}
