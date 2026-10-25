<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lets a controller enforce a menu permission on the pages, fragments and downloads behind a
 * menu entry. The AccessChecker middleware only guards the menu page itself (a path that has a
 * row in cb_sub_modules), so the AJAX and export paths that page uses, and every route in the
 * General Ledger block, would otherwise be open to any signed-in user.
 */
trait ChecksMenuAccess
{
    /**
     * Whether the signed-in user's role is linked to a menu entry with this URL, using the same
     * tables as the menu and the AccessChecker (cb_role_user, cb_sub_module_permissions). Give
     * several URLs to allow a user who has any one of them.
     *
     * @param string|array $subModuleUrl for example '/ledger/balances', or a list of them
     */
    protected function menuAllowed(string|array $subModuleUrl): bool {
        $subModuleIds = DB::table('cb_sub_modules')->whereIn('sub_module_url', (array) $subModuleUrl)->pluck('id');
        if ($subModuleIds->isEmpty()) {
            return false;
        }

        $roleIds = DB::table('cb_role_user')->where('user_id', Auth::id())->pluck('role_id');

        return $roleIds->isNotEmpty() && DB::table('cb_sub_module_permissions')->whereIn('sub_module_id', $subModuleIds)->whereIn('role_id', $roleIds)->exists();
    }

    /**
     * The answer when the user is not allowed: a redirect to the dashboard for a page or a
     * download, a short message for a fragment the page loads, or JSON for a data call.
     *
     * @param string $kind page, fragment or json
     */
    protected function denied(string $kind = 'page') {
        $message = 'You do not have permission to access the page.';

        if ($kind === 'json') {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        if ($kind === 'fragment') {
            return response($message, 403);
        }

        return redirect('/dashboard')->with('error', $message);
    }
}
