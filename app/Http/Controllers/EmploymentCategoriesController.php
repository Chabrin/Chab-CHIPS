<?php

namespace App\Http\Controllers;

use App\Models\EmploymentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HR Settings > Employment Categories: HR adds, edits and switches categories on or off. A switched-off category is no
 * longer offered for new staff but still shows on the staff already in it. One that staff are in cannot be deleted.
 *
 * Access follows the Staff Positions permissions (read, create, update and delete _staff_positions), which are the
 * nearest existing ones; the page is reached from the Staff Positions page.
 */
class EmploymentCategoriesController extends Controller
{
    private function allow(string $action): void
    {
        abort_unless(Auth::check() && Auth::user()->isAbleTo($action . '_staff_positions'), 403, 'You do not have permission to ' . $action . ' employment categories.');
    }

    public function index()
    {
        $this->allow('read');

        return view('settings.hr.employment-categories', [
            'canCreate' => Auth::user()->isAbleTo('create_staff_positions'),
            'canUpdate' => Auth::user()->isAbleTo('update_staff_positions'),
            'canDelete' => Auth::user()->isAbleTo('delete_staff_positions'),
        ]);
    }

    public function list(): JsonResponse
    {
        $this->allow('read');

        $counts = \App\Models\Staff::selectRaw('employment_type, count(*) as total')->groupBy('employment_type')->pluck('total', 'employment_type');

        return response()->json([
            'success' => true,
            'categories' => EmploymentCategory::ordered()->get()->map(fn (EmploymentCategory $category) => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'active' => $category->active,
                'staff_number_prefix' => $category->staff_number_prefix,
                'gets_login' => $category->gets_login,
                'has_end_date' => $category->has_end_date,
                'staff_count' => (int) ($counts[$category->code] ?? 0),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('create');

        $data = $this->validated($request);
        $category = EmploymentCategory::create([
            'code' => EmploymentCategory::codeFor($data['name']),
            'name' => $data['name'],
            'active' => true,
            'staff_number_prefix' => $data['staff_number_prefix'],
            'gets_login' => $data['gets_login'],
            'has_end_date' => $data['has_end_date'],
            'sort_order' => ((int) EmploymentCategory::max('sort_order')) + 10,
        ]);

        return response()->json(['success' => true, 'message' => "{$category->name} was added.", 'id' => $category->id]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->allow('update');

        $category = EmploymentCategory::findOrFail($id);
        $data = $this->validated($request, $category);

        // Staff numbers already carry the prefix, so it stays put once anyone is in the category
        $staffCount = $category->staffCount();
        if ($staffCount > 0 && $data['staff_number_prefix'] !== $category->staff_number_prefix) {
            return response()->json([
                'success' => false,
                'error' => "The staff number prefix cannot change while staff are in {$category->name}, because " . $this->people($staffCount) . ($staffCount === 1 ? ' already carries' : ' already carry') . ' it.',
            ], 422);
        }

        $category->update($data);

        return response()->json(['success' => true, 'message' => "{$category->name} was updated."]);
    }

    public function toggle(int $id): JsonResponse
    {
        $this->allow('update');

        $category = EmploymentCategory::findOrFail($id);
        $category->update(['active' => !$category->active]);

        return response()->json([
            'success' => true,
            'active' => $category->active,
            'message' => $category->active
                ? "{$category->name} is on again and can be chosen for new staff."
                : "{$category->name} is switched off. It is no longer offered for new staff, and the staff in it keep it.",
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->allow('delete');

        $category = EmploymentCategory::findOrFail($id);
        $staffCount = $category->staffCount();
        if ($staffCount > 0) {
            return response()->json([
                'success' => false,
                'error' => "Cannot delete {$category->name} because " . $this->people($staffCount) . ($staffCount === 1 ? ' is' : ' are') . ' in it. Switch it off instead, so it is no longer offered for new staff.',
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => "{$category->name} was deleted."]);
    }

    private function people(int $count): string
    {
        return $count . ($count === 1 ? ' staff member' : ' staff members');
    }

    private function validated(Request $request, ?EmploymentCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($category) {
                // "contract" and "Contract" are the same category
                $taken = EmploymentCategory::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
                    ->when($category, fn ($query) => $query->where('id', '!=', $category->id))->exists();
                if ($taken) {
                    $fail('There is already a category with this name.');
                }
            }],
            'staff_number_prefix' => ['nullable', 'string', 'max:5', 'regex:/^[A-Za-z]*$/'],
            'gets_login' => 'nullable|boolean',
            'has_end_date' => 'nullable|boolean',
        ], [
            'staff_number_prefix.regex' => 'The staff number prefix can only have letters.',
        ]);

        return [
            'name' => trim($data['name']),
            'staff_number_prefix' => strtoupper($data['staff_number_prefix'] ?? ''),
            'gets_login' => $request->boolean('gets_login', true),
            'has_end_date' => $request->boolean('has_end_date', true),
        ];
    }
}
