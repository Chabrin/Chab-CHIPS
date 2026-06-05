<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlAccClass;
use App\Models\GlAccGroup;
use App\Models\GlAccountCategory;
use App\Models\ChartAccount;
use App\Models\GlAccountSetup;
use App\Models\GlPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Exports\GlTransactionsExport;
use App\Exports\LedgerAccountsExport;
use App\Services\ErrorDispatcher;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

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

    public function exportAccounts()
    {
        try {
            $accounts = ChartAccount::with([
                'category:id,category_name',
                'glGroup:id,group_name',
                'glClass:id,name',
            ])
            ->orderBy('account_code')
            ->get()
            ->map(function ($a) {
                return [
                    'account_code'     => $a->account_code ?? '-',
                    'account_name'     => $a->account_name ?? '-',
                    'class_name'       => $a->glClass->name ?? '-',
                    'group_name'       => $a->glGroup->group_name ?? '-',
                    'category_name'    => $a->category->category_name ?? '-',
                    'normally'         => $a->normally ?? '-',
                    'status'           => $a->current_status_id == 1 ? 'Active' : 'Inactive',
                ];
            })
            ->toArray();

            $filename = 'Ledger_Accounts_' . date('Y-m-d_His') . '.xlsx';
            return Excel::download(new LedgerAccountsExport($accounts), $filename);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting GL accounts: ' . $e->getMessage());
            return back()->with('error', 'Failed to export accounts');
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

    // ═══════════════════════════════════════════════════════
    // GL PERIODS
    // ═══════════════════════════════════════════════════════

    public function periodsIndex()
    {
        return view('ledger.periods');
    }

    public function getPeriods()
    {
        try {
            $canUpdate = Auth::user()->isAbleTo('update_gl_periods');
            $canDelete = Auth::user()->isAbleTo('delete_gl_periods');

            // Resolve user names in one query
            $userNames = \Illuminate\Support\Facades\DB::table('josya_users')
                ->pluck('name', 'id');

            $periods = GlPeriod::orderByDesc('period_start')->get()->map(function ($p) use ($canUpdate, $canDelete, $userNames) {
                return [
                    'encrypted_id'         => Crypt::encrypt($p->id),
                    'period_start'         => $p->period_start?->format('Y-m-d'),
                    'period_end'           => $p->period_end?->format('Y-m-d'),
                    'period_start_display' => $p->period_start?->format('d/m/Y') ?? '-',
                    'period_end_display'   => $p->period_end?->format('d/m/Y') ?? '-',
                    'added_by_name'        => $p->added_by ? ($userNames[$p->added_by] ?? 'User #' . $p->added_by) : '-',
                    'added_on_display'     => $p->added_on?->format('d/m/Y H:i') ?? '-',
                    'can_update'           => $canUpdate,
                    'can_delete'           => $canDelete,
                ];
            });

            return response()->json(['data' => $periods]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL periods: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load periods'], 500);
        }
    }

    public function storePeriod(Request $request)
    {
        try {
            $validated = $request->validate([
                'period_start' => ['required', 'date'],
                'period_end'   => ['required', 'date', 'after:period_start'],
            ]);

            $overlap = $this->findOverlappingPeriod($validated['period_start'], $validated['period_end']);
            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['period_start' => ["This period overlaps with an existing period ({$overlap->period_start->format('d/m/Y')} – {$overlap->period_end->format('d/m/Y')})."]],
                ], 422);
            }

            GlPeriod::create([
                'period_start'      => $validated['period_start'],
                'period_end'        => $validated['period_end'],
                'date_time'         => now(),
                'current_status_id' => 1,
                'added_by'          => Auth::id(),
                'added_on'          => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Period created successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating GL period: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create period'], 500);
        }
    }

    public function updatePeriod(Request $request, $encryptedId)
    {
        try {
            $id     = Crypt::decrypt($encryptedId);
            $period = GlPeriod::findOrFail($id);

            $validated = $request->validate([
                'period_start' => ['required', 'date'],
                'period_end'   => ['required', 'date', 'after:period_start'],
            ]);

            $overlap = $this->findOverlappingPeriod($validated['period_start'], $validated['period_end'], $id);
            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['period_start' => ["This period overlaps with an existing period ({$overlap->period_start->format('d/m/Y')} – {$overlap->period_end->format('d/m/Y')})."]],
                ], 422);
            }

            $period->update([
                'period_start' => $validated['period_start'],
                'period_end'   => $validated['period_end'],
            ]);

            return response()->json(['success' => true, 'message' => 'Period updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating GL period: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update period'], 500);
        }
    }

    private function findOverlappingPeriod(string $start, string $end, ?int $excludeId = null): ?GlPeriod
    {
        // Two ranges overlap when: existing.start <= new.end AND existing.end >= new.start
        return GlPeriod::where('period_start', '<=', $end)
            ->where('period_end', '>=', $start)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    public function deletePeriod($encryptedId)
    {
        try {
            $id     = Crypt::decrypt($encryptedId);
            $period = GlPeriod::findOrFail($id);
            $period->delete();

            return response()->json(['success' => true, 'message' => 'Period deleted successfully']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting GL period: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete period'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // GL TRANSACTIONS
    // ═══════════════════════════════════════════════════════

    public function glTransactionsIndex()
    {
        return view('ledger.transactions');
    }

    public function getGlTransactions(Request $request)
    {
        try {
            $start = $request->input('start');
            $end   = $request->input('end');

            // Default: most recent 10 days that have data
            if (!$start || !$end) {
                $maxDate = DB::table('sys_gl_transactions')->max('journal_date');
                if (!$maxDate) {
                    return response()->json(['data' => [], 'range' => null]);
                }
                $dt    = Carbon::parse($maxDate);
                $end   = $dt->toDateString();
                $start = $dt->copy()->subDays(9)->toDateString();
            }

            // Server-side: reject ranges > 31 days
            $startCarbon = Carbon::parse($start)->startOfDay();
            $endCarbon   = Carbon::parse($end)->endOfDay();

            if ($startCarbon->diffInDays($endCarbon) > 31) {
                return response()->json(['error' => 'Date range cannot exceed 31 days.'], 422);
            }

            $lines = DB::table('sys_gl_transactions_91_repeat as d')
                ->join('sys_gl_transactions as h', 'h.id', '=', 'd.parent_id')
                ->whereBetween('h.journal_date', [$startCarbon, $endCarbon])
                ->select(
                    'd.parent_id',
                    'h.journal_date',
                    'h.reference',
                    'd.account_id',
                    'd.narration',
                    'd.entry_type',
                    'd.amount'
                )
                ->orderBy('d.parent_id')
                ->orderBy('d.id')
                ->get()
                ->map(function ($row) {
                    $isDebit = $row->entry_type === 'Debit';
                    $amount  = abs((float) ($row->amount ?? 0));
                    return [
                        'parent_id' => $row->parent_id,
                        'date'      => $row->journal_date
                                        ? Carbon::parse($row->journal_date)->format('d/m/Y')
                                        : '-',
                        'reference' => $row->reference ?? '-',
                        'account'   => $row->account_id ?? '-',
                        'narration' => $row->narration ?? '-',
                        'debit'     => $isDebit ? number_format($amount, 2) : '',
                        'credit'    => !$isDebit ? number_format($amount, 2) : '',
                    ];
                });

            return response()->json([
                'data'  => $lines,
                'range' => ['start' => $start, 'end' => $end],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading GL transactions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load transactions'], 500);
        }
    }

    public function downloadGlTransactionsExcel(Request $request)
    {
        Log::info('GL Transactions export requested', ['start' => $request->input('start'), 'end' => $request->input('end')]);
        ini_set('memory_limit', '512M');
        set_time_limit(120);
        try {
            $start = $request->input('start');
            $end   = $request->input('end');

            if (!$start || !$end) {
                $maxDate = DB::table('sys_gl_transactions')->max('journal_date');
                if (!$maxDate) {
                    return back()->with('error', 'No transactions found.');
                }
                $dt    = Carbon::parse($maxDate);
                $end   = $dt->toDateString();
                $start = $dt->copy()->subDays(9)->toDateString();
            }

            $startCarbon = Carbon::parse($start)->startOfDay();
            $endCarbon   = Carbon::parse($end)->endOfDay();

            if ($startCarbon->diffInDays($endCarbon) > 31) {
                abort(422, 'Date range cannot exceed 31 days.');
            }

            $rows = DB::table('sys_gl_transactions_91_repeat as d')
                ->join('sys_gl_transactions as h', 'h.id', '=', 'd.parent_id')
                ->whereBetween('h.journal_date', [$startCarbon, $endCarbon])
                ->select('d.parent_id', 'h.journal_date', 'h.reference', 'd.account_id', 'd.narration', 'd.entry_type', 'd.amount')
                ->orderBy('d.parent_id')
                ->orderBy('d.id')
                ->get()
                ->map(function ($row) {
                    $isDebit = $row->entry_type === 'Debit';
                    $amount  = abs((float) ($row->amount ?? 0));
                    return [
                        'parent_id' => $row->parent_id,
                        'date'      => $row->journal_date ? Carbon::parse($row->journal_date)->format('d/m/Y') : '-',
                        'reference' => $row->reference ?? '-',
                        'account'   => $row->account_id ?? '-',
                        'narration' => $row->narration ?? '-',
                        'debit'     => $isDebit ? number_format($amount, 2) : '',
                        'credit'    => !$isDebit ? number_format($amount, 2) : '',
                    ];
                })
                ->toArray();

            $startLabel = Carbon::parse($start)->format('d-m-Y');
            $endLabel   = Carbon::parse($end)->format('d-m-Y');
            $filename   = 'gl-transactions-' . $start . '-to-' . $end . '.xlsx';

            // Force eager generation inside try-catch so any export error is caught here
            $content = Excel::raw(
                new GlTransactionsExport($rows, $startLabel, $endLabel),
                \Maatwebsite\Excel\Excel::XLSX
            );

            return response($content, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length'      => strlen($content),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error exporting GL transactions: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── GL Settings ────────────────────────────────────────────────────────────

    public function glSettingsIndex()
    {
        $setup = GlAccountSetup::first();

        $accountFields = [
            'retained_earnings_id', 'income_summary_account_id', 'receivable_control_id',
            'payable_control_account_id', 'bank_charges_account_id', 'vat_control_account_id',
            'other_income_account_id', 'bank_interest_account_id', 'accrued_payroll_expense_account_id',
            'payroll_expense_account_id', 'net_salaries_account_id', 'other_employee_deductions_id',
            'clients_account_id', 'tenants_account_id', 'revenues_account_id',
            'clients_bank_account_id', 'petty_cash_account_id', 'loans_receivable_account_id',
            'interests_receivable_account_id', 'business_bank_account_id', 'tenants_suspense_account_id',
            'loans_interests_revenue_account_id', 'rent_commission_account_id',
            'returns_payable_account_id', 'clients_advance_loans_payable_id',
            'co_op_bank_id', 'equity_bank_account_id', 'mpesa_account_id',
            'earned_commission_acc_id', 'deposits_payable_account_id',
            'miscallenous_revenue_id', 'payment_voucher_control_account_id',
        ];

        $ids = [];
        if ($setup) {
            foreach ($accountFields as $f) {
                if ($setup->$f) $ids[] = (int) $setup->$f;
            }
        }

        $accounts = ChartAccount::whereIn('id', array_unique($ids))
            ->select('id', 'account_name', 'account_code')
            ->get()->keyBy('id');

        $periods = GlPeriod::orderBy('period_start', 'desc')->get();

        return view('ledger.gl_settings', compact('setup', 'accounts', 'periods'));
    }

    public function updateGlSettings(Request $request)
    {
        if (!Auth::user()->isAbleTo('update_gl_settings')) {
            return response()->json(['error' => 'Unauthorised'], 403);
        }

        $fields = [
            'retained_earnings_id', 'income_summary_account_id', 'receivable_control_id',
            'payable_control_account_id', 'bank_charges_account_id', 'vat_control_account_id',
            'other_income_account_id', 'bank_interest_account_id', 'accrued_payroll_expense_account_id',
            'payroll_expense_account_id', 'net_salaries_account_id', 'other_employee_deductions_id',
            'clients_account_id', 'tenants_account_id', 'revenues_account_id',
            'clients_bank_account_id', 'petty_cash_account_id', 'loans_receivable_account_id',
            'interests_receivable_account_id', 'business_bank_account_id', 'tenants_suspense_account_id',
            'revenue_vat', 'loans_interests_revenue_account_id', 'rent_commission_account_id',
            'active_fiscal_year_id', 'returns_payable_account_id', 'clients_advance_loans_payable_id',
            'co_op_bank_id', 'equity_bank_account_id', 'mpesa_account_id',
            'earned_commission_acc_id', 'clients_vat', 'deposits_payable_account_id',
            'miscallenous_revenue_id', 'payment_voucher_control_account_id',
        ];

        $data = $request->only($fields);
        // Nullify blank strings
        foreach ($data as $k => $v) {
            if ($v === '' || $v === null) $data[$k] = null;
        }

        try {
            $setup = GlAccountSetup::first();
            if ($setup) {
                $setup->fill($data)->save();
            } else {
                $data['date_time'] = now();
                GlAccountSetup::create($data);
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('GL Settings save error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save settings.'], 500);
        }
    }

    public function searchGlAccountsAjax(Request $request)
    {
        $q = trim($request->get('q', ''));
        $query = ChartAccount::select('id', 'account_name', 'account_code');

        if (strlen($q) > 0) {
            $lower = strtolower($q);
            $query->where(function ($qb) use ($lower) {
                $qb->whereRaw('LOWER(account_name) LIKE ?', ["%{$lower}%"])
                   ->orWhereRaw('LOWER(account_code) LIKE ?', ["%{$lower}%"]);
            });
        }

        $results = $query->orderBy('account_code')->limit(40)->get()
            ->map(fn($a) => ['id' => $a->id, 'text' => $a->account_code . ' — ' . $a->account_name]);

        return response()->json(['results' => $results]);
    }
}
