<?php

namespace App\Http\Controllers;

use App\Models\LeaseContractTemplate;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeaseContractTemplateController extends Controller
{
    public function index()
    {
        try {
            $templates = LeaseContractTemplate::with('createdBy:id,name')
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return view('property.lease-settings.contract-templates.index', compact('templates'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading contract templates: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load contract templates.');
        }
    }

    public function create()
    {
        return view('property.lease-settings.contract-templates.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'html_body'     => 'required|string',
            'is_default'    => 'nullable|boolean',
        ]);

        try {
            $isDefault = !empty($validated['is_default']);

            if ($isDefault) {
                LeaseContractTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            LeaseContractTemplate::create([
                'template_name'    => $validated['template_name'],
                'description'      => $validated['description'] ?? null,
                'html_body'        => $validated['html_body'],
                'is_default'       => $isDefault,
                'current_status_id' => 1,
                'created_by_id'    => Auth::id(),
                'date_time'        => now(),
            ]);

            return redirect()
                ->route('property.lease-settings.contract-templates.index')
                ->with('success', 'Contract template created successfully.');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating contract template: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create template.')->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $template = LeaseContractTemplate::findOrFail($id);
            return view('property.lease-settings.contract-templates.edit', compact('template'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Template not found.');
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'html_body'     => 'required|string',
            'is_default'    => 'nullable|boolean',
        ]);

        try {
            $template = LeaseContractTemplate::findOrFail($id);
            $isDefault = !empty($validated['is_default']);

            if ($isDefault && !$template->is_default) {
                LeaseContractTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template->update([
                'template_name' => $validated['template_name'],
                'description'   => $validated['description'] ?? null,
                'html_body'     => $validated['html_body'],
                'is_default'    => $isDefault,
            ]);

            return redirect()
                ->route('property.lease-settings.contract-templates.index')
                ->with('success', 'Contract template updated successfully.');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating contract template: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update template.')->withInput();
        }
    }

    public function setDefault($id)
    {
        try {
            LeaseContractTemplate::where('is_default', true)->update(['is_default' => false]);
            LeaseContractTemplate::findOrFail($id)->update(['is_default' => true]);

            return response()->json(['success' => true, 'message' => 'Default template updated.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error setting default template: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to set default.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $template = LeaseContractTemplate::findOrFail($id);

            if ($template->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the default template. Set another template as default first.',
                ], 422);
            }

            $template->delete();

            return response()->json(['success' => true, 'message' => 'Template deleted successfully.']);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting contract template: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete template.'], 500);
        }
    }
}
