<?php

namespace App\Http\Controllers;

use App\Models\LandlordEntryType;
use App\Services\LandlordEntryTypeService;
use App\Traits\ChecksMenuAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandlordEntryTypesController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/finance/landlord-entry-types';

    protected LandlordEntryTypeService $entryTypes;

    /**
     * Inject the entry type registry.
     */
    public function __construct(LandlordEntryTypeService $entryTypes) {
        $this->entryTypes = $entryTypes;
    }

    /**
     * Show the registry: every landlord entry type, whether it counts toward the balance, and how
     * many ledger rows fall under it.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        return view('finance.landlord-entry-types.index', [
            'types' => $this->entryTypes->listWithUsage(),
            'unregistered' => $this->entryTypes->unregisteredRows(),
            'kinds' => LandlordEntryTypeService::KINDS,
        ]);
    }

    /**
     * Add an entry type (a new code, or a description pattern under an existing code).
     */
    public function store(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'description_pattern' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(array_keys(LandlordEntryTypeService::KINDS))],
            'counts_toward_balance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->entryTypes->exists($data['code'], $data['description_pattern'] ?? null)) {
            return back()->withInput()->withErrors(['code' => 'This code' . (!empty($data['description_pattern']) ? ' and pattern' : '') . ' is already in the registry.']);
        }

        $this->entryTypes->create($data);

        return redirect()->route('finance.landlord.entry.types.index')->with('success', 'Entry type added.');
    }

    /**
     * Change an entry type's name, kind, whether it counts toward the balance, and notes.
     */
    public function update(Request $request, int $id) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $type = LandlordEntryType::findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(array_keys(LandlordEntryTypeService::KINDS))],
            'counts_toward_balance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->entryTypes->update($type, $data);

        return redirect()->route('finance.landlord.entry.types.index')->with('success', 'Entry type ' . $type->code . ' updated.');
    }
}
