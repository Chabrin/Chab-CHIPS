<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Module;
use App\Models\Submodule;
use App\Models\SuperModule;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorDispatcher;

class ModuleController extends Controller
{
    public function addModule()
    {
        return view('modules.addmodule');
    }

    public function storeModule(Request $request)
    {
        try {
            $validated = $request->validate([
                'module_name' => 'required|string|max:255',
                'icon' => 'nullable|string|max:255',
            ]);

            // Check for duplicate module name
            $exists = Module::where('module_name', $validated['module_name'])
                ->where('deleted', 0)
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A module with this name already exists.']);
            }

            $maxOrder = Module::max('module_order');
            $nextOrder = $maxOrder ? $maxOrder + 1 : 1;
            $user = Auth::user();
            $module = Module::create([
                'module_name' => $validated['module_name'],
                'icon' => $validated['icon'] ?? null,
                'module_order' => $nextOrder,
                'added_by' => $user ? $user->username : null,
                'added_on' => now(),
            ]);
            return response()->json(['success' => true, 'message' => 'Module created successfully!', 'id' => $module->id]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding module: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function addSubmodule()
    {
        $modules = Module::where('deleted', 0)->orderBy('module_order')->get();
        return view('modules.addsubmodule', compact('modules'));
    }

    public function storeSubmodule(Request $request)
    {
        try {
            $validated = $request->validate([
                'sub_module_name' => 'required|string|max:255',
                'sub_module_url' => 'nullable|string|max:255',
                'module_id' => 'required|exists:cb_modules,id',
            ]);
            // Manual duplicate check
            $exists = Submodule::where('sub_module_name', $validated['sub_module_name'])
                ->where('module_id', $validated['module_id'])
                ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'A submodule with this name already exists for the selected module.']);
            }
            $maxOrder = Submodule::where('module_id', $validated['module_id'])->max('order');
            $nextOrder = $maxOrder ? $maxOrder + 1 : 1;
            $user = Auth::user();
            $submodule = Submodule::create([
                'sub_module_name' => $validated['sub_module_name'],
                'sub_module_url' => $validated['sub_module_url'] ?? null,
                'module_id' => $validated['module_id'],
                'order' => $nextOrder,
                'added_by' => $user ? $user->username : null,
                'added_on' => now(),
            ]);
            return response()->json(['success' => true, 'message' => 'Submodule created successfully!', 'id' => $submodule->id]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error adding submodule: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function mapper()
    {
        $superModules = SuperModule::orderBy('order')->get();
        $modules = Module::where('deleted', 0)->orderBy('module_order')->get();
        return view('modules.mapper', compact('superModules', 'modules'));
    }

    public function saveMapper(Request $request)
    {
        try {
            $mappings = $request->input('mappings', []);

            if (empty($mappings)) {
                return response()->json(['success' => false, 'error' => 'No mappings provided.']);
            }

            foreach ($mappings as $mapping) {
                $moduleId = (int) ($mapping['module_id'] ?? 0);
                $superModuleId = !empty($mapping['super_module_id']) ? (int) $mapping['super_module_id'] : null;

                if ($moduleId > 0) {
                    Module::where('id', $moduleId)
                        ->update(['super_module_id' => $superModuleId]);
                }
            }

            return response()->json(['success' => true, 'message' => 'Module mappings saved successfully!']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error saving module mappings: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
