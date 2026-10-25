<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingMaterialRequest;
use App\Models\Staff;
use App\Models\StaffTrainingRecord;
use App\Models\TrainingMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TrainingMaterialController extends Controller
{
    /**
     * Training materials, with the logged-in staff member's own completion status for each.
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
     * Upload a training file; mandatory material is assigned to every active staff member (due in 14 days).
     */
    public function store(StoreTrainingMaterialRequest $request)
    {
        $file = $request->file('document');
        $storedPath = $file->store('training_materials', 'local');

        $material = TrainingMaterial::create([
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'version' => $request->version ?? '1.0',
            'is_mandatory' => $request->boolean('is_mandatory'),
            'uploaded_by' => Auth::user()->linkedStaff()?->id,
        ]);

        if ($material->is_mandatory) {
            $now = now();
            $rows = Staff::where('current_status_id', 1)->pluck('id')->map(fn($staffId) => [
                'staff_id' => $staffId,
                'training_material_id' => $material->id,
                'version_acknowledged' => $material->version,
                'due_date' => $now->copy()->addDays(14)->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            StaffTrainingRecord::insertOrIgnore($rows);
        }

        return redirect()->back()->with('success', 'Training material uploaded and assigned successfully.');
    }

    /**
     * Stream a training file to the browser.
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
     * Staff acknowledges the material (legal timestamp and IP recorded).
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
     * HR compliance report: every assignment and acknowledgement across staff.
     */
    public function complianceReport()
    {
        abort_unless(Auth::user()->hasPermission('read_staff'), 403);

        $records = StaffTrainingRecord::with(['staff', 'material'])
            ->latest('updated_at')
            ->get();

        return view('hr.training.compliance', compact('records'));
    }
}
