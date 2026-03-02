<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Submodule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use App\Services\ErrorDispatcher;

class RolesController extends Controller
{
    /**
     * Show the view for adding permissions.
     */
    public function addPermissionView()
    {
        // You may want to pass modules to the view for dropdown selection
        $modules = Module::all();
        return view('roles.addpermission', compact('modules'));
    }
    /**
     * Add permissions (CRUD) for a module via AJAX.
     */
    public function addPermission(Request $request)
    {
        try {
            $request->validate([
                'module_id' => 'required|integer|exists:cb_modules,id',
                'base_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:255',
            ]);
            $crud = ['create', 'read', 'update', 'delete'];
            $created = [];
            foreach ($crud as $action) {
                $permName = $action . '_' . $request->input('base_name');
                $exists = Permission::where('module_id', $request->input('module_id'))
                    ->where('name', $permName)
                    ->exists();
                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'error' => "Permission '{$permName}' already exists for this module."
                    ], 409);
                }
                $perm = new Permission();
                $perm->module_id = $request->input('module_id');
                $perm->name = $permName;
                $perm->display_name = $permName;
                $perm->save();
                $created[] = $perm->name;
            }
            return response()->json(['success' => true, 'created' => $created]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding permissions: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to add permissions.'], 500);
        }
    }
    /**
     * Add a new role via AJAX.
     */
    public function addRole(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:cb_roles,name',
            ]);
            $request->validate([
                'name' => 'required|string|max:255|unique:cb_roles,name',
                'description' => 'nullable|string|max:255',
            ]);
            $role = new Role();
            $role->name = $request->input('name');
            $role->display_name = $request->input('name');
            $role->description = $request->input('description');
            $role->save();
            return response()->json(['success' => true, 'role' => $role]);
        } catch (ValidationException $e) {
            Log::error('Error adding role: ' . $e->getMessage());
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding role: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to add role.'], 500);
        }
    }
    /**
     * Save modules access for a role (AJAX).
     */
    public function saveModulesAccess(Request $request, $id)
    {
        try {
            $roleId = Crypt::decrypt($id);
            $role = Role::findOrFail($roleId);
            $selectedSubmodules = $request->input('submodules', []);
            // Use Eloquent sync for module_child permissions
            $role->moduleChildren()->sync($selectedSubmodules);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::info('Error saving modules access: ' . $e);
            Log::error('Error saving modules access: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update modules access.'], 500);
        }
    }

    /**
     * Save permissions access for a role (AJAX).
     */
    public function savePermissionsAccess(Request $request, $id)
    {
        try {
            $roleId = Crypt::decrypt($id);
            $role = Role::findOrFail($roleId);
            $selectedPermissions = $request->input('permissions', []);
            // Sync permissions for the role
            $role->permissions()->sync($selectedPermissions);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::info('Error saving permissions access: ' . $e);
            Log::error('Error saving permissions access: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update permissions access.'], 500);
        }
    }

    public function modulesAccess($encryptedId)
    {
        $roleId = Crypt::decrypt($encryptedId);
        $role = DB::table('cb_roles')->where('id', $roleId)->first();
        $modules = Module::with(['submodules'])->get();
        $assigned = DB::table('cb_sub_module_permissions')
            ->where('role_id', $roleId)
            ->pluck('sub_module_id')->toArray();
        return view('roles.modulesaccess', compact('role', 'modules', 'assigned'));
    }

    public function permissionsAccess($encryptedId)
    {
        $roleId = Crypt::decrypt($encryptedId);
        $role = DB::table('cb_roles')->where('id', $roleId)->first();
        $permissions = DB::table('cb_permissions')->get();
        $assigned = DB::table('cb_permission_role')
            ->where('role_id', $roleId)
            ->pluck('permission_id')->toArray();
        $modules = Module::all();
        return view('roles.permissionsaccess', compact('role', 'permissions', 'assigned', 'modules'));
    }

    public function listRoles()
    {
        $roles = DB::table('cb_roles')->get();
        return view('roles.listroles', compact('roles'));
    }

    public function getRoleModules($roleId)
    {
        $modules = Module::with(['submodules'])->get();
        $assigned = DB::table('cb_sub_module_permissions')
            ->where('role_id', $roleId)
            ->pluck('sub_module_id')->toArray();
        return response()->json(['modules' => $modules, 'assigned' => $assigned]);
    }

    public function getRolePermissions($roleId)
    {
        $permissions = DB::table('um_permissions')->get();
        $assigned = DB::table('um_permission_role')
            ->where('role_id', $roleId)
            ->pluck('permission_id')->toArray();
        return response()->json(['permissions' => $permissions, 'assigned' => $assigned]);
    }
}
