<?php

namespace App\Http\Controllers;

use App\Models\TaxBand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;

class TaxController extends Controller
{
    /**
     * Display a listing of tax bands
     */
    public function index()
    {
        try {
            return view('tax.index');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax bands index: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load tax bands');
        }
    }

    /**
     * Get tax bands data for DataTable
     */
    public function getData(Request $request)
    {
        try {
            $taxBands = TaxBand::ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $taxBands->map(function ($band) {
                    return [
                        'id' => $band->id,
                        'range_display' => $band->range_display,
                        'tax_rate_display' => $band->tax_rate_display,
                        'range_from' => $band->range_from,
                        'range_to' => $band->range_to,
                        'tax_rate' => $band->tax_rate,
                        'created_at' => $band->created_at->format('M d, Y'),
                        'updated_at' => $band->updated_at->format('M d, Y')
                    ];
                })
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching tax bands data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to load tax bands data'
            ], 500);
        }
    }

    /**
     * Store a newly created tax band
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'range_from' => 'required|numeric|min:0',
                'range_to' => 'nullable|numeric|gt:range_from',
                'tax_rate' => 'required|numeric|min:0|max:100'
            ]);

            // Check for overlapping ranges
            $this->validateNoOverlap($validated['range_from'], $validated['range_to']);

            DB::beginTransaction();

            try {
                $taxBand = new TaxBand();
                $taxBand->range_from = $validated['range_from'];
                $taxBand->range_to = $validated['range_to'];
                $taxBand->tax_rate = $validated['tax_rate'];
                $taxBand->added_by = Auth::user()->username ?? 'system';
                $taxBand->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Tax band created successfully',
                    'data' => $taxBand
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating tax band: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to create tax band. Please try again.'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified tax band
     */
    public function show($id)
    {
        try {
            $taxBand = TaxBand::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $taxBand
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching tax band: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Tax band not found'
            ], 404);
        }
    }

    /**
     * Update the specified tax band
     */
    public function update(Request $request, $id)
    {
        try {
            $taxBand = TaxBand::findOrFail($id);

            $validated = $request->validate([
                'range_from' => 'required|numeric|min:0',
                'range_to' => 'nullable|numeric|gt:range_from',
                'tax_rate' => 'required|numeric|min:0|max:100'
            ]);

            // Check for overlapping ranges (excluding current record)
            $this->validateNoOverlap($validated['range_from'], $validated['range_to'], $id);

            DB::beginTransaction();

            try {
                $taxBand->range_from = $validated['range_from'];
                $taxBand->range_to = $validated['range_to'];
                $taxBand->tax_rate = $validated['tax_rate'];
                $taxBand->updated_by = Auth::user()->username ?? 'system';
                $taxBand->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Tax band updated successfully',
                    'data' => $taxBand
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating tax band: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to update tax band. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified tax band
     */
    public function destroy($id)
    {
        try {
            $taxBand = TaxBand::findOrFail($id);

            DB::beginTransaction();

            try {
                $taxBand->delete();
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Tax band deleted successfully'
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting tax band: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete tax band. Please try again.'
            ], 500);
        }
    }

    /**
     * Validate that the new range doesn't overlap with existing ranges
     */
    private function validateNoOverlap($rangeFrom, $rangeTo, $excludeId = null)
    {
        $query = TaxBand::where(function ($q) use ($rangeFrom, $rangeTo) {
            if ($rangeTo) {
                // New range has an end, check for any overlap
                $q->where(function ($subQ) use ($rangeFrom, $rangeTo) {
                    // Case 1: Existing range starts within new range
                    $subQ->whereBetween('range_from', [$rangeFrom, $rangeTo])
                        // Case 2: Existing range ends within new range  
                        ->orWhereBetween('range_to', [$rangeFrom, $rangeTo])
                        // Case 3: New range is completely within existing range
                        ->orWhere(function ($innerQ) use ($rangeFrom, $rangeTo) {
                            $innerQ->where('range_from', '<=', $rangeFrom)
                                ->where('range_to', '>=', $rangeTo);
                        })
                        // Case 4: Existing range is completely within new range
                        ->orWhere(function ($innerQ) use ($rangeFrom, $rangeTo) {
                            $innerQ->where('range_from', '>=', $rangeFrom)
                                ->where('range_to', '<=', $rangeTo);
                        });
                });
            } else {
                // New range has no end (goes to infinity), check if any existing range starts from this point or higher
                $q->where('range_from', '>=', $rangeFrom)
                    ->orWhere(function ($subQ) use ($rangeFrom) {
                        $subQ->where('range_from', '<=', $rangeFrom)
                            ->where(function ($innerQ) use ($rangeFrom) {
                                $innerQ->whereNull('range_to')
                                    ->orWhere('range_to', '>=', $rangeFrom);
                            });
                    });
            }
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $overlapping = $query->first();

        if ($overlapping) {
            $rangeDisplay = $rangeFrom . ' - ' . ($rangeTo ?? '∞');
            $existingDisplay = $overlapping->range_from . ' - ' . ($overlapping->range_to ?? '∞');

            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                [
                    'range_from' => ["Range {$rangeDisplay} overlaps with existing range {$existingDisplay}"]
                ]
            );
        }
    }
}
