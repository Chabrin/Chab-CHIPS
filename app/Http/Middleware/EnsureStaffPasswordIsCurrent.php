<?php

namespace App\Http\Middleware;

use App\Services\StaffPasswordPolicy;
use Closure;
use Illuminate\Http\Request;

class EnsureStaffPasswordIsCurrent
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && app(StaffPasswordPolicy::class)->requiresChange($request->user())
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
