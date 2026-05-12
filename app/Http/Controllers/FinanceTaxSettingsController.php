<?php

namespace App\Http\Controllers;

use App\Models\TaxDefaultType;
use App\Models\TaxComputation;
use App\Models\TaxCategory;
use App\Models\ChartAccount;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceTaxSettingsController extends Controller
{
    // Default Types CRUD
    public function defaultTypesIndex()
    {
        try {
            return view('finance.tax-settings.default-types');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax default types page: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load tax default types');
        }
    }

    public function defaultTypesList()
    {
        try {
            $types = TaxDefaultType::with('createdBy')->orderBy('id')->get();
            return response()->json(['success' => true, 'data' => $types->map(function ($type) {
                return [
                    'id' => $type->id,
                    'type_name' => $type->type_name,
                    'current_status_id' => $type->current_status_id,
                    'created_by' => optional($type->createdBy)->name,
                    'created_at' => optional($type->date_time)->format('Y-m-d H:i:s'),
                ];
            })]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax default types: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load'], 500);
        }
    }

    public function defaultTypesStore(Request $request)
    {
        try {
            $validated = $request->validate(['type_name' => 'required|string|max:255']);
            
            $type = new TaxDefaultType();
            $type->type_name = $validated['type_name'];
            $type->current_status_id = 5;
            $type->created_by_id = Auth::id();
            $type->date_time = now();
            $type->save();

            return response()->json(['success' => true, 'message' => 'Tax default type created']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing tax default type: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    public function defaultTypesShow($id)
    {
        try {
            $type = TaxDefaultType::findOrFail($id);
            return response()->json(['success' => true, 'data' => [
                'id' => $type->id,
                'type_name' => $type->type_name,
                'current_status_id' => $type->current_status_id,
            ]]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
    }

    public function defaultTypesUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type_name' => 'required|string|max:255',
                'current_status_id' => 'required|integer',
            ]);
            $type = TaxDefaultType::findOrFail($id);
            $type->type_name = $validated['type_name'];
            $type->current_status_id = $validated['current_status_id'];
            $type->save();
            return response()->json(['success' => true, 'message' => 'Tax default type updated']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating tax default type: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update'], 500);
        }
    }

    public function defaultTypesDelete($id)
    {
        try {
            TaxDefaultType::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Tax default type deleted']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting tax default type: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete'], 500);
        }
    }

    // Computations CRUD
    public function computationsIndex()
    {
        try {
            return view('finance.tax-settings.computations');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax computations page: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load tax computations');
        }
    }

    public function computationsList()
    {
        try {
            $computations = TaxComputation::with('createdBy')->orderBy('id')->get();
            return response()->json(['success' => true, 'data' => $computations->map(function ($comp) {
                return [
                    'id' => $comp->id,
                    'computation_name' => $comp->computation_name,
                    'current_status_id' => $comp->current_status_id,
                    'created_by' => optional($comp->createdBy)->name,
                    'created_at' => optional($comp->date_time)->format('Y-m-d H:i:s'),
                ];
            })]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax computations: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load'], 500);
        }
    }

    public function computationsStore(Request $request)
    {
        try {
            $validated = $request->validate(['computation_name' => 'required|string|max:255']);
            
            $comp = new TaxComputation();
            $comp->computation_name = $validated['computation_name'];
            $comp->current_status_id = 5;
            $comp->created_by_id = Auth::id();
            $comp->date_time = now();
            $comp->save();

            return response()->json(['success' => true, 'message' => 'Tax computation created']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing tax computation: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    public function computationsShow($id)
    {
        try {
            $comp = TaxComputation::findOrFail($id);
            return response()->json(['success' => true, 'data' => [
                'id' => $comp->id,
                'computation_name' => $comp->computation_name,
                'current_status_id' => $comp->current_status_id,
            ]]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
    }

    public function computationsUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'computation_name' => 'required|string|max:255',
                'current_status_id' => 'required|integer',
            ]);
            $comp = TaxComputation::findOrFail($id);
            $comp->computation_name = $validated['computation_name'];
            $comp->current_status_id = $validated['current_status_id'];
            $comp->save();
            return response()->json(['success' => true, 'message' => 'Tax computation updated']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating tax computation: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update'], 500);
        }
    }

    public function computationsDelete($id)
    {
        try {
            TaxComputation::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Tax computation deleted']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting tax computation: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete'], 500);
        }
    }

    // Categories CRUD
    public function categoriesIndex()
    {
        try {
            return view('finance.tax-settings.categories');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax categories page: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load tax categories');
        }
    }

    public function categoriesList()
    {
        try {
            $categories = TaxCategory::with('createdBy')->orderBy('id')->get();
            return response()->json(['success' => true, 'data' => $categories->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'category_name' => $cat->category_name,
                    'current_status_id' => $cat->current_status_id,
                    'created_by' => optional($cat->createdBy)->name,
                    'created_at' => optional($cat->date_time)->format('Y-m-d H:i:s'),
                ];
            })]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax categories: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load'], 500);
        }
    }

    public function categoriesStore(Request $request)
    {
        try {
            $validated = $request->validate(['category_name' => 'required|string|max:255']);
            
            $cat = new TaxCategory();
            $cat->category_name = $validated['category_name'];
            $cat->current_status_id = 5;
            $cat->created_by_id = Auth::id();
            $cat->date_time = now();
            $cat->save();

            return response()->json(['success' => true, 'message' => 'Tax category created']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error storing tax category: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    public function categoriesShow($id)
    {
        try {
            $cat = TaxCategory::findOrFail($id);
            return response()->json(['success' => true, 'data' => [
                'id' => $cat->id,
                'category_name' => $cat->category_name,
                'current_status_id' => $cat->current_status_id,
            ]]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
    }

    public function categoriesUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'category_name' => 'required|string|max:255',
                'current_status_id' => 'required|integer',
            ]);
            $cat = TaxCategory::findOrFail($id);
            $cat->category_name = $validated['category_name'];
            $cat->current_status_id = $validated['current_status_id'];
            $cat->save();
            return response()->json(['success' => true, 'message' => 'Tax category updated']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating tax category: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to update'], 500);
        }
    }

    public function categoriesDelete($id)
    {
        try {
            TaxCategory::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Tax category deleted']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting tax category: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete'], 500);
        }
    }

    // Get all options for dropdowns
    public function getOptions()
    {
        try {
            $defaultTypes = TaxDefaultType::where('current_status_id', 5)->orderBy('type_name')->get()->map(function($item) {
                return ['id' => $item->id, 'type_name' => $item->type_name];
            });
            $computations = TaxComputation::where('current_status_id', 5)->orderBy('computation_name')->get()->map(function($item) {
                return ['id' => $item->id, 'computation_name' => $item->computation_name];
            });
            $categories = TaxCategory::where('current_status_id', 5)->orderBy('category_name')->get()->map(function($item) {
                return ['id' => $item->id, 'category_name' => $item->category_name];
            });

            return response()->json(['success' => true, 'data' => [
                'default_types' => $defaultTypes,
                'computations' => $computations,
                'categories' => $categories
            ]]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading tax options: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to load options'], 500);
        }
    }
}
