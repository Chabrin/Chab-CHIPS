<?php

namespace App\Http\Controllers;

use App\Models\ControlCategory;
use App\Models\SystemControl;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;

class SystemControlController extends Controller
{
    // show categories management view
    public function categories()
    {
        return view('settings.control_categories');
    }

    // return categories as JSON for DataTable
    public function ajaxListCategories()
    {
        try {
            $categories = ControlCategory::orderBy('precedence', 'asc')->get();
            return response()->json(['categories' => $categories]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['categories' => [], 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // store or update category
    public function ajaxStoreCategory(Request $r)
    {
        try {
            $data = $r->validate([
                'category_name' => 'required|string|max:50',
                'display_name' => 'required|string|max:50',
                'precedence'   => 'required|integer'
            ]);
            $id = $r->input('id');
            if ($id) {
                $cat = ControlCategory::find($id);
                if (!$cat) return response()->json(['success' => false, 'error' => 'Not found'], 404);
                $cat->update($data);
            } else {
                ControlCategory::create($data);
            }
            return response()->json(['success' => true]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['success' => false, 'errors' => $ve->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteCategory($id)
    {
        try {
            $cat = ControlCategory::find($id);
            if (!$cat) return response()->json(['success' => false, 'error' => 'Not found'], 404);
            // consider cascading delete of controls
            $cat->controls()->delete();
            $cat->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // show system controls view (tabbed by category)
    public function index()
    {
        $categories = ControlCategory::orderBy('precedence')->get();
        return view('settings.cb_system_configs', compact('categories'));
    }

    // ajax list controls for a category
    public function ajaxListControls(Request $r)
    {
        try {
            $categoryId = $r->get('category_id');
            $controls = SystemControl::where('category_id', $categoryId)
                ->orderBy('precedence')
                ->get();
            return response()->json(['controls' => $controls]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('Error dispatch failed: ' . $ex->getMessage());
            }
            return response()->json(['controls' => [], 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // store or update a system control
    public function ajaxStoreControl(Request $r)
    {
        try {
            // support bulk updates (e.g., toggles) via 'bulk' JSON payload
            if ($r->filled('bulk')) {
                $bulk = json_decode($r->input('bulk'), true);
                if (!is_array($bulk)) return response()->json(['success' => false, 'error' => 'Invalid payload'], 422);
                foreach ($bulk as $item) {
                    if (empty($item['id'])) continue;
                    $c = SystemControl::find($item['id']);
                    if (!$c) continue;
                    // if value provided in bulk, update it
                    if (array_key_exists('value', $item) && $item['value'] !== null) {
                        $c->value = (string)$item['value'];
                        $c->save();
                        continue;
                    }
                    // if toggle is provided, update value (store as '1' or '0')
                    if (array_key_exists('toggle', $item) && $item['toggle'] !== null) {
                        $c->value = $item['toggle'] ? '1' : '0';
                        $c->save();
                    }
                }
                return response()->json(['success' => true]);
            }

            $data = $r->validate([
                'category_id' => 'required|integer',
                'display_name' => 'required|string|max:50',
                'precedence' => 'nullable|integer',
                'value' => 'nullable|string',
                'setting' => 'required|string|max:50',
                'toggle_boolean' => 'nullable|integer'
            ]);

            // normalize toggle_boolean
            $data['toggle_boolean'] = $r->has('toggle_boolean') && intval($r->input('toggle_boolean')) === 1 ? 1 : 0;
            // ensure value is string
            if (!isset($data['value'])) $data['value'] = '';

            $id = $r->input('id');
            if ($id) {
                $c = SystemControl::find($id);
                if (!$c) return response()->json(['success' => false, 'error' => 'Not found'], 404);
                $c->update($data);
            } else {
                SystemControl::create($data);
            }
            return response()->json(['success' => true]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['success' => false, 'errors' => $ve->errors()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteControl($id)
    {
        try {
            $c = SystemControl::find($id);
            if (!$c) return response()->json(['success' => false, 'error' => 'Not found'], 404);
            $c->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
