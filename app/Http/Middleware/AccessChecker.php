<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AccessChecker
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            abort(403, 'Unauthorized: User not authenticated.');
        }

        // Get user's role
        $user = Auth::user();
        //Log::info("User ID: {$user->id}, Email: {$user->email}, Role Companies: " . json_encode($user->role_companies));

        $roleId = DB::table('cb_role_user')
            ->where('user_id', $user->id)
            ->value('role_id') ?? null;

        if (!$roleId) {
            abort(403, 'Unauthorized: No role assigned.');
        }

        // Get the requested path, e.g., "members/active"
        $path = $request->path();
        //append a slash / ate the start of the path
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        //Log::info("Requested path: {$path}");

        // Check if this path exists in sub_menu
        $submenu = DB::table('cb_sub_modules')
            ->where('sub_module_url', $path)
            ->first();

        // If it's not a defined sub_menu, allow
        if (!$submenu) {
            return $next($request);
        }

        // Check permission for this role
        $hasPermission = DB::table('cb_sub_module_permissions')
            ->where('role_id', $roleId)
            ->where('sub_module_id', $submenu->id)
            ->exists();

        if (!$hasPermission) {
            return redirect('/dashboard')->with('error', 'You do not have permission to access the page.');
            //Log::info("User ID {$user->id} attempted to access a submenu without permission: {$path}");
            abort(403, 'Access denied: You do not have permission to access this page.');
        }
        return $next($request);
    }
}
