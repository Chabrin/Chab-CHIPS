<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Vendor CRUD (/system/vendors). List + create on the index page; a
 * tab-based details page (Overview — editable vendor_code/vendor_name/
 * vendor_type/address — and Bank Details — editable bank_name/bank_branch/
 * account_number, the same single set of bank fields already on the vendors
 * table, not a separate repeatable-accounts table). AJAX throughout, same
 * per-column-search-box + debounce convention already used by the Tenant
 * Listing and Tenant Arrears pages.
 */
class VendorController extends Controller
{
    private const VENDOR_TYPES = [
        'LAN' => 'Landlord',
        'OTH' => 'Other',
    ];

    public function index()
    {
        try {
            return view('system.vendors.index', ['vendorTypes' => self::VENDOR_TYPES]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading vendors page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load vendors page');
        }
    }

    /**
     * AJAX list data. Per-column ILIKE filters (3+ chars), capped at 1000
     * rows — the whole table is currently ~1,100 rows, so this comfortably
     * covers it while staying consistent with the same cap used elsewhere.
     */
    public function data(Request $request)
    {
        try {
            $query = Vendor::query();

            if ($request->filled('filter_code') && strlen($request->filter_code) >= 3) {
                $query->where('vendor_code', 'ILIKE', '%' . $request->filter_code . '%');
            }
            if ($request->filled('filter_name') && strlen($request->filter_name) >= 3) {
                $query->where('vendor_name', 'ILIKE', '%' . $request->filter_name . '%');
            }
            if ($request->filled('filter_type')) {
                $query->where('vendor_type', $request->filter_type);
            }
            if ($request->filled('filter_status')) {
                $query->where('status', $request->filter_status);
            }

            $vendors = $query->orderBy('vendor_name')
                ->limit(1000)
                ->get()
                ->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'encrypted_id' => $v->encrypted_id,
                        'vendor_code' => $v->vendor_code,
                        'vendor_name' => $v->vendor_name ?? '-',
                        'vendor_type' => self::VENDOR_TYPES[$v->vendor_type] ?? ($v->vendor_type ?? '-'),
                        'account_number' => $v->account_number ?? '-',
                        'current_balance' => number_format($v->current_balance ?? 0, 2),
                        'status' => $v->status,
                    ];
                });

            return response()->json(['success' => true, 'count' => $vendors->count(), 'data' => $vendors]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching vendors data: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch vendors'], 500);
        }
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasPermission('create_vendors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'vendor_code' => 'required|string|max:255|unique:vendors,vendor_code',
                'vendor_name' => 'required|string|max:255',
                'vendor_type' => 'required|string|in:' . implode(',', array_keys(self::VENDOR_TYPES)),
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'address_line_3' => 'nullable|string|max:255',
            ]);

            $validated['status'] = 'Active';
            $validated['created_by'] = Auth::id();
            $validated['audit_user'] = Auth::user()->username ?? Auth::user()->name ?? null;
            $validated['last_maintained_at'] = now();

            $vendor = Vendor::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Vendor created successfully.',
                'encrypted_id' => $vendor->encrypted_id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating vendor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create vendor'], 500);
        }
    }

    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $vendor = Vendor::findOrFail($id);

            return view('system.vendors.details', ['vendor' => $vendor, 'vendorTypes' => self::VENDOR_TYPES]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading vendor details: ' . $e->getMessage());
            return redirect()->route('vendors.index')->with('error', 'Vendor not found');
        }
    }

    /**
     * Overview tab — vendor_code/vendor_name/vendor_type/address_line_1-3.
     */
    public function update(Request $request, $encryptedId)
    {
        if (!Auth::user()->hasPermission('update_vendors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $id = Crypt::decrypt($encryptedId);
            $vendor = Vendor::findOrFail($id);

            $validated = $request->validate([
                'vendor_code' => 'required|string|max:255|unique:vendors,vendor_code,' . $vendor->id,
                'vendor_name' => 'required|string|max:255',
                'vendor_type' => 'required|string|in:' . implode(',', array_keys(self::VENDOR_TYPES)),
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'address_line_3' => 'nullable|string|max:255',
            ]);

            $validated['audit_user'] = Auth::user()->username ?? Auth::user()->name ?? null;
            $validated['last_maintained_at'] = now();

            $vendor->update($validated);

            return response()->json(['success' => true, 'message' => 'Vendor updated successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating vendor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update vendor'], 500);
        }
    }

    /**
     * Bank Details tab — the single bank_name/bank_branch/account_number
     * set already on the vendors table.
     */
    public function updateBankDetails(Request $request, $encryptedId)
    {
        if (!Auth::user()->hasPermission('update_vendors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $id = Crypt::decrypt($encryptedId);
            $vendor = Vendor::findOrFail($id);

            $validated = $request->validate([
                'bank_name' => 'nullable|string|max:255',
                'bank_branch' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:255',
            ]);

            $validated['audit_user'] = Auth::user()->username ?? Auth::user()->name ?? null;
            $validated['last_maintained_at'] = now();

            $vendor->update($validated);

            return response()->json(['success' => true, 'message' => 'Bank details updated successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating vendor bank details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update bank details'], 500);
        }
    }

    public function destroy($encryptedId)
    {
        if (!Auth::user()->hasPermission('delete_vendors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $id = Crypt::decrypt($encryptedId);
            $vendor = Vendor::findOrFail($id);
            $vendor->delete();

            return response()->json(['success' => true, 'message' => 'Vendor deleted successfully.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting vendor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete vendor'], 500);
        }
    }
}
