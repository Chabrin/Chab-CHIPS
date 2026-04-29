<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlAccClass;
use App\Models\GlAccGroup;
use App\Models\GlAccountCategory;
use App\Models\ChartAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Services\ErrorDispatcher;

class LedgerController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // PAGE VIEWS
    // ═══════════════════════════════════════════════════════

    public function classesIndex()
    {
        return view('ledger.classes');
    }

    public function groupsIndex()
    {
        return view('ledger.groups');
    }

    public function categoriesIndex()
    {
        return view('ledger.categories');
    }

    public function accountsIndex()
    {
        $user = Auth::user();
        return view('ledger.accounts', [
            'canReadClasses'     => $user->isAbleTo('read_gl_classes'),
            'canReadGroups'      => $user->isAbleTo('read_gl_groups'),
            'canReadCategories'  => $user->isAbleTo('read_gl_categories'),
            'canReadAccounts'    => $user->isAbleTo('read_gl_accounts'),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // DROPDOWN HELPERS
    // ═══════════════════════════════════════════════════════

    public function getClassesDropdown()
    {
        try {
            $classes = GlAccClass::orderBy('name')->get(['id', 'name']);
            return response()->json($classes);
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }

    public function getGroupsDropdown(Request $request)
    {
        try {
            $query = GlAccGroup::orderBy('group_name');
            if ($request->filled('class_id')) {
                $query->where('class_id', $request->class_id);
            }
            return response()->json($query->get(['id', 'group_name as name', 'class_id']));
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }

    public function getCategoriesDropdown(Request $request)
    {
        try {
            $query = GlAccountCategory::orderBy('category_name');
            if ($request->filled('group_id')) {
                $query->where('account_group_id', $request->group_id);
            }
            return response()->json($query->get(['id', 'category_name as name', 'account_group_id']));
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // GL CLASSES
    // ═══════════════════════════════════════════════════════

    public function getClasses()
    {
        try {
            $canUpdate = Auth::user()->isAbleTo('update_gl_classes');
            $canDelete = Auth::user()->isAbleTo('delete_gl_classes');
            $classes = GlAccClass::orderBy('name')->get()->map(function ($c) use ($canUpdate, $canDelete) {
                return [
                    'id'               => $c->id,
                    'encrypted_id'     => Crypt::encrypt($c->id),
                    'name'             => $c->name ?? '-',
                    'balance_nature_id'=> $c->balance_nature_id ?? '-',
                    'can_update'       => $canUpdate,
                    'can_delete'       => $canDelete,
                ];
            });
            return response()->json(['data' => $classes]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL classes: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load classes'], 500);
        }
    }

    public function storeClass(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'              => ['required', 'string', 'max:255', Rule::unique('sys_gl_acc_classes', 'name')],
                'balance_nature_id' => ['required', Rule::in(['Debit', 'Credit'])],
            ], ['name.unique' => 'A class with this name already exists.']);

            $validated['date_time'] = now();
            GlAccClass::create($validated);

            return response()->json(['success' => true, 'message' => 'Class created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating GL class: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create class'], 500);
        }
    }

    public function updateClass(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $class = GlAccClass::findOrFail($id);

            $validated = $request->validate([
                'name'              => ['required', 'string', 'max:255', Rule::unique('sys_gl_acc_classes', 'name')->ignore($id)],
                'balance_nature_id' => ['required', Rule::in(['Debit', 'Credit'])],
            ], ['name.unique' => 'A class with this name already exists.']);

            $class->update($validated);

            return response()->json(['success' => true, 'message' => 'Class updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating GL class: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update class'], 500);
        }
    }

    public function deleteClass($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $class = GlAccClass::findOrFail($id);

            if ($class->groups()->exists()) {
                return response()->json(['success' => false, 'message' => 'Cannot delete: class has groups assigned to it'], 422);
            }

            $class->delete();
            return response()->json(['success' => true, 'message' => 'Class deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting GL class: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete class'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // GL GROUPS
    // ═══════════════════════════════════════════════════════

    public function getGroups()
    {
        try {
            $canUpdate = Auth::user()->isAbleTo('update_gl_groups');
            $canDelete = Auth::user()->isAbleTo('delete_gl_groups');
            $groups = GlAccGroup::with('glClass:id,name')->orderBy('group_name')->get()->map(function ($g) use ($canUpdate, $canDelete) {
                return [
                    'id'           => $g->id,
                    'encrypted_id' => Crypt::encrypt($g->id),
                    'group_name'   => $g->group_name ?? '-',
                    'class_id'     => $g->class_id,
                    'class_name'   => $g->glClass->name ?? '-',
                    'can_update'   => $canUpdate,
                    'can_delete'   => $canDelete,
                ];
            });
            return response()->json(['data' => $groups]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL groups: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load groups'], 500);
        }
    }

    public function storeGroup(Request $request)
    {
        try {
            $validated = $request->validate([
                'group_name' => ['required', 'string', 'max:255', Rule::unique('sys_gl_acc_groups', 'group_name')],
                'class_id'   => ['required', 'integer', 'exists:sys_gl_acc_classes,id'],
            ], ['group_name.unique' => 'A group with this name already exists.']);

            $validated['date_time'] = now();
            GlAccGroup::create($validated);

            return response()->json(['success' => true, 'message' => 'Group created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating GL group: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create group'], 500);
        }
    }

    public function updateGroup(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $group = GlAccGroup::findOrFail($id);

            $validated = $request->validate([
                'group_name' => ['required', 'string', 'max:255', Rule::unique('sys_gl_acc_groups', 'group_name')->ignore($id)],
                'class_id'   => ['required', 'integer', 'exists:sys_gl_acc_classes,id'],
            ], ['group_name.unique' => 'A group with this name already exists.']);

            $group->update($validated);

            return response()->json(['success' => true, 'message' => 'Group updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating GL group: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update group'], 500);
        }
    }

    public function deleteGroup($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $group = GlAccGroup::findOrFail($id);

            if ($group->categories()->exists()) {
                return response()->json(['success' => false, 'message' => 'Cannot delete: group has categories assigned to it'], 422);
            }

            $group->delete();
            return response()->json(['success' => true, 'message' => 'Group deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting GL group: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete group'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // GL CATEGORIES
    // ═══════════════════════════════════════════════════════

    public function getCategories()
    {
        try {
            $canUpdate = Auth::user()->isAbleTo('update_gl_categories');
            $canDelete = Auth::user()->isAbleTo('delete_gl_categories');
            $cats = GlAccountCategory::with(['group:id,group_name,class_id', 'group.glClass:id,name'])
                ->orderBy('category_name')
                ->get()
                ->map(function ($c) use ($canUpdate, $canDelete) {
                    return [
                        'id'               => $c->id,
                        'encrypted_id'     => Crypt::encrypt($c->id),
                        'category_name'    => $c->category_name ?? '-',
                        'account_group_id' => $c->account_group_id,
                        'group_name'       => $c->group->group_name ?? '-',
                        'class_id'         => $c->group->class_id ?? null,
                        'class_name'       => $c->group->glClass->name ?? '-',
                        'can_update'       => $canUpdate,
                        'can_delete'       => $canDelete,
                    ];
                });
            return response()->json(['data' => $cats]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL categories: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load categories'], 500);
        }
    }

    public function storeCategory(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_name'    => ['required', 'string', 'max:255', Rule::unique('sys_gl_accounts_categories', 'category_name')],
                'account_group_id' => ['required', 'integer', 'exists:sys_gl_acc_groups,id'],
            ], ['category_name.unique' => 'A category with this name already exists.']);

            $validated['date_time'] = now();
            GlAccountCategory::create($validated);

            return response()->json(['success' => true, 'message' => 'Category created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating GL category: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create category'], 500);
        }
    }

    public function updateCategory(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $cat = GlAccountCategory::findOrFail($id);

            $validated = $request->validate([
                'category_name'    => ['required', 'string', 'max:255', Rule::unique('sys_gl_accounts_categories', 'category_name')->ignore($id)],
                'account_group_id' => ['required', 'integer', 'exists:sys_gl_acc_groups,id'],
            ], ['category_name.unique' => 'A category with this name already exists.']);

            $cat->update($validated);

            return response()->json(['success' => true, 'message' => 'Category updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating GL category: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update category'], 500);
        }
    }

    public function deleteCategory($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $cat = GlAccountCategory::findOrFail($id);

            if ($cat->accounts()->exists()) {
                return response()->json(['success' => false, 'message' => 'Cannot delete: category has accounts assigned to it'], 422);
            }

            $cat->delete();
            return response()->json(['success' => true, 'message' => 'Category deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting GL category: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete category'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // GL ACCOUNTS
    // ═══════════════════════════════════════════════════════

    public function getAccounts()
    {
        try {
            $canUpdate = Auth::user()->isAbleTo('update_gl_accounts');
            $canDelete = Auth::user()->isAbleTo('delete_gl_accounts');
            $accounts = ChartAccount::with([
                'category:id,category_name',
                'glGroup:id,group_name',
                'glClass:id,name',
            ])
            ->orderBy('account_code')
            ->get()
            ->map(function ($a) use ($canUpdate, $canDelete) {
                return [
                    'id'               => $a->id,
                    'encrypted_id'     => Crypt::encrypt($a->id),
                    'account_code'     => $a->account_code ?? '-',
                    'account_name'     => $a->account_name ?? '-',
                    'category_id'      => $a->category_id,
                    'category_name'    => $a->category->category_name ?? '-',
                    'account_group_id' => $a->account_group_id,
                    'group_name'       => $a->glGroup->group_name ?? '-',
                    'class_id'         => $a->class_id,
                    'class_name'       => $a->glClass->name ?? '-',
                    'normally'         => $a->normally ?? '-',
                    'status'           => $a->current_status_id == 1 ? 'Active' : 'Inactive',
                    'current_status_id'=> $a->current_status_id,
                    'can_update'       => $canUpdate,
                    'can_delete'       => $canDelete,
                ];
            });
            return response()->json(['data' => $accounts]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL accounts: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load accounts'], 500);
        }
    }

    public function storeAccount(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_name'  => ['required', 'string', 'max:255'],
                'account_code'  => ['required', 'string', 'max:50', Rule::unique('sys_gl_chart_accounts_master', 'account_code')],
                'class_id'      => ['required', 'integer', 'exists:sys_gl_acc_classes,id'],
                'account_group_id' => ['required', 'integer', 'exists:sys_gl_acc_groups,id'],
                'category_id'   => ['required', 'integer', 'exists:sys_gl_accounts_categories,id'],
                'normally'      => ['required', Rule::in(['Debit', 'Credit'])],
                'current_status_id' => ['required', 'integer'],
            ], ['account_code.unique' => 'This account code already exists.']);

            $validated['date_time'] = now();
            ChartAccount::create($validated);

            return response()->json(['success' => true, 'message' => 'Account created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating GL account: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create account'], 500);
        }
    }

    public function updateAccount(Request $request, $encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $account = ChartAccount::findOrFail($id);

            $validated = $request->validate([
                'account_name'  => ['required', 'string', 'max:255'],
                'account_code'  => ['required', 'string', 'max:50', Rule::unique('sys_gl_chart_accounts_master', 'account_code')->ignore($id)],
                'class_id'      => ['required', 'integer', 'exists:sys_gl_acc_classes,id'],
                'account_group_id' => ['required', 'integer', 'exists:sys_gl_acc_groups,id'],
                'category_id'   => ['required', 'integer', 'exists:sys_gl_accounts_categories,id'],
                'normally'      => ['required', Rule::in(['Debit', 'Credit'])],
                'current_status_id' => ['required', 'integer'],
            ], ['account_code.unique' => 'This account code already exists.']);

            $account->update($validated);

            return response()->json(['success' => true, 'message' => 'Account updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating GL account: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update account'], 500);
        }
    }

    public function deleteAccount($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $account = ChartAccount::findOrFail($id);
            $account->delete();

            return response()->json(['success' => true, 'message' => 'Account deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting GL account: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete account'], 500);
        }
    }
}
