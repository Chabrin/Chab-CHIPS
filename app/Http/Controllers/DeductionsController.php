<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\HelbDeduction;
use App\Models\HelbDeductionRepeat;
use App\Models\ProratedDeduction;
use App\Models\ProratedDeductionRepeat;
use App\Models\PayrollElement;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Error;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DeductionsController extends Controller
{
    private function requirePermission(string $permission): void
    {
        abort_unless(Auth::check() && Auth::user()->isAbleTo($permission), 403, 'You do not have permission to perform this deduction action.');
    }

    /**
     * Display HELB deductions index page
     */
    public function helbIndex()
    {
        $this->requirePermission('read_helb_loans');
        return view('deductions.helb');
    }

    /**
     * Display HELB deduction details page
     */
    public function helbShow($encryptedId)
    {
        $this->requirePermission('read_helb_loans');
        try {
            $id = \Illuminate\Support\Facades\Crypt::decrypt($encryptedId);
            $deduction = HelbDeduction::with(['staff', 'createdBy'])->findOrFail($id);
            return view('deductions.helb_details', compact('deduction'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching HELB deduction details: ' . $e->getMessage());
            return redirect()->route('deductions.helb')->with('error', 'HELB loan not found');
        }
    }

    /**
     * Get HELB deductions list for DataTables
     */
    public function helbList()
    {
        $this->requirePermission('read_helb_loans');
        try {
            $deductions = HelbDeduction::with(['staff', 'createdBy'])
                ->select('sys_helb_deductions.*')
                ->orderBy('date_time', 'desc')
                ->get();

            // Add permission flags and computed fields
            $canUpdate = Auth::user()->isAbleTo('update_helb_loans');
            $canDelete = Auth::user()->isAbleTo('delete_helb_loans');
            $deductions = $deductions->map(function ($deduction) use ($canUpdate, $canDelete) {
                $deduction->can_update = $canUpdate;
                $deduction->can_delete = $canDelete;
                $deduction->staff_name = $deduction->staff ? $deduction->staff->full_names : 'N/A';
                $deduction->staff_number = $deduction->staff ? $deduction->staff->staff_number : 'N/A';
                $deduction->status = $deduction->cleared ? 'Cleared' : ($deduction->suspended ? 'Suspended' : 'Active');
                $deduction->encrypted_id = \Illuminate\Support\Facades\Crypt::encrypt($deduction->id);
                return $deduction;
            });

            return response()->json(['deductions' => $deductions]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                ErrorDispatcher::dispatch($ex);
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading HELB deductions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load HELB deductions'], 500);
        }
    }

    /**
     * Store a new HELB deduction
     */
    public function helbStore(Request $request)
    {
        $this->requirePermission('create_helb_loans');
        try {
            $validated = $request->validate([
                'staff_id' => 'required|exists:sys_staff,id',
                'loan_balance' => 'required|numeric|min:0',
                'monthly_deduction' => 'required|numeric|min:0',
                'start_deduction_date' => 'required|date',
            ]);

            // Check if staff already has an active HELB loan
            $existingLoan = HelbDeduction::where('staff_id', $validated['staff_id'])
                ->where('cleared', 0)
                ->where('suspended', 0)
                ->first();

            if ($existingLoan) {
                return response()->json([
                    'success' => false,
                    'error' => 'Staff member already has an active HELB loan'
                ], 422);
            }

            $deduction = new HelbDeduction();
            $deduction->date_time = now();
            $deduction->created_by_id = Auth::id();
            $deduction->staff_id = $validated['staff_id'];
            $deduction->loan_balance = $validated['loan_balance'];
            $deduction->monthly_deduction = $validated['monthly_deduction'];
            $deduction->start_deduction_date = $validated['start_deduction_date'];
            $deduction->current_status_id = 1;
            $deduction->cleared = 0;
            $deduction->suspended = 0;
            $deduction->save();

            return response()->json(['success' => true, 'deduction' => $deduction]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving HELB deduction: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save HELB deduction'], 500);
        }
    }

    /**
     * Update an existing HELB deduction
     */
    public function helbUpdate(Request $request, $id)
    {
        $this->requirePermission('update_helb_loans');
        try {
            $validated = $request->validate([
                'loan_balance' => 'required|numeric|min:0',
                'monthly_deduction' => 'required|numeric|min:0',
                'start_deduction_date' => 'required|date',
                'cleared' => 'boolean',
                'suspended' => 'boolean',
            ]);

            $deduction = HelbDeduction::findOrFail($id);
            $deduction->loan_balance = $validated['loan_balance'];
            $deduction->monthly_deduction = $validated['monthly_deduction'];
            $deduction->start_deduction_date = $validated['start_deduction_date'];
            $deduction->cleared = $validated['cleared'] ?? 0;
            $deduction->suspended = $validated['suspended'] ?? 0;
            $deduction->save();

            return response()->json(['success' => true, 'deduction' => $deduction]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                ErrorDispatcher::dispatch($ex);
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating HELB deduction: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update HELB deduction'], 500);
        }
    }

    /**
     * Delete a HELB deduction
     */
    public function helbDestroy($id)
    {
        $this->requirePermission('delete_helb_loans');
        try {
            $deduction = HelbDeduction::findOrFail($id);

            // Delete related repeats first
            HelbDeductionRepeat::where('parent_id', $id)->delete();

            $deduction->delete();

            return response()->json(['success' => true, 'message' => 'HELB deduction deleted successfully']);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                ErrorDispatcher::dispatch($ex);
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error deleting HELB deduction: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete HELB deduction'], 500);
        }
    }

    /**
     * Get staff list for dropdown
     */
    public function getStaffList()
    {
        $user = Auth::user();
        abort_unless(
            $user && ($user->isAbleTo('create_helb_loans') || $user->isAbleTo('create_prorated_deductions')),
            403,
            'You do not have permission to select staff for deductions.'
        );
        try {
            $staff = Staff::select('id', 'staff_number', 'full_names')
                ->orderBy('full_names')
                ->get()
                ->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'text' => $s->staff_number . ' - ' . $s->full_names
                    ];
                });

            return response()->json(['staff' => $staff]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading staff list: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load staff list'], 500);
        }
    }

    /**
     * Get HELB deduction repeats/history for DataTables
     */
    public function helbRepeatsList($id)
    {
        $this->requirePermission('read_helb_loans');
        try {
            $repeats = HelbDeductionRepeat::where('parent_id', $id)
                ->orderBy('deduction_date', 'desc')
                ->get();

            return response()->json(['repeats' => $repeats]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading HELB deduction repeats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load deduction history'], 500);
        }
    }

    // ============================================
    // PRORATED DEDUCTIONS METHODS
    // ============================================

    /**
     * Display prorated deductions index page
     */
    public function proratedIndex()
    {
        $this->requirePermission('read_prorated_deductions');
        return view('deductions.prorated');
    }

    /**
     * Display prorated deduction details page
     */
    public function proratedShow($encryptedId)
    {
        $this->requirePermission('read_prorated_deductions');
        try {
            $id = \Illuminate\Support\Facades\Crypt::decrypt($encryptedId);
            $deduction = ProratedDeduction::with(['staff', 'createdBy', 'element'])->findOrFail($id);
            return view('deductions.prorated_details', compact('deduction'));
        } catch (\Exception $e) {
            Log::error('Error fetching prorated deduction details: ' . $e->getMessage());
            return redirect()->route('deductions.prorated')->with('error', 'Prorated deduction not found');
        }
    }

    /**
     * Get prorated deductions list for DataTables
     */
    public function proratedList()
    {
        $this->requirePermission('read_prorated_deductions');
        try {
            $deductions = ProratedDeduction::with(['staff', 'createdBy', 'element'])
                ->select('sys_hr_employee_amortized_deductions.*')
                ->orderBy('date_time', 'desc')
                ->get();

            // Add permission flags and computed fields
            $canUpdate = Auth::user()->isAbleTo('update_prorated_deductions');
            $deductions = $deductions->map(function ($deduction) use ($canUpdate) {
                $deduction->can_update = $canUpdate;
                $deduction->staff_name = $deduction->staff ? $deduction->staff->full_names : 'N/A';
                $deduction->staff_number = $deduction->staff ? $deduction->staff->staff_number : 'N/A';
                $deduction->element_name = $deduction->element ? $deduction->element->element_name : 'N/A';
                $deduction->encrypted_id = \Illuminate\Support\Facades\Crypt::encrypt($deduction->id);

                // Determine status
                if ($deduction->cleared == 1) {
                    $deduction->status = 'Cleared';
                    $deduction->status_class = 'badge-light-danger';
                } elseif ($deduction->suspended == 1) {
                    $deduction->status = 'Suspended';
                    $deduction->status_class = 'badge-light-danger';
                } else {
                    $deduction->status = 'Active';
                    $deduction->status_class = 'badge-light-success';
                }

                return $deduction;
            });

            return response()->json(['deductions' => $deductions]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading prorated deductions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load prorated deductions'], 500);
        }
    }

    /**
     * Store a new prorated deduction
     */
    public function proratedStore(Request $request)
    {
        $this->requirePermission('create_prorated_deductions');
        try {
            $validated = $request->validate([
                'staff_id' => 'required|exists:sys_staff,id',
                'element_id' => 'required|exists:sys_hr_payroll_elements,id',
                'principal_amount' => 'required|numeric|min:0',
                'interest_rate' => 'required|numeric|min:0|max:100',
                'repayment_months' => 'required|integer|min:1',
                'monthly_amount' => 'required|numeric|min:0',
                'issued_date' => 'required|date',
                'deduction_date' => 'required|date',
                'initial_deduction' => 'nullable|numeric|min:0',
            ]);

            // Calculate interest amount and total
            $principal = $validated['principal_amount'];
            $interestRate = $validated['interest_rate'];
            $months = $validated['repayment_months'];
            $monthlyAmount = $validated['monthly_amount'];
            $initialDeduction = $validated['initial_deduction'] ?? 0;

            $interestAmount = ($principal * $interestRate / 100);
            $totalAmount = $principal + $interestAmount;

            $deduction = new ProratedDeduction();
            $deduction->date_time = now();
            $deduction->created_by_id = Auth::id();
            $deduction->staff_id = $validated['staff_id'];
            $deduction->element_id = $validated['element_id'];
            $deduction->principal_amount = $principal;
            $deduction->interest_rate = $interestRate;
            $deduction->interest_amount = $interestAmount;
            $deduction->total_amount = $totalAmount;
            $deduction->repayment_months = $months;
            $deduction->monthly_deduction = $monthlyAmount;
            $deduction->initial_deduction = $initialDeduction;
            $deduction->issued_date = $validated['issued_date'];
            $deduction->deduction_date = $validated['deduction_date'];
            $deduction->current_status_id = 1;
            $deduction->save();

            return response()->json(['success' => true, 'deduction' => $deduction]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving prorated deduction: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save prorated deduction'], 500);
        }
    }

    /**
     * Update an existing prorated deduction
     */
    public function proratedUpdate(Request $request, $id)
    {
        $this->requirePermission('update_prorated_deductions');
        try {
            $validated = $request->validate([
                'principal_amount' => 'required|numeric|min:0',
                'interest_rate' => 'required|numeric|min:0|max:100',
                'repayment_months' => 'required|integer|min:1',
                'monthly_amount' => 'required|numeric|min:0',
                'issued_date' => 'required|date',
                'deduction_date' => 'required|date',
                'initial_deduction' => 'nullable|numeric|min:0',
            ]);

            $deduction = ProratedDeduction::findOrFail($id);

            // Recalculate amounts
            $principal = $validated['principal_amount'];
            $interestRate = $validated['interest_rate'];
            $months = $validated['repayment_months'];
            $monthlyAmount = $validated['monthly_amount'];
            $initialDeduction = $validated['initial_deduction'] ?? 0;

            $interestAmount = ($principal * $interestRate / 100);
            $totalAmount = $principal + $interestAmount;

            $deduction->principal_amount = $principal;
            $deduction->interest_rate = $interestRate;
            $deduction->interest_amount = $interestAmount;
            $deduction->total_amount = $totalAmount;
            $deduction->repayment_months = $months;
            $deduction->monthly_deduction = $monthlyAmount;
            $deduction->initial_deduction = $initialDeduction;
            $deduction->issued_date = $validated['issued_date'];
            $deduction->deduction_date = $validated['deduction_date'];
            $deduction->save();

            return response()->json(['success' => true, 'deduction' => $deduction]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating prorated deduction: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update prorated deduction'], 500);
        }
    }

    /**
     * Get payroll elements list for dropdown
     */
    public function getElementsList()
    {
        $this->requirePermission('create_prorated_deductions');
        try {
            $elements = PayrollElement::select('id', 'element_name')
                ->where('element_type', '1')
                ->whereNotIn('element_key', ['nhif', 'nssf', 'payee', 'helb_loans', 'housing_levy'])
                ->orderBy('element_name')
                ->get()
                ->map(function ($e) {
                    return [
                        'id' => $e->id,
                        'text' => $e->element_name
                    ];
                });

            return response()->json(['elements' => $elements]);
        } catch (\Exception $e) {
            Log::error('Error loading elements list: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load elements list'], 500);
        }
    }

    /**
     * Get prorated deduction repeats/history for DataTables
     */
    public function proratedRepeatsList($id)
    {
        $this->requirePermission('read_prorated_deductions');
        try {
            $repeats = ProratedDeductionRepeat::where('parent_id', $id)
                ->orderBy('deduction_date', 'desc')
                ->get();

            return response()->json(['repeats' => $repeats]);
        } catch (\Exception $e) {
            Log::error('Error loading prorated deduction repeats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load deduction history'], 500);
        }
    }
}
