<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\SubMenu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot()
    {
        View::composer('layouts.main', function ($view) {
            $user = Auth::user();
            if (!$user) {
                // Handle the case where no user is logged in
                $view->with('menus', []);
                return;
            }

            $userId = $user->id;
            $userRole = DB::table('cb_role_user')->where('user_id', $userId)->value('role_id');

            // Get allowed submenus for this role
            $allowedSubmenus = DB::table('cb_sub_modules')
                ->join('cb_sub_module_permissions', 'cb_sub_modules.id', '=', 'cb_sub_module_permissions.sub_module_id')
                ->where('cb_sub_module_permissions.role_id', $userRole)
                ->orderBy('cb_sub_modules.order')
                ->get();
            $submenusGrouped = $allowedSubmenus->groupBy('module_id');
            $menus = DB::table('cb_modules')->orderBy('module_order')->get();
            $menuData = [];
            foreach ($menus as $menu) {
                if ($submenusGrouped->has($menu->id)) {
                    $menuData[$menu->id] = [
                        'module_name' => $menu->module_name,
                        'icon' => $menu->icon,
                        'module_url' => $menu->module_url,
                        'submenus' => $submenusGrouped[$menu->id],
                    ];
                }
            }
            $view->with('menus', $menuData);
        });
    }
}
