<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\ErrorDispatcher;
use App\Models\User;
use App\Models\UserStaffLink;
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
                ->leftJoin('sys_user_staff_links as usl', 'u.id', '=', 'usl.user_id')
                ->leftJoin('sys_staff as s', 'usl.staff_id', '=', 's.id')
                ->select('u.id', 'u.name', 'u.username', 'u.email', 'r.display_name as role_name', 'u.block',
                         's.id as linked_staff_id', 's.full_names as linked_staff_name', 's.staff_number as linked_staff_number')
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
                ->leftJoin('sys_user_staff_links as usl', 'u.id', '=', 'usl.user_id')
                ->leftJoin('sys_staff as s', 'usl.staff_id', '=', 's.id')
                ->where('u.id', $id)
                ->select('u.id', 'u.name', 'u.username', 'u.email', 'ru.role_id', 'u.block',
                         's.id as linked_staff_id', 's.full_names as linked_staff_name', 's.staff_number as linked_staff_number')
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
                'password' => 'required|string|min:8|max:255',
                'role_id' => 'required|integer|exists:cb_roles,id',
                'staff_id' => 'nullable|integer|exists:sys_staff,id|unique:sys_user_staff_links,staff_id',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'block' => 0,
                'sendEmail' => 0,
                'registerDate' => now(),
                'lastvisitDate' => now(),
                'activation' => '',
                'params' => '',
                // 'lastResetTime' => '',
                'resetCount' => 0,
                'otpKey' => '',
                'otep' => '',
                'requireReset' => 0,
            ]);

            DB::table('cb_role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $validated['role_id'],
            ]);

            if (!empty($validated['staff_id'])) {
                UserStaffLink::create([
                    'user_id' => $user->id,
                    'staff_id' => $validated['staff_id'],
                ]);
                $user->staff_id = $validated['staff_id'];
                $user->save();
            }

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
                'password' => 'nullable|string|min:8|max:255',
                'role_id' => 'required|integer|exists:cb_roles,id',
                'staff_id' => [
                    'nullable', 'integer', 'exists:sys_staff,id',
                    Rule::unique('sys_user_staff_links', 'staff_id')->where(fn($q) => $q->where('user_id', '!=', $id)),
                ],
            ]);

            $user->name = $validated['name'];
            $user->username = $validated['username'];
            $user->email = $validated['email'];
            if (!empty($validated['password'])) {
                $user->password = $validated['password'];
                $user->requireReset = true;
            }
            $user->save();

            DB::table('cb_role_user')->where('user_id', $id)->delete();
            DB::table('cb_role_user')->insert([
                'user_id' => $id,
                'role_id' => $validated['role_id'],
            ]);

            if (!empty($validated['staff_id'])) {
                UserStaffLink::updateOrCreate(
                    ['user_id' => $id],
                    ['staff_id' => $validated['staff_id']]
                );
                $user->staff_id = $validated['staff_id'];
            } else {
                UserStaffLink::where('user_id', $id)->delete();
                $user->staff_id = null;
            }
            $user->save();

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

    public function showUserDetails($id)
    {
        try {
            $user = DB::table('josya_users as u')
                ->leftJoin('cb_role_user as ru', 'u.id', '=', 'ru.user_id')
                ->leftJoin('cb_roles as r', 'ru.role_id', '=', 'r.id')
                ->leftJoin('sys_user_staff_links as usl', 'u.id', '=', 'usl.user_id')
                ->where('u.id', $id)
                ->select('u.id', 'u.name', 'u.username', 'u.email', 'r.display_name as role_name', 'u.block',
                         'usl.staff_id')
                ->first();

            if (!$user) {
                return redirect()->route('system.users')->with('error', 'User not found');
            }

            $staff = null;

            if ($user->staff_id) {
                $staff = DB::table('sys_staff as s')
                    ->leftJoin('sys_set_staff_positions as p', 's.position_id', '=', 'p.id')
                    ->leftJoin('sys_set_departments as d', 's.department_id', '=', 'd.id')
                    ->leftJoin('sys_set_zones as z', 's.zone_id', '=', 'z.id')
                    ->where('s.id', $user->staff_id)
                    ->select(
                        's.id', 's.full_names', 's.staff_number', 's.current_status_id', 's.mobile_number',
                        's.email_address', 's.national_id', 's.hire_date',
                        'p.position_name', 'd.department_name', 'z.zone_name'
                    )
                    ->first();

                if ($staff) {
                    $staff->status_label = \App\Models\Staff::STATUSES[$staff->current_status_id] ?? null;
                    $staff->hire_date = $staff->hire_date ? \Carbon\Carbon::parse($staff->hire_date)->format('d M Y') : null;
                }
            }

            return view('users.details', compact('user', 'staff'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user details: ' . $e->getMessage());
            return redirect()->route('system.users')->with('error', 'Failed to load user details');
        }
    }

    public function staffSearch(Request $request)
    {
        try {
            $query = trim($request->input('q', ''));

            $lower = strtolower($query);
            $staff = DB::table('sys_staff')
                ->where(function ($q) use ($lower) {
                    $q->whereRaw('LOWER(full_names) LIKE ?', ['%' . $lower . '%'])
                      ->orWhereRaw('LOWER(staff_number) LIKE ?', ['%' . $lower . '%']);
                })
                ->select('id', 'full_names', 'staff_number')
                ->orderBy('full_names')
                ->limit(20)
                ->get();

            return response()->json($staff);
        } catch (\Exception $e) {
            Log::error('Error searching staff: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to search staff'], 500);
        }
    }

    public function checkEmailExists(Request $request)
    {
        $email = trim($request->input('email', ''));
        $exists = $email !== '' && DB::table('josya_users')->where('email', $email)->exists();
        return response()->json(['exists' => $exists]);
    }
}
