<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Gender;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\BankBranch;
use App\Models\Department;
use App\Models\Salutation;
use Illuminate\Http\Request;
use App\Models\MaritalStatus;
use App\Jobs\ErrorDispatchJob;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{

    // Industry CRUD
    public function industryIndex()
    {
        return view('settings.industry');
    }

    public function industryList()
    {
        try {
            $industries = Industry::all();
            return response()->json(['industries' => $industries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading industries: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load industries'], 500);
        }
    }

    public function industryStore(Request $request)
    {
        try {
            $request->validate([
                'industry_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:200',
                'other' => 'nullable|string|max:100',
            ]);
            $industry = new Industry();
            $industry->industry_name = $request->industry_name;
            $industry->description = $request->description;
            $industry->other = $request->other;
            $industry->added_by = Auth::user()->username ?? 'system';
            $industry->added_on = now();
            $industry->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to save industry'], 500);
        }
    }

    public function industryUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'industry_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:200',
                'other' => 'nullable|string|max:100',
            ]);
            $industry = Industry::findOrFail($id);
            $industry->industry_name = $request->industry_name;
            $industry->description = $request->description;
            $industry->other = $request->other;
            $industry->added_by = Auth::user()->username ?? 'system';
            $industry->added_on = now();
            $industry->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update industry'], 500);
        }
    }

    public function industryDelete($id)
    {
        try {
            $industry = Industry::findOrFail($id);
            $industry->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to delete industry'], 500);
        }
    }
    //currencyIndex to load View
    public function currencyIndex()
    {
        $countries = Country::all();
        return view('settings.currency', compact('countries'));
    }

    public function currencyList()
    {
        try {
            $currencies = Currency::with('country')->get();
            return response()->json(['currencies' => $currencies]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load currencies'], 500);
        }
    }

    public function currencyStore(Request $request)
    {
        try {
            $request->validate([
                'currency_name' => 'required|string|max:100',
                'currency_code' => 'required|string|max:10',
                'country_id' => 'nullable|integer|exists:countries,id',
            ]);
            $currency = new Currency();
            $currency->currency_name = $request->currency_name;
            $currency->currency_code = $request->currency_code;
            $currency->country_id = $request->country_id;
            $currency->added_by = Auth::user()->username ?? 'system';
            $currency->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error saving currency: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save currency'], 500);
        }
    }

    public function currencyUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'currency_name' => 'required|string|max:100',
                'currency_code' => 'required|string|max:10',
                'country_id' => 'nullable|integer|exists:countries,id',
            ]);
            $currency = Currency::findOrFail($id);
            $currency->currency_name = $request->currency_name;
            $currency->currency_code = $request->currency_code;
            $currency->country_id = $request->country_id;
            $currency->added_by = Auth::user()->username ?? 'system';
            $currency->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to update currency'], 500);
        }
    }

    public function currencyDelete($id)
    {
        try {
            $currency = Currency::findOrFail($id);
            $currency->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to delete currency'], 500);
        }
    }
    // Country CRUD
    public function countryIndex()
    {
        return view('settings.country');
    }

    public function countryList()
    {
        try {
            $countries = Country::all();
            return response()->json(['countries' => $countries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load countries'], 500);
        }
    }

    public function countryStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'short_name' => 'required|string|max:3',
                'name' => 'required|string|max:50',
                'phone_code' => 'required|string|max:10',
                'risk_level' => 'nullable|string|max:255',
            ]);
            $exists = Country::where('short_name', $validated['short_name'])
                ->orWhere('name', $validated['name'])
                ->orWhere('phone_code', $validated['phone_code'])
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A country with that short name, name, or phone code already exists.'], 422);
            }
            $country = Country::create($validated);
            return response()->json(['success' => true, 'country' => $country]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to save country'], 500);
        }
    }

    public function countryUpdate(Request $request, $id)
    {
        $country = Country::findOrFail($id);
        $validated = $request->validate([
            'short_name' => 'required|string|max:3',
            'name' => 'required|string|max:50',
            'phone_code' => 'required|string|max:10',
            'risk_level' => 'nullable|string|max:255',
        ]);
        try {
            $exists = Country::where(function ($q) use ($validated) {
                $q->where('short_name', $validated['short_name'])
                    ->orWhere('name', $validated['name'])
                    ->orWhere('phone_code', $validated['phone_code']);
            })
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A country with that short name, name, or phone code already exists.'], 422);
            }
            $country->update($validated);
            return response()->json(['success' => true, 'country' => $country]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to update country'], 500);
        }
    }

    public function countryDelete($id)
    {
        try {
            $country = Country::findOrFail($id);
            $country->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to delete country'], 500);
        }
    }

    public function maritalStatusIndex()
    {
        return view('settings.marital_status');
    }

    public function maritalStatusList()
    {
        try {
            $marital_statuses = MaritalStatus::whereNull('deleted_by')->get();
            return response()->json(['marital_statuses' => $marital_statuses]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['error' => 'Failed to load marital statuses'], 500);
            }
            return response()->json(['error' => 'Failed to load marital statuses'], 500);
        }
    }

    public function maritalStatusStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50',
            ]);
            $exists = MaritalStatus::where('name', $validated['name'])->whereNull('deleted_by')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A marital status with that name already exists.'], 422);
            }
            $marital_status = new MaritalStatus();
            $marital_status->name = $validated['name'];
            $marital_status->added_by = Auth::user()->username;
            $marital_status->save();
            return response()->json(['success' => true, 'marital_status' => $marital_status]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                Log::info("Error saving" . $e->getMessage());
                return response()->json(['success' => false, 'error' => 'Failed to save marital status'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to save marital status'], 500);
        }
    }

    public function maritalStatusUpdate(Request $request, $id)
    {
        $marital_status = MaritalStatus::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);
        try {
            $exists = MaritalStatus::where('name', $validated['name'])->where('id', '!=', $id)->whereNull('deleted_by')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A marital status with that name already exists.'], 422);
            }
            $marital_status->update($validated);
            return response()->json(['success' => true, 'marital_status' => $marital_status]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to update marital status'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to update marital status'], 500);
        }
    }

    public function maritalStatusDelete($id)
    {
        try {
            $marital_status = MaritalStatus::findOrFail($id);
            $marital_status->deleted_by = Auth::user()->id;
            $marital_status->save();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to delete marital status'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete marital status'], 500);
        }
    }

    public function salutationIndex()
    {
        return view('settings.salutation');
    }

    public function salutationList()
    {
        try {
            $salutations = Salutation::whereNull('deleted_by')->get();
            return response()->json(['salutations' => $salutations]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load salutations'], 500);
        }
    }

    public function salutationStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'description' => 'nullable|string|max:255',
            ]);
            $exists = Salutation::where('name', $validated['name'])->whereNull('deleted_by')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A salutation with that name already exists.'], 422);
            }
            $salutation = new Salutation();
            $salutation->name = $validated['name'];
            $salutation->description = $validated['description'] ?? '';
            $salutation->added_by = Auth::user()->username;
            $salutation->save();
            return response()->json(['success' => true, 'salutation' => $salutation]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                Log::info("Error saving" . $e->getMessage());
                return response()->json(['success' => false, 'error' => 'Failed to save salutation'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to save salutation'], 500);
        }
    }

    public function salutationUpdate(Request $request, $id)
    {
        $salutation = Salutation::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);
        try {
            $exists = Salutation::where('name', $validated['name'])->where('id', '!=', $id)->whereNull('deleted_by')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A salutation with that name already exists.'], 422);
            }
            $salutation->name = $validated['name'];
            $salutation->description = $validated['description'] ?? '';
            $salutation->save();
            return response()->json(['success' => true, 'salutation' => $salutation]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to update salutation'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to update salutation'], 500);
        }
    }

    // Banks CRUD
    public function banksIndex()
    {
        return view('settings.banks');
    }

    public function banksList()
    {
        try {
            $banks = Bank::orderBy('bank_name')->get();
            return response()->json(['banks' => $banks]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load banks'], 500);
        }
    }

    public function banksStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'bank_name' => 'required|string|max:100',
                'address' => 'nullable|string|max:255',
            ]);
            $exists = Bank::where('bank_name', $validated['bank_name'])->exists();
            if ($exists) return response()->json(['success' => false, 'error' => 'A bank with that name already exists.'], 422);
            $bank = new Bank();
            $bank->bank_name = $validated['bank_name'];
            $bank->address = $validated['address'] ?? null;
            $bank->current_status_id = 1;
            $bank->date_time = now();
            $bank->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to save bank'], 500);
        }
    }

    public function banksUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'bank_name' => 'required|string|max:100',
                'address' => 'nullable|string|max:255',
            ]);
            $bank = Bank::findOrFail($id);
            $exists = Bank::where('bank_name', $validated['bank_name'])->where('id', '!=', $id)->exists();
            if ($exists) return response()->json(['success' => false, 'error' => 'A bank with that name already exists.'], 422);
            $bank->bank_name = $validated['bank_name'];
            $bank->address = $validated['address'] ?? null;
            $bank->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to update bank'], 500);
        }
    }

    public function banksDelete($id)
    {
        try {
            $bank = Bank::findOrFail($id);
            $bank->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to delete bank'], 500);
        }
    }

    // Bank branches CRUD
    public function branchesIndex()
    {
        // need banks list for branch modal
        $banks = Bank::orderBy('bank_name')->get();
        return view('settings.branches', compact('banks'));
    }

    public function branchesList()
    {
        try {
            $branches = BankBranch::with('bank')->get();
            return response()->json(['branches' => $branches]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load branches'], 500);
        }
    }

    /**
     * Return branches for a specific bank (AJAX)
     */
    public function bankBranches($bankId)
    {
        try {
            $branches = BankBranch::where('bank_id', $bankId)->get();
            return response()->json(['branches' => $branches]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['error' => 'Failed to load branches for bank'], 500);
        }
    }

    public function branchesStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_code' => 'required|string|max:50',
                'branch_name' => 'required|string|max:100',
                'bank_id' => 'required|integer|exists:sys_set_banks,id',
            ]);
            // Ensure branch_code is unique per bank (different banks may have same branch_code)
            $exists = BankBranch::where('branch_code', $validated['branch_code'])
                ->where('bank_id', $validated['bank_id'])
                ->exists();
            if ($exists) return response()->json(['success' => false, 'error' => 'Branch code already exists for the selected bank.'], 422);
            $b = new BankBranch();
            $b->branch_code = $validated['branch_code'];
            $b->branch_name = $validated['branch_name'];
            $b->bank_id = $validated['bank_id'];
            $b->current_status_id = 1;
            $b->date_time = now();
            $b->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error saving branch: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save branch'], 500);
        }
    }

    public function branchesUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'branch_code' => 'required|string|max:50',
                'branch_name' => 'required|string|max:100',
                'bank_id' => 'required|integer|exists:sys_set_banks,id',
            ]);
            $branch = BankBranch::findOrFail($id);
            // Ensure branch_code is unique per bank when updating
            $exists = BankBranch::where('branch_code', $validated['branch_code'])
                ->where('bank_id', $validated['bank_id'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) return response()->json(['success' => false, 'error' => 'Branch code already exists for the selected bank.'], 422);
            $branch->branch_code = $validated['branch_code'];
            $branch->branch_name = $validated['branch_name'];
            $branch->bank_id = $validated['bank_id'];
            $branch->save();
            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to update branch'], 500);
        }
    }

    public function branchesDelete($id)
    {
        try {
            $branch = BankBranch::findOrFail($id);
            $branch->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to delete branch'], 500);
        }
    }

    public function salutationDelete($id)
    {
        try {
            $salutation = Salutation::findOrFail($id);
            $salutation->deleted_by = Auth::user()->id;
            $salutation->save();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to delete salutation'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete salutation'], 500);
        }
    }

    public function departmentIndex()
    {
        return view('settings.department');
    }

    public function departmentList()
    {
        try {
            $departments = Department::whereNull('deleted_by')->get();
            return response()->json(['departments' => $departments]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['error' => 'Failed to load departments'], 500);
            }
            return response()->json(['error' => 'Failed to load departments'], 500);
        }
    }

    public function departmentStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'short_code' => 'nullable|string|max:30',
            ]);
            $exists = Department::where('name', $validated['name'])->whereNull('deleted_by')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A department with that name already exists.'], 422);
            }
            $department = Department::create($validated);
            return response()->json(['success' => true, 'department' => $department]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to save department'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to save department'], 500);
        }
    }

    public function departmentUpdate(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'short_code' => 'nullable|string|max:30',
        ]);
        try {
            $exists = Department::where('name', $validated['name'])->where('id', '!=', $id)->whereNull('deleted_by')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A department with that name already exists.'], 422);
            }
            $department->update($validated);
            return response()->json(['success' => true, 'department' => $department]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to update department'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to update department'], 500);
        }
    }

    public function departmentDelete($id)
    {
        try {
            $department = Department::findOrFail($id);
            $department->deleted_by = Auth::user()->id;
            $department->save();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $e) {
                ErrorDispatcher::dispatch($e);
                return response()->json(['success' => false, 'error' => 'Failed to delete department'], 500);
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete department'], 500);
        }
    }

    public function genderIndex()
    {
        return view('settings.gender');
    }

    public function genderList()
    {
        try {
            $genders = Gender::whereNull('deleted_at')->get();
            return response()->json(['genders' => $genders]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['genders' => [], 'success' => false, 'error' => 'Failed to load genders'], 500);
        }
    }

    public function genderStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'description' => 'nullable|string|max:255',
            ]);

            // Check for existing gender with same name (not deleted)
            $exists = Gender::where('name', $validated['name'])->whereNull('deleted_at')->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A gender with that name already exists.'], 422);
            }

            $gender = Gender::create($validated);
            return response()->json(['success' => true, 'gender' => $gender]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save gender'], 500);
        }
    }

    public function genderUpdate(Request $request, $id)
    {
        $gender = Gender::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:gender,name,' . $id . ',id,deleted_at,NULL',
            'description' => 'nullable|string|max:255',
        ]);
        try {
            $gender->update($validated);
            return response()->json(['success' => true, 'gender' => $gender]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update gender'], 500);
        }
    }

    public function genderDelete($id)
    {
        try {
            $gender = Gender::findOrFail($id);
            $gender->deleted_at = now();
            $gender->deleted_by = Auth::user()->username;
            $gender->save();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete gender'], 500);
        }
    }
}
