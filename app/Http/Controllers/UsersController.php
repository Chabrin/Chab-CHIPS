<?php

namespace App\Http\Controllers;

use App\Models\Gender;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\MaritalStatus;
use App\Models\EmploymentTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Services\ErrorDispatcher;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function confirmUsersAjax()
    {
        try {
            $users = DB::table('users as u')
                ->join('employees as e', 'u.employee_id', '=', 'e.id')
                ->leftJoin('role_user as ru', 'u.id', '=', 'ru.user_id')
                ->leftJoin('roles as r', 'ru.role_id', '=', 'r.id')
                ->whereNull('u.confirmed')
                ->select(
                    'u.id',
                    'u.username',
                    'u.email',
                    'u.confirmed',
                    'r.name as role',
                    'e.national_id',
                    'e.first_name',
                    'e.surname'
                )
                ->get()
                ->map(function ($row) {
                    $row->name = trim(($row->first_name ?? '') . ' ' . ($row->surname ?? ''));
                    return $row;
                });

            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error listing confirm users AJAX: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to list users'], 500);
        }
    }


    public function systemUsers()
    {
        try {
            return view('users.systemusers');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading system users: ' . $e->getMessage());
            return back()->with('error', 'Failed to load system users');
        }
    }

    public function systemUsersAjax()
    {
        try {
            $users = DB::table('josya_users as u')
                ->leftJoin('cb_role_user as ru', 'u.id', '=', 'ru.user_id')
                ->leftJoin('cb_roles as r', 'ru.role_id', '=', 'r.id')
                ->select('u.id', 'u.name', 'u.username', 'u.email', 'r.display_name as role_name', 'u.block')
                ->get();

            // Check permissions
            $canUpdate = auth()->user()->hasPermission('update_users');
            $canDelete = auth()->user()->hasPermission('delete_users');

            return response()->json([
                'users' => $users,
                'permissions' => [
                    'canUpdate' => $canUpdate,
                    'canDelete' => $canDelete
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading system users ajax: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load users'], 500);
        }
    }

    public function getUserAjax($id)
    {
        try {
            $user = DB::table('josya_users as u')
                ->leftJoin('cb_role_user as ru', 'u.id', '=', 'ru.user_id')
                ->where('u.id', $id)
                ->select('u.id', 'u.name', 'u.username', 'u.email', 'ru.role_id', 'u.block')
                ->first();

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            return response()->json(['user' => $user]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error getting user ajax: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get user'], 500);
        }
    }

    public function storeSystemUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:100|unique:josya_users,username',
                'email' => 'required|email|max:255|unique:josya_users,email',
                'role_id' => 'required|integer|exists:cb_roles,id',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(12)),
                'block' => 0,
                'sendEmail' => 0,
                'registerDate' => now(),
                'lastvisitDate' => now(),
                'activation' => '',
                'params' => '',
                'lastResetTime' => '0000-00-00 00:00:00',
                'resetCount' => 0,
                'otpKey' => '',
                'otep' => '',
                'requireReset' => 0,
            ]);

            DB::table('cb_role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $validated['role_id'],
            ]);

            return response()->json(['success' => true, 'message' => 'User created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating system user: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to create user'], 500);
        }
    }

    public function updateSystemUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'username' => ['required', 'string', 'max:100', Rule::unique('josya_users', 'username')->ignore($user->id)],
                'email' => ['required', 'email', 'max:255', Rule::unique('josya_users', 'email')->ignore($user->id)],
                'role_id' => 'required|integer|exists:cb_roles,id',
            ]);

            $user->name = $validated['name'];
            $user->username = $validated['username'];
            $user->email = $validated['email'];
            $user->save();



            DB::table('cb_role_user')->where('user_id', $id)->delete();
            DB::table('cb_role_user')->insert([
                'user_id' => $id,
                'role_id' => $validated['role_id'],
            ]);

            return response()->json(['success' => true, 'message' => 'User updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating system user: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update user'], 500);
        }
    }

    public function suspendUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['block' => 1]);
            return response()->json(['success' => true, 'message' => 'User suspended successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error suspending user: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to suspend user'], 500);
        }
    }

    public function activateUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['block' => 0]);
            return response()->json(['success' => true, 'message' => 'User activated successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error activating user: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to activate user'], 500);
        }
    }
}
