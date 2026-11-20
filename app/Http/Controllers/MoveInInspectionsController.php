<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MoveInInspection;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class MoveInInspectionsController extends Controller
{
    private const CONDITIONS = ['Excellent', 'Good', 'Fair', 'Poor'];

    /**
     * List all move-in inspections on record.
     */
    public function index()
    {
        return view('property.tenants.move_in_inspections.index');
    }

    /**
     * Move-in inspections DataTable source (AJAX).
     */
    public function getInspections()
    {
        try {
            $inspections = MoveInInspection::with([
                    'tenant:id,names,reference_number',
                    'property:id,property_name',
                    'unit:id,unit_name,unit_number',
                    'conductedBy:id,name',
                ])
                ->orderBy('inspection_date', 'desc')
                ->get()
                ->map(function ($inspection) {
                    return [
                        'id' => $inspection->id,
                        'encrypted_id' => Crypt::encrypt($inspection->id),
                        'tenant_name' => $inspection->tenant->names ?? '-',
                        'property_name' => $inspection->property->property_name ?? '-',
                        'unit_name' => $inspection->unit->unit_name ?? $inspection->unit->unit_number ?? '-',
                        'inspection_date' => $inspection->inspection_date ? $inspection->inspection_date->format('d-m-Y') : '-',
                        'inspector_name' => $inspection->inspector_name ?? '-',
                        'overall_condition' => $inspection->overall_condition ?? '-',
                        'conducted_by' => $inspection->conductedBy->name ?? '-',
                    ];
                });

            return response()->json(['success' => true, 'data' => $inspections]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading move-in inspections: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load inspections'], 500);
        }
    }

    /**
     * Show the form to record a move-in inspection for an existing tenant —
     * for onboardings where it was skipped/forgotten. Accepts an optional
     * ?tenant= (encrypted id) to pre-select, e.g. when linked from the
     * tenant listing page.
     */
    public function create(Request $request)
    {
        $selectedTenant = null;

        if ($request->filled('tenant')) {
            try {
                $id = Crypt::decrypt($request->input('tenant'));
                $selectedTenant = Client::tenants()->findOrFail($id);
            } catch (\Exception $e) {
                $selectedTenant = null;
            }
        }

        return view('property.tenants.move_in_inspections.create', compact('selectedTenant'));
    }

    /**
     * Searchable tenant list for the create form's Select2 (AJAX) — the
     * tenants table is large, so this is search-scoped rather than a full
     * preloaded list. Each result carries the tenant's own property/unit/SLA
     * so the form can auto-fill them (a move-in inspection is always for the
     * unit the tenant is actually recorded as occupying).
     */
    public function tenantsForPicker(Request $request)
    {
        try {
            $query = Client::tenants()->select(['id', 'names', 'reference_number', 'property_id', 'unit_id', 'sla_id']);

            if ($request->filled('term')) {
                $term = $request->input('term');
                $query->where(function ($q) use ($term) {
                    $q->where('names', 'ILIKE', "%{$term}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$term}%");
                });
            }

            $tenants = $query->orderBy('names')->limit(50)->get()->map(function ($tenant) {
                return [
                    'id' => Crypt::encrypt($tenant->id),
                    'text' => $tenant->names . ($tenant->reference_number ? ' (' . $tenant->reference_number . ')' : ''),
                    'has_unit' => (bool) ($tenant->property_id && $tenant->unit_id),
                ];
            });

            return response()->json(['results' => $tenants]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error searching tenants for move-in inspection: ' . $e->getMessage());
            return response()->json(['results' => []], 500);
        }
    }

    /**
     * Store a move-in inspection recorded after the fact. Mirrors
     * TenantsController::saveMoveInInspection() (the onboarding-time path),
     * except property/unit/SLA are taken from the selected tenant's own
     * record rather than an in-progress onboarding form.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|string',
                'inspection_date' => 'required|date',
                'inspector_name' => 'nullable|string|max:255',
                'walls_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'floors_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'ceiling_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'doors_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'windows_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'plumbing_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'electrical_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'overall_condition' => 'nullable|in:' . implode(',', self::CONDITIONS),
                'inspection_notes' => 'nullable|string',
                'inspection_photos.*' => 'nullable|image|mimes:jpeg,png,webp,heic,heif|max:10240',
                'inspection_videos.*' => 'nullable|file|mimes:mp4,mov,avi,mkv|max:51200',
            ]);

            $tenantId = Crypt::decrypt($validated['tenant_id']);
            app(\App\Services\TenantReviewService::class)->lockForCorrection((int) $tenantId, $request->user());
            $tenant = Client::tenants()->findOrFail($tenantId);

            if (!$tenant->property_id || !$tenant->unit_id) {
                return redirect()->back()
                    ->with('error', 'This tenant has no property/unit assigned — cannot record an inspection.')
                    ->withInput();
            }

            $photoPaths = [];
            if ($request->hasFile('inspection_photos')) {
                foreach ($request->file('inspection_photos') as $file) {
                    if ($file->isValid()) {
                        $photoPaths[] = $file->store('move-in-inspections/photos', 'public');
                    }
                }
            }

            $videoPaths = [];
            if ($request->hasFile('inspection_videos')) {
                foreach ($request->file('inspection_videos') as $file) {
                    if ($file->isValid()) {
                        $videoPaths[] = $file->store('move-in-inspections/videos', 'public');
                    }
                }
            }

            $inspection = MoveInInspection::create([
                'tenant_id' => $tenant->id,
                'property_id' => $tenant->property_id,
                'unit_id' => $tenant->unit_id,
                'sla_id' => $tenant->sla_id,
                'inspection_date' => $validated['inspection_date'],
                'inspector_name' => $validated['inspector_name'] ?? null,
                'walls_condition' => $validated['walls_condition'] ?? null,
                'floors_condition' => $validated['floors_condition'] ?? null,
                'ceiling_condition' => $validated['ceiling_condition'] ?? null,
                'doors_condition' => $validated['doors_condition'] ?? null,
                'windows_condition' => $validated['windows_condition'] ?? null,
                'plumbing_condition' => $validated['plumbing_condition'] ?? null,
                'electrical_condition' => $validated['electrical_condition'] ?? null,
                'overall_condition' => $validated['overall_condition'] ?? null,
                'notes' => $validated['inspection_notes'] ?? null,
                'photo_paths' => $photoPaths ?: null,
                'video_paths' => $videoPaths ?: null,
                'conducted_by' => Auth::id(),
            ]);

            return redirect()->route('move.in.inspections.show', Crypt::encrypt($inspection->id))
                ->with('success', 'Move-in inspection recorded successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating move-in inspection: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to record inspection: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * View a single move-in inspection's captured details/photos/videos.
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $inspection = MoveInInspection::with(['tenant', 'property', 'unit', 'sla', 'conductedBy'])->findOrFail($id);
            return view('property.tenants.move_in_inspections.show', compact('inspection'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading move-in inspection: ' . $e->getMessage());
            return redirect()->route('move.in.inspections.index')->with('error', 'Inspection not found');
        }
    }
}
