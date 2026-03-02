<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;

class UtilityController extends Controller
{
    /**
     * Search tenants (AJAX)
     * Common search functionality used across the application
     */
    public function searchTenants(Request $request)
    {
        try {
            $searchTerm = $request->input('search', '');

            if (strlen($searchTerm) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter at least 2 characters to search',
                    'tenants' => []
                ]);
            }

            $tenants = Client::select([
                'id',
                'names',
                'mobile_number',
                'national_id',
                'reference_number',
                'email_address'
            ])
                ->tenants()
                ->where(function ($query) use ($searchTerm) {
                    $query->where('names', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('mobile_number', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('national_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('reference_number', 'LIKE', "%{$searchTerm}%");
                })
                ->limit(50)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'encrypted_id' => $tenant->encrypted_id,
                        'names' => $tenant->names,
                        'mobile_number' => $tenant->mobile_number,
                        'national_id' => $tenant->national_id,
                        'reference_number' => $tenant->reference_number,
                        'email_address' => $tenant->email_address,
                    ];
                });

            return response()->json([
                'success' => true,
                'tenants' => $tenants,
                'count' => $tenants->count()
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching tenants: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Search failed',
                'message' => 'An error occurred while searching tenants'
            ], 500);
        }
    }

    public function getTenantDetails($id)
    {
        try {
            $tenant = Client::with(['property', 'unit', 'lease.property', 'lease.unit'])
                ->where('id', $id)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }

            // Get property and unit from lease if available, otherwise from client
            $propertyName = null;
            $propertyId = null;
            $unitName = null;
            $unitId = null;

            if ($tenant->lease) {
                $propertyId = $tenant->lease->property_id;
                $unitId = $tenant->lease->unit_id;

                if ($tenant->lease->property) {
                    $propertyName = $tenant->lease->property->property_name;
                }
                if ($tenant->lease->unit) {
                    $unitName = $tenant->lease->unit->unit_name;
                }
            } else {
                // Fallback to client's property/unit if no lease
                $propertyId = $tenant->property_id;
                $unitId = $tenant->unit_id;

                if ($tenant->property) {
                    $propertyName = $tenant->property->property_name;
                }
                if ($tenant->unit) {
                    $unitName = $tenant->unit->unit_name;
                }
            }

            return response()->json([
                'success' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'names' => $tenant->names,
                    'mobile_number' => $tenant->mobile_number,
                    'email' => $tenant->email_address,
                    'national_id' => $tenant->national_id,
                    'property_id' => $propertyId,
                    'property_name' => $propertyName,
                    'unit_id' => $unitId,
                    'unit_name' => $unitName,
                    'lease_id' => $tenant->lease ? $tenant->lease->id : null,
                    'lease_reference' => $tenant->lease ? $tenant->lease->ref_number : null,
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error fetching tenant details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching tenant details'
            ], 500);
        }
    }
}
