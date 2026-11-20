<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot()
    {
        View::composer('layouts.main', function ($view) {
            $user = Auth::user();
            if (!$user) {
                $view->with('menus', []);
                $view->with('superModuleMenus', []);
                $view->with('forcePasswordChange', false);
                return;
            }

            $userId = $user->id;
            $userRole = DB::table('cb_role_user')->where('user_id', $userId)->value('role_id');

            // Get allowed submenus for this role
            $allowedSubmenus = $this->allowedSubmenus($userRole);
            $submenusGrouped = $allowedSubmenus->groupBy('module_id');
            $modules = DB::table('cb_modules')->orderBy('module_order')->get();

            // Tenant onboarding uses the same registered module grants as the rest of the app.
            $allowedSubmenus = $allowedSubmenus->filter(function ($entry) use ($user) {
                return match ($entry->sub_module_url) {
                    \App\Services\TenantReviewAccess::REVIEWS => \App\Services\TenantReviewAccess::allows($user, 'view_tenant_onboarding'),
                    \App\Services\TenantReviewAccess::SETUP => app(\App\Services\TenantReviewWorkflowService::class)->canManage($user),
                    \App\Services\TenantReviewAccess::ACCOUNTS => app(\App\Services\TenantReviewService::class)->canReconcile($user),
                    default => true,
                };
            });
            $submenusGrouped = $allowedSubmenus->groupBy('module_id');
            // Build menu data for each module the user has access to
            $menuData = [];
            foreach ($modules as $menu) {
                if ($submenusGrouped->has($menu->id)) {
                    $menuData[$menu->id] = [
                        'module_name' => $menu->module_name,
                        'icon' => $menu->icon,
                        'module_url' => $menu->module_url,
                        'super_module_id' => $menu->super_module_id ?? null,
                        'submenus' => $submenusGrouped[$menu->id],
                    ];
                }
            }

            // Group into super modules
            $superModules = DB::table('cb_super_modules')->orderBy('order')->get();
            $superModuleMenus = [];

            foreach ($superModules as $sm) {
                // Collect modules that belong to this super module AND have accessible submenus
                $smModules = [];
                foreach ($menuData as $moduleId => $mod) {
                    if (isset($mod['super_module_id']) && (int) $mod['super_module_id'] === (int) $sm->id) {
                        $smModules[$moduleId] = $mod;
                    }
                }
                // Only include super module if it has at least one accessible module
                if (!empty($smModules)) {
                    $superModuleMenus[$sm->id] = [
                        'name' => $sm->name,
                        'icon' => $sm->icon,
                        'modules' => $smModules,
                    ];
                }
            }

            // Collect ungrouped modules (no super_module_id)
            $ungroupedMenus = [];
            foreach ($menuData as $moduleId => $mod) {
                if (empty($mod['super_module_id'])) {
                    $ungroupedMenus[$moduleId] = $mod;
                }
            }

            $view->with('menus', $ungroupedMenus);
            $view->with('superModuleMenus', $superModuleMenus);
            $view->with('forcePasswordChange', (bool) ($user->requireReset ?? false));
        });
    }

    /**
     * The menu entries a role may open, each once, in menu order. Only the entry's own columns are read, so a
     * role granted the same entry twice does not show it twice and the id is the entry's, not the grant's.
     */
    private function allowedSubmenus($roleId): Collection
    {
        return DB::table('cb_sub_modules')
            ->join('cb_sub_module_permissions', 'cb_sub_modules.id', '=', 'cb_sub_module_permissions.sub_module_id')
            ->where('cb_sub_module_permissions.role_id', $roleId)
            ->select('cb_sub_modules.*')
            ->orderBy('cb_sub_modules.order')
            ->get()
            ->unique('id')
            ->values();
    }
}
