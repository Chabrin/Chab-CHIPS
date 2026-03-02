<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FundManager;
use Illuminate\Http\Request;
use App\Models\EmployerLogin;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use App\Models\ContributionsStagingSummary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Traits\CompanyAccessChecker;

class CompaniesController extends Controller
{
    use CompanyAccessChecker;

    public function listCompanies()
    {
        try {
            // Get user's accessible company IDs
            $companyIds = $this->getUserCompanyIds();

            // If user has no assigned companies, return empty list
            if (empty($companyIds)) {
                $companies = collect([]);
            } else {
                // Filter companies by user's access
                $companies = Company::whereIn('id', $companyIds)->get();
            }

            return view('companies.listcompanies', compact('companies'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return view('companies.listcompanies', ['error' => 'Failed to load companies.']);
        }
    }

    // AJAX endpoint: return list of companies from companies
    public function ajaxCompaniesList(Request $request)
    {
        try {

            $rows = Company::select('id', DB::raw("company_name as name"))->orderBy('company_name')->get();
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            Log::info('Error fetching companies: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Failed to load companies'], 500);
        }
    }

    public function addCompany()
    {
        try {
            $fundManagers = FundManager::orderBy('fund_manager_name')->get();
            return view('companies.addcompany', compact('fundManagers'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return view('companies.addcompany', ['error' => 'Failed to load fund managers.']);
        }
    }

    public function storeCompany(Request $request)
    {
        try {
            $request->validate([
                'company_name' => 'required|string|max:100',
                'email' => 'required|email|max:100',
                'pin_no' => 'required|string|max:100',
                'company_code' => 'required|string|min:2|max:8',
                'fund_manager_id' => 'nullable|exists:fund_managers,id',
                'logo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);
            $data = $request->only([
                'company_name',
                'email',
                'pin_no',
                'company_code',
                'address',
                'town',
                'fax',
                'tel',
                'tel2',
                'mobile',
                'website',
                'physical_add',
                'reg_cert',
                'vat_no',
                'contact_name',
                'fin_start',
                'other_names',
                'fund_manager_id'
            ]);
            if ($request->hasFile('logo_file')) {
                $file = $request->file('logo_file');
                $path = $file->store('companies', 'public');
                // store relative path used by asset('storage/...')
                $data['logo'] = $path;
            }
            $company = Company::create($data);
            return redirect()->route('listcompanies')->with('success', 'Company added successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to add company.')->withInput();
        }
    }

    public function editCompany($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $company = Company::findOrFail($id);
            $fundManagers = FundManager::orderBy('fund_manager_name')->get();
            return view('companies.editcompany', compact('company', 'fundManagers'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return view('companies.editcompany', ['error' => 'Failed to load company.']);
        }
    }

    public function updateCompany(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);
            $request->validate([
                'company_name' => 'required|string|max:100',
                'email' => 'required|email|max:100',
                'pin_no' => 'required|string|max:100',
                'company_code' => 'required|string|min:2|max:7|regex:/^[A-Z]+$/',
                'fund_manager_id' => 'nullable|exists:fund_managers,id',
                'logo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);

            $data = $request->only([
                'company_name',
                'email',
                'pin_no',
                'company_code',
                'address',
                'town',
                'fax',
                'tel',
                'tel2',
                'mobile',
                'website',
                'physical_add',
                'reg_cert',
                'vat_no',
                'contact_name',
                'fin_start',
                'other_names',
                'fund_manager_id'
            ]);
            if ($request->hasFile('logo_file')) {
                $file = $request->file('logo_file');
                $path = $file->store('companies', 'public');
                // delete old logo if exists
                if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                    try {
                        Storage::disk('public')->delete($company->logo);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old company logo: ' . $e->getMessage());
                    }
                }
                $data['logo'] = $path;
            }

            $company->update($data);
            return redirect()->route('listcompanies')->with('success', 'Company updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to update company.')->withInput();
        }
    }

    public function viewCompany($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $company = Company::with('fund_manager')->findOrFail($id);
            return view('companies.viewcompany', compact('company'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return view('companies.viewcompany', ['error' => 'Failed to load company.']);
        }
    }

    // Employer Logins AJAX endpoints
    public function ajaxEmployerLoginsList($companyId)
    {
        try {
            $logins = EmployerLogin::where('company_id', $companyId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'logins' => $logins
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching employer logins: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to load users'
            ], 500);
        }
    }

    public function ajaxEmployerLoginStore(Request $request)
    {
        try {
            $userId = $request->filled('id') ? $request->id : null;

            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'email_address' => [
                    'required',
                    'email',
                    \Illuminate\Validation\Rule::unique('employer_logins', 'email_address')->ignore($userId)
                ],
                'names' => 'required|string|max:255',
                'role' => 'required|in:user,admin',
                'password' => 'nullable|min:6',
            ]);

            DB::beginTransaction();

            if ($userId) {
                // Update existing
                $login = EmployerLogin::findOrFail($userId);
                $login->fill($request->only(['names', 'email_address', 'role']));

                if ($request->filled('password')) {
                    $login->password = $request->password;
                }

                $login->save();
            } else {
                // Create new
                $login = new EmployerLogin();
                $login->fill($request->only(['company_id', 'names', 'email_address', 'role']));

                if ($request->filled('password')) {
                    $login->password = $request->password;
                }

                $login->added_by = Auth::id();
                $login->added_on = now();
                $login->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User saved successfully',
                'login' => $login
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving employer login: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to save user'
            ], 500);
        }
    }

    public function ajaxEmployerLoginSuspend(Request $request, $id)
    {
        try {
            $login = EmployerLogin::findOrFail($id);

            $action = $request->input('action', 'suspend');

            if ($action === 'suspend') {
                $login->suspend();
                $message = 'User suspended successfully';
            } else {
                $login->unsuspend();
                $message = 'User activated successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'login' => $login->load('addedBy')
            ]);
        } catch (\Exception $e) {
            Log::error('Error suspending/unsuspending employer login: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to update user status'
            ], 500);
        }
    }

