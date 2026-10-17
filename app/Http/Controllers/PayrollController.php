<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\TaxBand;
use App\Models\Position;
use App\Models\ChartAccount;
use Illuminate\Http\Request;
use App\Models\PayrollElement;
use App\Models\PayrollSummary;
use App\Models\PayrollCalendar;
use App\Models\PayrollPeriod;
use App\Models\ContractElement;
use App\Models\EmployeeContract;
use App\Models\HelbDeduction;
use App\Models\HelbDeductionRepeat;
use App\Models\ProratedDeduction;
use App\Models\ProratedDeductionRepeat;
use App\Services\ErrorDispatcher;
use App\Services\NssfCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function __construct(private NssfCalculator $nssfCalculator) {
    }

    /**
     * Display tax bands index page
     */
    public function taxBandsIndex()
    {
        return view('settings.payroll.tax-bands');
    }

    /**
     * Get tax bands list for DataTables
     */
    public function taxBandsList()
    {
        try {
            $taxBands = TaxBand::orderBy('range_from', 'asc')->get();

            // Add permission flags
            $taxBands = $taxBands->map(function ($band) {
                $band->can_update = Auth::user()->isAbleTo('update_tax_bands');
                $band->can_delete = Auth::user()->isAbleTo('delete_tax_bands');
                return $band;
            });

            return response()->json(['taxBands' => $taxBands]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading tax bands: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load tax bands'], 500);
        }
    }

    /**
     * Store a new tax band
     */
    public function taxBandsStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'range_from' => 'required|numeric|min:0',
                'range_to' => 'nullable|numeric|min:0|gt:range_from',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'is_last' => 'boolean',
            ]);

            // Check for overlapping ranges
            $overlap = $this->checkOverlap(
                $validated['range_from'],
                $validated['range_to'] ?? null,
                null
            );

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tax band range overlaps with existing band'
                ], 422);
            }

            // If this is marked as last, unmark any existing last band
            if (isset($validated['is_last']) && $validated['is_last']) {
                TaxBand::where('is_last', true)->update(['is_last' => false]);
                $validated['range_to'] = null; // Last band has no upper limit
            }

            $taxBand = new TaxBand();
            $taxBand->range_from = $validated['range_from'];
            $taxBand->range_to = $validated['range_to'];
            $taxBand->tax_rate = $validated['tax_rate'];
            $taxBand->is_last = $validated['is_last'] ?? false;
            $taxBand->added_by = Auth::user()->username ?? 'system';
            $taxBand->save();

            return response()->json(['success' => true, 'taxBand' => $taxBand]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving tax band: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save tax band'], 500);
        }
    }

    /**
     * Update an existing tax band
     */
    public function taxBandsUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'range_from' => 'required|numeric|min:0',
                'range_to' => 'nullable|numeric|min:0|gt:range_from',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'is_last' => 'boolean',
            ]);

            $taxBand = TaxBand::findOrFail($id);

            // Check for overlapping ranges (excluding current record)
            $overlap = $this->checkOverlap(
                $validated['range_from'],
                $validated['range_to'] ?? null,
                $id
            );

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tax band range overlaps with existing band'
                ], 422);
            }

            // If this is marked as last, unmark any existing last band
            if (isset($validated['is_last']) && $validated['is_last']) {
                TaxBand::where('is_last', true)->where('id', '!=', $id)->update(['is_last' => false]);
                $validated['range_to'] = null; // Last band has no upper limit
            }

            $taxBand->range_from = $validated['range_from'];
            $taxBand->range_to = $validated['range_to'];
            $taxBand->tax_rate = $validated['tax_rate'];
            $taxBand->is_last = $validated['is_last'] ?? false;
            $taxBand->updated_by = Auth::user()->username ?? 'system';
            $taxBand->save();

            return response()->json(['success' => true, 'taxBand' => $taxBand]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating tax band: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update tax band'], 500);
        }
    }

    /**
     * Check for overlapping tax band ranges
     */
    private function checkOverlap($rangeFrom, $rangeTo, $excludeId = null)
    {
        $query = TaxBand::where(function ($q) use ($rangeFrom, $rangeTo) {
            if ($rangeTo === null) {
                // New band has no upper limit - check if any band starts within our range
                $q->where('range_from', '>=', $rangeFrom);
            } else {
                // Check for any overlap
                $q->where(function ($subQ) use ($rangeFrom, $rangeTo) {
                    // Case 1: Existing band starts within new range
                    $subQ->whereBetween('range_from', [$rangeFrom, $rangeTo])
                        // Case 2: Existing band ends within new range
                        ->orWhereBetween('range_to', [$rangeFrom, $rangeTo])
                        // Case 3: Existing band completely encompasses new range
                        ->orWhere(function ($innerQ) use ($rangeFrom, $rangeTo) {
                            $innerQ->where('range_from', '<=', $rangeFrom)
                                ->where(function ($nullCheck) use ($rangeTo) {
                                    $nullCheck->where('range_to', '>=', $rangeTo)
                                        ->orWhereNull('range_to');
                                });
                        });
                });
            }
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Display payroll elements index page
     */
    public function payrollElementsIndex()
    {
        return view('settings.payroll.elements');
    }

    /**
     * Get payroll elements list for DataTables
     */
    public function payrollElementsList()
    {
        try {
            $elements = PayrollElement::with(['account', 'position'])
                ->orderBy('element_name', 'asc')
                ->get();

            // Add permission flags and formatted data
            $elements = $elements->map(function ($element) {
                $element->can_update = Auth::user()->isAbleTo('update_payroll_elements');
                $element->can_delete = Auth::user()->isAbleTo('delete_payroll_elements');
                $element->element_type_name = $element->element_type_name;
                $element->status_name = $element->status_name;
                $element->formatted_amount = $element->formatted_amount;
                $element->account_name = $element->account ? $element->account->full_account : '-';
                $element->position_name = $element->position ? $element->position->position_name : '-';
                return $element;
            });

            return response()->json(['elements' => $elements]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading payroll elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load payroll elements'], 500);
        }
    }

    public function payrollElementsListSimpler()
    {
        try {
            $elements = PayrollElement::select('id', 'element_name')->orderBy('element_name', 'asc')
                ->get();

            return response()->json(['elements' => $elements]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading payroll elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load payroll elements'], 500);
        }
    }


    public function payrollElementsListAccounts()
    {
        try {
            $accounts = ChartAccount::with('chartGroup')->get();
            // Group accounts by chartGroup title
            $grouped = [];
            foreach ($accounts as $account) {
                $groupTitle = $account->chartGroup ? $account->chartGroup->title : 'Other';
                if (!isset($grouped[$groupTitle])) {
                    $grouped[$groupTitle] = [];
                }
                $grouped[$groupTitle][] = [
                    'id' => $account->id,
                    'name' => $account->full_account,
                ];
            }

            return response()->json(['accounts' => $grouped]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading accounts for payroll elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load accounts'], 500);
        }
    }


    public function payrollElementsListStaffPositions()
    {
        try {
            $positions = Position::select('id', 'position_name')->orderBy('position_name', 'asc')->get();
            return response()->json(['staff_positions' => $positions]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error loading staff positions for payroll elements: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load staff positions'], 500);
        }
    }

    public function payrollElementsStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'element_name'      => 'required|string|max:255',
                'element_type'      => 'required|in:1,2', // 1: Deduction, 2: Allowance
                'formula'           => 'required|in:1,2,3,4,5,6,7,8', // Formula options
                'amount'            => 'required|numeric|min:0',
                'account'           => 'nullable',
                'staff_positions' => 'nullable',
                'payslip_position'  => 'nullable|integer',
            ]);

            Log::info('Validated payroll element data: ', $validated);

            $element = new PayrollElement();
            $element->element_name        = $validated['element_name'];
            $element->element_type        = $validated['element_type'];
            $element->formula_id          = $validated['formula'];
            $element->amount              = $validated['amount'];
            $element->account_id          = $validated['account'] ?? null;
            $element->staff_positions_id  = $validated['staff_positions'] ?? null;
            $element->payslip_position    = $validated['payslip_position'] ?? null;
            $element->current_status_id   = 5; // 5: Approved, 1: Pending
            $element->date_time           = now();
            $element->save();

            return response()->json(['success' => true, 'element' => $element]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error saving payroll element: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save payroll element'], 500);
        }
    }

    public function payrollElementsUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'element_name'      => 'required|string|max:255',
                'element_type'      => 'required|in:1,2', // 1: Deduction, 2: Allowance
                'formula'           => 'required|in:1,2,3,4,5,6,7,8', // Formula options
                'amount'            => 'required|numeric|min:0',
                'account'           => 'nullable',
                'staff_positions'   => 'nullable',
                'payslip_position'  => 'nullable|integer',
            ]);

            $element = PayrollElement::findOrFail($id);
            $element->element_name        = $validated['element_name'];
            $element->element_type        = $validated['element_type'];
            $element->formula_id          = $validated['formula'];
            $element->amount              = $validated['amount'];
            $element->account_id          = $validated['account'] ?? null;
            $element->staff_positions_id  = $validated['staff_positions'] ?? null;
            $element->payslip_position    = $validated['payslip_position'] ?? null;
            $element->current_status_id   = 5; // 5: Approved, 1: Pending
            $element->date_time           = now();
            $element->save();

            return response()->json(['success' => true, 'element' => $element]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating payroll element: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update payroll element'], 500);
        }
    }

    public function payrollElementsDelete($id)
    {
        try {
            $element = PayrollElement::findOrFail($id);
            $element->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error deleting payroll element: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete payroll element'], 500);
        }
    }

    // Payroll Calendar Settings
    public function calendarIndex()
    {
        return view('settings.hr.payroll_calendar');
    }

    public function calendarList()
    {
        // try {
            $calendars = PayrollCalendar::with(['createdBy', 'periods'])
                ->orderBy('payroll_month_date', 'desc')
                ->get()
                ->map(function ($calendar) {
                    return [
                        'id' => $calendar->id,
                        'payroll_month_date' => $calendar->payroll_month_date,
                        'month_formatted' => $calendar->month_formatted,
                        'periods_count' => $calendar->periods->count(),
                        'current_status_id' => $calendar->current_status_id,
                        'created_by' => $calendar->createdBy ? [
                            'id' => $calendar->createdBy->id,
                            'name' => $calendar->createdBy->name,
                        ] : null,
                        'date_time' => $calendar->date_time,
                        'can_update' => Auth::user()->isAbleTo('update_payroll_calendar'),
                        'can_delete' => Auth::user()->isAbleTo('delete_payroll_calendar'),
                    ];
                });

            return response()->json(['calendars' => $calendars]);
        // } catch (\Exception $e) {
        //     try {
        //         ErrorDispatcher::dispatch($e);
        //     } catch (\Exception $ex) {
        //         Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
        //     }
        //     return response()->json(['error' => 'Failed to load payroll calendar'], 500);
        // }
    }

    public function calendarStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'payroll_month_date' => 'required|string|size:6|unique:sys_hr_payroll_calendar,payroll_month_date',
            ]);

            // Create calendar (summary only)
            $calendar = new PayrollCalendar();
            $calendar->payroll_month_date = $validated['payroll_month_date'];
            $calendar->current_status_id = 5;
            $calendar->created_by = Auth::id();
            $calendar->date_time = now();
            $calendar->save();

            return response()->json(['success' => true, 'calendar' => $calendar]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save payroll calendar: ' . $e->getMessage()], 500);
        }
    }

    public function calendarUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'payroll_month_date' => 'required|string|size:6|unique:sys_hr_payroll_calendar,payroll_month_date,' . $id,
            ]);

            $calendar = PayrollCalendar::findOrFail($id);
            $calendar->payroll_month_date = $validated['payroll_month_date'];
            $calendar->date_time = now();
            $calendar->save();

            return response()->json(['success' => true, 'calendar' => $calendar]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update payroll calendar: ' . $e->getMessage()], 500);
        }
    }

    public function calendarDelete($id)
    {
        try {
            $calendar = PayrollCalendar::findOrFail($id);
            $calendar->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete payroll calendar'], 500);
        }
    }

    // Payroll Periods Management
    public function periodsList($calendarId)
    {
        try {
            $periods = PayrollPeriod::with('createdBy')
                ->where('calendar_id', $calendarId)
                ->orderBy('period_number')
                ->get()
                ->map(function ($period) {
                    return [
                        'id' => $period->id,
                        'period_number' => $period->period_number,
                        'start_date' => $period->start_date ? $period->start_date->format('Y-m-d') : null,
                        'end_date' => $period->end_date ? $period->end_date->format('Y-m-d') : null,
                        'formatted_period' => $period->formatted_period,
                        'duration_days' => $period->duration_days,
                        'period_month' => $period->period_month,
                        'current_status_id' => $period->current_status_id,
                        'created_by' => $period->createdBy ? [
                            'id' => $period->createdBy->id,
                            'name' => $period->createdBy->name,
                        ] : null,
                        'date_time' => $period->date_time,
                        'can_update' => Auth::user()->isAbleTo('update_payroll_calendar'),
                        'can_delete' => Auth::user()->isAbleTo('delete_payroll_calendar'),
                    ];
                });

            return response()->json(['periods' => $periods]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load periods'], 500);
        }
    }

    public function periodsStore(Request $request, $calendarId)
    {
        try {
            $calendar = PayrollCalendar::findOrFail($calendarId);

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            // Calculate duration in days
            $startDate = new \DateTime($validated['start_date']);
            $endDate = new \DateTime($validated['end_date']);
            $duration = $startDate->diff($endDate)->days + 1;

            // Check if duration exceeds 32 days
            if ($duration > 32) {
                return response()->json([
                    'success' => false,
                    'error' => 'Period cannot exceed 32 days. Current period is ' . $duration . ' days.'
                ], 422);
            }

            // Check for overlapping periods within the same calendar
            $overlap = PayrollPeriod::where('calendar_id', $calendarId)
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('start_date', '<=', $validated['start_date'])
                                ->where('end_date', '>=', $validated['end_date']);
                        });
                })->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'error' => 'Period overlaps with existing period'
                ], 422);
            }

            // Get next period number
            $maxPeriod = PayrollPeriod::where('calendar_id', $calendarId)->max('period_number');
            $periodNumber = ($maxPeriod ?? 0) + 1;

            // Extract month name from calendar's payroll_month_date
            $year = substr($calendar->payroll_month_date, 0, 4);
            $month = substr($calendar->payroll_month_date, 4, 2);
            $monthName = date('F', mktime(0, 0, 0, (int)$month, 1));

            // Create period
            $period = new PayrollPeriod();
            $period->calendar_id = $calendarId;
            $period->period_number = $periodNumber;
            $period->start_date = $validated['start_date'];
            $period->end_date = $validated['end_date'];
            $period->period_month = $monthName;
            $period->current_status_id = 5;
            $period->created_by = Auth::id();
            $period->date_time = now();
            $period->save();

            return response()->json(['success' => true, 'period' => $period]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to save period: ' . $e->getMessage()], 500);
        }
    }

    public function periodsUpdate(Request $request, $calendarId, $periodId)
    {
        try {
            $calendar = PayrollCalendar::findOrFail($calendarId);

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            // Calculate duration in days
            $startDate = new \DateTime($validated['start_date']);
            $endDate = new \DateTime($validated['end_date']);
            $duration = $startDate->diff($endDate)->days + 1;

            // Check if duration exceeds 32 days
            if ($duration > 32) {
                return response()->json([
                    'success' => false,
                    'error' => 'Period cannot exceed 32 days. Current period is ' . $duration . ' days.'
                ], 422);
            }

            // Check for overlapping periods (excluding current record)
            $overlap = PayrollPeriod::where('calendar_id', $calendarId)
                ->where('id', '!=', $periodId)
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('start_date', '<=', $validated['start_date'])
                                ->where('end_date', '>=', $validated['end_date']);
                        });
                })->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'error' => 'Period overlaps with existing period'
                ], 422);
            }

            $period = PayrollPeriod::where('calendar_id', $calendarId)
                ->where('id', $periodId)
                ->firstOrFail();

            $period->start_date = $validated['start_date'];
            $period->end_date = $validated['end_date'];
            $period->date_time = now();
            $period->save();

            return response()->json(['success' => true, 'period' => $period]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to update period: ' . $e->getMessage()], 500);
        }
    }

    public function periodsDelete($calendarId, $periodId)
    {
        try {
            $period = PayrollPeriod::where('calendar_id', $calendarId)
                ->where('id', $periodId)
                ->firstOrFail();
            $period->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            return response()->json(['success' => false, 'error' => 'Failed to delete period'], 500);
        }
    }

    // Income Tax Settings
    public function incomeTaxSettingsIndex()
    {
        return view('settings.hr.income_tax_settings');
    }

    /**
     * Display payroll processing page
     */
    public function processingIndex()
    {
        return view('payroll.processing');
    }

    /**
     * Get recent 3 periods with payroll summary
     */
    public function getRecentPeriodsSummary()
    {
        try {
            $recentPeriods = PayrollSummary::join('sys_hr_payroll_periods as p', 'sys_hr_payroll_summary.period_id', '=', 'p.id')
                ->select(
                    'p.id as period_id',
                    'p.start_date',
                    'p.end_date',
                    'p.period_number',
                    DB::raw('SUM(sys_hr_payroll_summary.basic_salary) as total_basic_salary'),
                    DB::raw('SUM(sys_hr_payroll_summary.basic_salary) as total_gross_salary'),
                    DB::raw('SUM(sys_hr_payroll_summary.total_deductions) as total_deductions'),
                    DB::raw('SUM(sys_hr_payroll_summary.net_pay) as total_net_salary'),
                    DB::raw('COUNT(sys_hr_payroll_summary.id) as employee_count')
                )
                ->groupBy('p.id', 'p.start_date', 'p.end_date', 'p.period_number')
                ->orderBy('p.start_date', 'desc')
                ->limit(3)
                ->get();

            return response()->json(['periods' => $recentPeriods]);
        } catch (\Exception $e) {
            Log::error('Error loading recent periods summary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load periods'], 500);
        }
    }

    /**
     * Get unprocessed periods
     */
    public function getUnprocessedPeriods()
    {
        try {
            $unprocessedPeriods = PayrollPeriod::whereNotIn('id', function ($query) {
                $query->select('period_id')
                    ->from('sys_hr_payroll_summary')
                    ->whereNotNull('period_id')
                    ->distinct();
            })
                ->where('current_status_id', 5) // Only active periods
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($period) {
                    return [
                        'id' => $period->id,
                        'period_number' => $period->period_number,
                        'start_date' => $period->start_date ? $period->start_date->format('Y-m-d') : null,
                        'end_date' => $period->end_date ? $period->end_date->format('Y-m-d') : null,
                        'formatted_period' => $period->formatted_period,
                        'period_month' => $period->period_month,
                    ];
                });

            return response()->json(['periods' => $unprocessedPeriods]);
        } catch (\Exception $e) {
            Log::error('Error loading unprocessed periods: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load unprocessed periods'], 500);
        }
    }

    /**
     * Process payroll via AJAX
     */
    public function processPayroll(Request $request)
    {
        try {
            $validated = $request->validate([
                'period_id' => 'required|exists:sys_hr_payroll_periods,id',
            ]);

            $period = PayrollPeriod::findOrFail($validated['period_id']);
            // Check if this period has already been processed
            $alreadyProcessed = PayrollSummary::where('period_id', $validated['period_id'])->exists();

            if ($alreadyProcessed) {
                return response()->json([
                    'success' => false,
                    'error' => 'This payroll period has already been processed. Please select an unprocessed period.'
                ], 422);
            }

            $startDate = $period->start_date->format('Y-m-d');
            $endDate = $period->end_date->format('Y-m-d');

            // Get payroll elements by element_key
            $payrollElements = PayrollElement::approved()
                ->whereIn('element_key', ['nssf', 'nhif', 'housing_levy', 'payee'])
                ->get()
                ->keyBy('element_key');

            // Get active employee contracts
            $contracts = EmployeeContract::with('staff')
                ->active()
                ->get();
            //count contracts
            Log::info('Number of active employee contracts found: ' . $contracts->count());

            if ($contracts->isEmpty()) {
                Log::info('No active employee contracts found during payroll processing');
                return response()->json([
                    'success' => false,
                    'error' => 'No active employee contracts found'
                ], 404);
            }

            $results = [];

            // Wrap entire payroll processing in a transaction
            DB::beginTransaction();

            try {
                foreach ($contracts as $contract) {
                    if (!$contract->staff) {
                        continue;
                    }

                    $payrollData = $this->calculatePayroll(
                        $contract->staff->full_name ?? 'Unknown',
                        $contract->basic_salary,
                        $contract->staff_id,
                        $contract->staff_number,
                        $contract->position_id,
                        $contract->staff->department_id ?? null,
                        $payrollElements,
                        $startDate,
                        $period->id,
                        $contract->id
                    );

                    $results[] = $payrollData;

                    // Save to database (includes deduction repeats logging)
                    $this->savePayrollSummary($payrollData, $startDate, $endDate, $period->id);
                }

                // Commit transaction if all successful
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payroll processed successfully for ' . count($results) . ' employees',
                    'results' => $results
                ]);
            } catch (\Exception $ex) {
                // Rollback on any error
                DB::rollBack();
                throw $ex;
            }
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error processing payroll: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to process payroll'], 500);
        }
    }

    /**
     * Build a self-describing payslip element entry.
     * Stores name/type/position inline so display never requires a second DB lookup.
     */
    private function makePayslipEntry(?PayrollElement $element, float $amount, string $fallbackName = '', string $fallbackType = '1', int $fallbackPos = 999, ?string $nameOverride = null): array
    {
        return [
            'element_id'       => $element?->id ?? 0,
            'element_name'     => $nameOverride ?? $element?->element_name ?? $fallbackName,
            'element_type'     => $element?->element_type ?? $fallbackType,
            'payslip_position' => $element?->payslip_position ?? $fallbackPos,
            'amount'           => $amount,
        ];
    }

    /**
     * Calculate payroll for a single employee
     */
    private function calculatePayroll($employeeName, $basicSalary, $staffId, $staffNumber, $positionId, $departmentId, $payrollElements, $periodStartDate, $periodId, $contractId = null)
    {
        $staffDisability = Staff::where('id', $staffId)->value('disability') ?? 'no';

        // --- Contract allowances & non-statutory deductions ---
        $contractAllowances   = 0.0;
        $allowanceEntries     = [];
        $extraDeductionEntries = [];
        $statutoryKeys = ['nssf', 'nhif', 'housing_levy', 'payee', 'helb_loans'];

        if ($contractId) {
            $contractElements = ContractElement::with('payrollElement')
                ->where('parent_id', $contractId)
                ->get();

            foreach ($contractElements as $el) {
                $pe = $el->payrollElement;
                if (!$pe) {
                    continue;
                }
                $amount = (float) $el->amount;
                if ($pe->element_type == '2') {
                    // Allowance — counts toward gross pay and appears under Earnings
                    $contractAllowances += $amount;
                    $allowanceEntries[]   = $this->makePayslipEntry($pe, $amount, '', '2');
                } elseif ($pe->element_type == '1' && !in_array($pe->element_key, $statutoryKeys)) {
                    // Non-statutory deduction — added post-tax alongside HELB/prorated
                    $extraDeductionEntries[] = $this->makePayslipEntry($pe, $amount, '', '1');
                }
            }
        }

        // Gross pay = basic salary + contract allowances
        $grossPay = (float) $basicSalary + $contractAllowances;

        // --- Statutory deductions ---
        $nssfElement       = $payrollElements->get('nssf');
        $nhifElement       = $payrollElements->get('nhif');
        $housingLevyElement = $payrollElements->get('housing_levy');
        $payeeElement      = $payrollElements->get('payee');

        // NSSF (Tier I + Tier II) on gross pay, same calculation as the contract form
        $nssfBreakdown = $this->nssfCalculator->calculate($grossPay);
        $nssf = $nssfBreakdown['total'];
        $nssfTier1 = $nssfBreakdown['tiers'][0]['amount'];
        $nssfTier2 = $nssfBreakdown['tiers'][1]['amount'];
        // NHIF/SHIF and Housing Levy on full gross (basic + allowances)
        $nhif        = $this->calculateNHIF($grossPay);
        $housingLevy = $this->calculateHousingLevy($grossPay);

        // Taxable income for PAYE
        $taxableIncome = $grossPay - $nssf - $nhif - $housingLevy;

        $paye = 0;
        if (strtolower($staffDisability) !== 'yes') {
            $paye = $this->calculatePAYE($taxableIncome);
        }

        $incomeTaxBeforeRelief = $paye + 2400;

        $totalDeductions = $nssf + $nhif + $housingLevy + $paye;

        // Build payslip elements — stored with full details so display needs no extra DB query
        $payslipElements = array_merge(
            $allowanceEntries,
            [
                $this->makePayslipEntry($nssfElement,       $nssfTier1,   'NSSF Tier I',  '1', 10, 'NSSF Tier I'),
                $this->makePayslipEntry($nssfElement,       $nssfTier2,   'NSSF Tier II', '1', 11, 'NSSF Tier II'),
                $this->makePayslipEntry($nhifElement,       $nhif,        'SHIF (NHIF)',  '1', 20),
                $this->makePayslipEntry($housingLevyElement, $housingLevy, 'Housing Levy', '1', 30),
                $this->makePayslipEntry($payeeElement,      $paye,        'PAYE',         '1', 40),
            ],
            $extraDeductionEntries
        );

        // Add extra contract deductions to total
        foreach ($extraDeductionEntries as $entry) {
            $totalDeductions += $entry['amount'];
        }

        // Arrays to store deduction repeats info for logging
        $helbDeductionInfo = null;
        $proratedDeductionsInfo = [];

        // Handle HELB Loans (Post-tax deductions)
        $helbElement = PayrollElement::where('element_key', 'helb_loans')->first();
        $helbDeduction = HelbDeduction::where('staff_id', $staffId)
            ->where('cleared', 0)
            ->where('suspended', 0)
            ->where('start_deduction_date', '>=', $periodStartDate)
            ->first();

        if ($helbDeduction) {
            $helbAmount = (float) $helbDeduction->monthly_deduction;
            $totalDeductions += $helbAmount;
            $payslipElements[] = $this->makePayslipEntry($helbElement, $helbAmount, 'HELB Loan', '1', 50);

            $helbDeductionInfo = [
                'parent_id'        => $helbDeduction->id,
                'deduction_amount' => $helbAmount,
                'deduction_date'   => $periodStartDate,
            ];
        }

        // Handle Prorated/Amortized Deductions (Post-tax deductions)
        $proratedDeductions = ProratedDeduction::with('element')
            ->where('staff_id', $staffId)
            ->where('cleared', 0)
            ->where('suspended', 0)
            ->get();

        foreach ($proratedDeductions as $deduction) {
            if ($deduction->element) {
                $deductionAmount = (float) $deduction->monthly_deduction;
                $totalDeductions += $deductionAmount;
                $payslipElements[] = $this->makePayslipEntry($deduction->element, $deductionAmount);

                $proratedDeductionsInfo[] = [
                    'parent_id'        => $deduction->id,
                    'deduction_amount' => $deductionAmount,
                    'deduction_date'   => $periodStartDate,
                ];
            }
        }

        // Calculate net salary (after all deductions)
        $netSalary = $grossPay - $totalDeductions;

        return [
            'staff_id' => $staffId,
            'staff_number' => $staffNumber,
            'position_id' => $positionId,
            'department_id' => $departmentId,
            'employee_name' => $employeeName,
            'basic_salary' => $basicSalary,
            'gross_pay' => $grossPay,
            'nssf' => $nssf,
            'nssf_tier1' => $nssfTier1,
            'nssf_tier2' => $nssfTier2,
            'nhif' => $nhif,
            'housing_levy' => $housingLevy,
            'taxable_income' => $taxableIncome,
            'income_tax' => $incomeTaxBeforeRelief,
            'paye' => $paye,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'payslip_elements' => $payslipElements,
            'helb_deduction_info' => $helbDeductionInfo,
            'prorated_deductions_info' => $proratedDeductionsInfo,
        ];
    }

    /**
     * Calculate SHIF contribution (replaces NHIF)
     * Rate: 2.75% of gross monthly salary
     * Minimum: KES 300/month
     * No upper limit
     */
    private function calculateNHIF($grossPay)
    {
        $rate = 0.0275; // 2.75%
        $minContribution = 300;

        $shif = $grossPay * $rate;

        // Apply minimum contribution
        $shif = max($shif, $minContribution);

        return round($shif, 2);
    }

    /**
     * Calculate Affordable Housing Levy (AHL)
     * Employee rate: 1.5% of gross monthly salary
     * (Employer also contributes 1.5%, total 3%)
     */
    private function calculateHousingLevy($grossPay)
    {
        $rate = 0.015; // 1.5% employee contribution
        return round($grossPay * $rate, 2);
    }

    /**
     * Calculate PAYE (Income Tax) using tax bands from database
     * Tax is calculated progressively on each band
     */
    private function calculatePAYE($taxableIncome)
    {
        if ($taxableIncome <= 0) {
            return 0;
        }

        // Personal relief
        $personalRelief = 2400;
        $taxBands = TaxBand::orderBy('range_from', 'asc')->get();
        $totalTax = 0;

        foreach ($taxBands as $band) {
            // Skip if taxable income doesn't reach this band
            if ($taxableIncome < $band->range_from) {
                break;
            }

            // Determine how much income falls in this band
            $bandStart = $band->range_from;
            $bandEnd = $band->is_last ? $taxableIncome : min($band->range_to, $taxableIncome);

            // Income in this band
            $incomeInThisBand = $bandEnd - $bandStart;

            if ($incomeInThisBand > 0) {
                $taxRate = $band->tax_rate / 100;
                $taxInBand = $incomeInThisBand * $taxRate;
                $totalTax += $taxInBand;

                Log::info("Band: " . number_format($bandStart, 2) . " - " . number_format($bandEnd, 2) .
                    " @ {$band->tax_rate}% | Income in band: " . number_format($incomeInThisBand, 2) .
                    " | Tax: " . number_format($taxInBand, 2));
            }
        }

        Log::info("Total Tax before relief: " . number_format($totalTax, 2));
        Log::info("Personal Relief: " . number_format($personalRelief, 2));

        // Apply personal relief
        $finalTax = max(0, $totalTax - $personalRelief);

        Log::info("Final PAYE: " . number_format($finalTax, 2));

        return round($finalTax, 2);
    }
    /**
     * Save payroll summary to database
     */
    private function savePayrollSummary($payrollData, $startDate, $endDate, $periodId)
    {
        PayrollSummary::create([
            'date_time' => now(),
            'staff_id' => $payrollData['staff_id'],
            'staff_number_id' => $payrollData['staff_number'],
            'period' => $startDate,
            'period_id' => $periodId,
            'basic_salary' => $payrollData['basic_salary'],
            'total_earnings' => $payrollData['gross_pay'],
            'tax_payable' => $payrollData['paye'],
            'nhif' => $payrollData['nhif'],
            'nssf' => $payrollData['nssf'],
            'nssf_tier1' => $payrollData['nssf_tier1'],
            'nssf_tier2' => $payrollData['nssf_tier2'],
            'housing_levy' => $payrollData['housing_levy'],
            'total_deductions' => $payrollData['total_deductions'],
            'net_pay' => $payrollData['net_salary'],
            'current_status_id' => 5, // Approved
            'department_id' => $payrollData['department_id'],
            'position_id' => $payrollData['position_id'],
            'payslip_elements' => $payrollData['payslip_elements'],
        ]);

        // Log HELB deduction repeat if applicable
        if ($payrollData['helb_deduction_info']) {
            $helbInfo = $payrollData['helb_deduction_info'];
            $helbParent = HelbDeduction::find($helbInfo['parent_id']);

            if ($helbParent) {
                // Calculate new balance
                $newBalance = $helbParent->loan_balance - $helbInfo['deduction_amount'];

                // Create repeat entry
                HelbDeductionRepeat::create([
                    'date_time' => now(),
                    'parent_id' => $helbInfo['parent_id'],
                    'deduction_amount' => $helbInfo['deduction_amount'],
                    'deduction_date' => $helbInfo['deduction_date'],
                    'balance_amount' => max(0, $newBalance),
                    'created_by_id' => Auth::id(),
                ]);

                // Update parent loan balance
                $helbParent->loan_balance = max(0, $newBalance);

                // Mark as cleared if balance is zero or negative
                if ($newBalance <= 0) {
                    $helbParent->cleared = 1;
                }

                $helbParent->save();

                Log::info("HELB Repeat logged for parent_id {$helbInfo['parent_id']}: Deducted KSh " . number_format($helbInfo['deduction_amount'], 2) . ", New Balance: KSh " . number_format(max(0, $newBalance), 2));
            }
        }

        // Log prorated deduction repeats if applicable
        if (!empty($payrollData['prorated_deductions_info'])) {
            foreach ($payrollData['prorated_deductions_info'] as $proratedInfo) {
                $proratedParent = ProratedDeduction::find($proratedInfo['parent_id']);

                if ($proratedParent) {
                    // Calculate interest and principal portions
                    $totalAmount = $proratedParent->total_amount;
                    $initialDeduction = $proratedParent->initial_deduction ?? 0;
                    $remainingAmount = $totalAmount - $initialDeduction;

                    // Get previous repeats to calculate balance
                    $totalDeducted = ProratedDeductionRepeat::where('parent_id', $proratedInfo['parent_id'])
                        ->sum('total_amount');

                    $currentBalance = $remainingAmount - $totalDeducted;
                    $deductionAmount = $proratedInfo['deduction_amount'];

                    // Calculate proportional principal and interest
                    $principalRatio = $proratedParent->principal_amount / $totalAmount;
                    $principalPortion = $deductionAmount * $principalRatio;
                    $interestPortion = $deductionAmount - $principalPortion;

                    $newBalance = $currentBalance - $deductionAmount;

                    // Create repeat entry
                    ProratedDeductionRepeat::create([
                        'date_time' => now(),
                        'parent_id' => $proratedInfo['parent_id'],
                        'principal_amount' => $principalPortion,
                        'interest_amount' => $interestPortion,
                        'total_amount' => $deductionAmount,
                        'balance_amount' => max(0, $newBalance),
                        'deduction_date' => $proratedInfo['deduction_date'],
                        'created_by_id' => Auth::id(),
                    ]);

                    // Mark as cleared if balance is zero or negative
                    if ($newBalance <= 0) {
                        $proratedParent->cleared = 1;
                        $proratedParent->save();
                    }

                    Log::info("Prorated Repeat logged for parent_id {$proratedInfo['parent_id']}: Deducted KSh " . number_format($deductionAmount, 2) . " (Principal: " . number_format($principalPortion, 2) . ", Interest: " . number_format($interestPortion, 2) . "), New Balance: KSh " . number_format(max(0, $newBalance), 2));
                }
            }
        }
    }
}
