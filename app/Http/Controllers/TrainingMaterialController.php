<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingMaterialRequest;
use App\Models\Staff;
use App\Models\StaffTrainingRecord;
use App\Models\TrainingMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
class TrainingMaterialController extends Controller
{
    /**
     * Dashboard: Shows training materials and staff completion status.
     */
    public function index()
    {
        $user = Auth::user();
        $staff = $user->linkedStaff();

        $materials = TrainingMaterial::latest()->get();
        $myRecords = $staff
            ? StaffTrainingRecord::where('staff_id', $staff->id)->get()->keyBy('training_material_id')
            : collect();

        return view('hr.training.index', [
            'materials' => $materials,
            'myRecords' => $myRecords,
            'staffLinked' => (bool) $staff,
            'canManage' => $user->hasPermission('update_staff'),
        ]);
    }

    /**
     * Store: Only users with 'update_staff' permission can upload materials.
     */
    public function store(StoreTrainingMaterialRequest $request)
    {
        abort_unless(Auth::user()->hasPermission('update_staff'), 403, 'Unauthorized action.');

        $file = $request->file('document');
        $storedPath = $file->store('training_materials', 'local');

        // Dynamic due days (defaults to 14 if not provided)
       
        $dueDays = $request->input('due_days', 14);

        $material = TrainingMaterial::create([
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $storedPath,
            'trained_by' => auth()->user()->name,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'version' => $request->version ?? '1.0',
            'is_mandatory' => $request->boolean('is_mandatory'),
            'uploaded_by' => Auth::user()->linkedStaff()?->id,
        ]);

        if ($material->is_mandatory) {
            $now = now();
            $dueDays = (int) $request->input('due_days', 14);
            $rows = Staff::where('current_status_id', 1)->pluck('id')->map(fn($staffId) => [
                'staff_id' => $staffId,
                'training_material_id' => $material->id,
                'version_acknowledged' => $material->version,
                'due_date' => $now->copy()->addDays($dueDays)->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            StaffTrainingRecord::insertOrIgnore($rows);
        }

        return redirect()->back()->with('success', 'Training material uploaded and assigned successfully.');
    }

    /**
     * Show the form for editing the specified training material.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->hasPermission('update_staff'), 403, 'Unauthorized action.');

        $material = TrainingMaterial::findOrFail($id);

        return view('hr.training.edit', compact('material'));
    }

    /**
     * Update the specified training material in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->hasPermission('update_staff'), 403, 'Unauthorized action.');

        $material = TrainingMaterial::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'due_days' => 'nullable|integer|min:1',
            'document' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,mp4,webm,zip|max:51200',
        ]);

        $oldVersion = $material->version;
        $isNowMandatory = $request->boolean('is_mandatory');
        $dueDays = (int) $request->input('due_days', 14);
        $newVersion = $request->version ?? $oldVersion;

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'version' => $newVersion,
            'is_mandatory' => $isNowMandatory,
        ];

        // If a new file is uploaded, replace the old one
        if ($request->hasFile('document')) {
            if (Storage::disk('local')->exists($material->file_path)) {
                Storage::disk('local')->delete($material->file_path);
            }

            $file = $request->file('document');
            $data['file_path'] = $file->store('training_materials', 'local');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientOriginalExtension();
        }

        $material->update($data);

        // If the version changed or it became mandatory, handle records
        if ($isNowMandatory) {
            $now = now();
            $dueDate = $now->copy()->addDays($dueDays)->toDateString();
            $activeStaffIds = Staff::where('current_status_id', 1)->pluck('id');

            foreach ($activeStaffIds as $staffId) {
                $record = StaffTrainingRecord::firstOrNew([
                    'staff_id' => $staffId,
                    'training_material_id' => $material->id,
                ]);

                // If version changed, force them to re-acknowledge by resetting completion for the new version
                if ($record->version_acknowledged !== $newVersion) {
                    $record->completed_at = null; // Reset completion so it shows "Not started" / "Due" again
                }

                $record->version_acknowledged = $newVersion;
                $record->due_date = $dueDate;
                $record->updated_at = $now;
                $record->save();
            }
        }

        return redirect()->route('hr.training.index')->with('success', 'Training material updated successfully. Staff records synced to version ' . $newVersion . '.');
    }

    /**
     * Remove the specified training material from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->hasPermission('update_staff'), 403, 'Unauthorized action.');

        $material = TrainingMaterial::findOrFail($id);

        // Delete file from storage
        if (Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }

        // Clean up associated training records
        StaffTrainingRecord::where('training_material_id', $material->id)->delete();

        $material->delete();

        return redirect()->back()->with('success', 'Training material deleted successfully.');
    }

    /**
     * Stream: Stream file securely.
     */
    public function stream($id)
    {
        $material = TrainingMaterial::findOrFail($id);

        if (!Storage::disk('local')->exists($material->file_path)) {
            return redirect()->back()->with('error', 'File not found on storage disk.');
        }

        return Storage::disk('local')->response($material->file_path, $material->file_name);
    }

    /**
     * Acknowledge: Staff logs completion with IP & timestamp.
     */
    public function acknowledge(Request $request, $id)
    {
        $staff = Auth::user()->linkedStaff();
        if (!$staff) {
            return redirect()->back()->with('error', 'Your login is not linked to a staff record, so training cannot be acknowledged.');
        }

        $material = TrainingMaterial::findOrFail($id);

        StaffTrainingRecord::updateOrCreate([
            'staff_id' => $staff->id,
            'training_material_id' => $material->id,
            'version_acknowledged' => $material->version,
        ], [
            'completed_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Training module marked as completed and acknowledged.');
    }

    /**
     * HR Compliance Report: Gated strictly by 'read_staff'.
     */
    public function complianceReport(Request $request)
{
    abort_unless(Auth::user()->hasPermission('read_staff'), 403, 'Unauthorized access to compliance reports.');

    $query = StaffTrainingRecord::with(['staff', 'material'])->latest('updated_at');

    // Filter by status if selected
    if ($request->filled('status')) {
        $status = $request->input('status');
        if ($status === 'completed') {
            $query->whereNotNull('completed_at');
        } elseif ($status === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($status === 'overdue') {
            $query->whereNull('completed_at')->whereDate('due_date', '<', now());
        }
    }

    // Filter by specific training material if needed
    if ($request->filled('material_id')) {
        $query->where('training_material_id', $request->input('material_id'));
    }

    $records = $query->get();

    // Handle CSV Export
    if ($request->has('export') && $request->input('export') === 'csv') {
        $filename = 'compliance_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($records) {
            $file = fopen('php://output', 'w');
            // CSV Header Row
            fputcsv($file, ['Staff Name', 'Staff Email', 'Training Title', 'Version', 'Status', 'Due Date', 'Completed At', 'IP Address']);

            foreach ($records as $record) {
                $status = $record->completed_at ? 'Completed' : (optional($record->due_date)->isPast() ? 'Overdue' : 'Pending');
                fputcsv($file, [
                    $record->staff->name ?? 'N/A',
                    $record->staff->email ?? 'N/A',
                    $record->material->title ?? 'N/A',
                    $record->version_acknowledged,
                    $status,
                    $record->due_date ?? 'N/A',
                    $record->completed_at ?? 'N/A',
                    $record->ip_address ?? 'N/A'
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    $materials = TrainingMaterial::all(); // For filtering dropdown

    return view('hr.training.compliance', compact('records', 'materials'));
}
}