    public function ajaxEmployerLoginDelete($id)
    {
        try {
            $login = EmployerLogin::findOrFail($id);
            $login->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting employer login: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete user'
            ], 500);
        }
    }

    public function ajaxContributionsList($companyId)
    {
        try {
            // Check if user has company access
            $companyIds = $this->getUserCompanyIds();

            // If user has no assigned companies, return empty list
            if (empty($companyIds)) {
                return response()->json(['success' => true, 'contributions' => []]);
            }

            // Check if user has access to the requested company
            if (!in_array($companyId, $companyIds)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied'
                ], 403);
            }

            $contributions = ContributionsStagingSummary::where('company_id', $companyId)
                ->select(
                    'id',
                    'fund_code',
                    'total_employee_amount',
                    'total_employer_amount',
                    'total_voluntary_amount',
                    'grand_total_amount',
                    'number_of_members',
                    'mop',
                    'value_date',
                    'confirmed',
                    'confirmed_on'
                )
                ->Where('confirmed', true)
                ->where('moved_forward', 1)
                ->orderBy('value_date', 'desc')
                ->get()
                ->map(function ($item) {
                    $item->encrypted_id = Crypt::encryptString($item->id);
                    return $item;
                });

            return response()->json([
                'success' => true,
                'contributions' => $contributions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching contributions: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to load contributions'
            ], 500);
        }
    }

    public function viewContributionDetails($encryptedId)
    {
        try {
            $id = Crypt::decryptString($encryptedId);
            $summary = ContributionsStagingSummary::with('company')->findOrFail($id);

            return view('contributions.details', compact('summary', 'encryptedId'));
        } catch (\Exception $e) {
            Log::error('Error loading contribution details: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to load contribution details');
        }
    }

    public function ajaxContributionDetailsList($encryptedId)
    {
        try {
            $id = Crypt::decryptString($encryptedId);

            $details = \App\Models\ContributionsStagingData::where('summary_id', $id)
                ->select(
                    'id',
                    'payroll_id',
                    'first_name',
                    'surname',
                    'id_no',
                    'employee_amount',
                    'employer_amount',
                    'voluntary_amount',
                    'total_amounts'
                )
                ->get();

            return response()->json([
                'success' => true,
                'details' => $details
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching contribution details: ' . $e->getMessage());
            ErrorDispatcher::dispatch($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to load contribution details'
            ], 500);
        }
    }
}
