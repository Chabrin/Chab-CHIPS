<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SystemCountry;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CountryController extends Controller
{
    public function index()
    {
        if (!Auth::user()->hasPermission('read_countries')) {
            abort(403);
        }

        return view('manage.countries.index');
    }

    public function getData()
    {
        try {
            $canEdit = Auth::user()->hasPermission('update_countries');
            $canDelete = Auth::user()->hasPermission('delete_countries');

            $countries = SystemCountry::orderBy('country_name')
                ->get()
                ->map(function ($country) use ($canEdit, $canDelete) {
                    return [
                        'id' => $country->id,
                        'country_code' => $country->country_code,
                        'country_name' => $country->country_name,
                        'can_edit' => $canEdit,
                        'can_delete' => $canDelete,
                    ];
                });

            return response()->json(['data' => $countries]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading countries: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load countries'], 500);
        }
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasPermission('create_countries')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'country_code' => 'required|string|max:10|unique:sys_set_countries,country_code',
                'country_name' => 'required|string|max:255|unique:sys_set_countries,country_name',
            ]);

            $country = SystemCountry::create($validated);

            return response()->json(['success' => true, 'id' => $country->id]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating country: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to create country'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->hasPermission('update_countries')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            $country = SystemCountry::findOrFail($id);

            $validated = $request->validate([
                'country_code' => 'required|string|max:10|unique:sys_set_countries,country_code,' . $id,
                'country_name' => 'required|string|max:255|unique:sys_set_countries,country_name,' . $id,
            ]);

            $country->update($validated);

            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating country: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update country'], 500);
        }
    }

    public function destroy($id)
    {
        if (!Auth::user()->hasPermission('delete_countries')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            $country = SystemCountry::findOrFail($id);

            if (Client::where('nationality_id', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'This country is assigned to one or more clients/tenants and cannot be deleted.',
                ], 422);
            }

            $country->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting country: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete country'], 500);
        }
    }
}
