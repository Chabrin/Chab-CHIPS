<?php

namespace App\Http\Controllers;

use App\Models\TrainingMaterial;
use App\Models\StaffTrainingRecord;
use App\Models\User;
use App\Http\Requests\StoreTrainingMaterialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class TrainingMaterialController extends Controller
{
    /**
     * Dashboard: List materials for staff & compliance overview for HR
     */
    public function index()
    {
        $user = Auth::user();

        // 1. All materials
        $materials = TrainingMaterial::latest()->get();

        // 2. Fetch the user's personal completion status map [material_id => record]
        $myRecords = StaffTrainingRecord::where('user_id', $user->id)
            ->get()
            ->keyBy('training_material_id');

        return view('hr.training.index', compact('materials', 'myRecords'));
    }

    /**
     * HR Action: Upload a new training file
     */
    public function store(StoreTrainingMaterialRequest $request)
    {
        $file = $request->file('document');
        $fileName = $file->getClientOriginalName();
        $fileType = $file->getClientOriginalExtension();

        // Save safely into private local storage
        $storedPath = $file->store('training_materials', 'local');

        $material = TrainingMaterial::create([
            'title'        => $request->title,
            'description'  => $request->description,
            'file_path'    => $storedPath,
            'file_name'    => $fileName,
            'file_type'    => $fileType,
            'version'      => $request->version ?? '1.0',
            'is_mandatory' => $request->has('is_mandatory'),
            'uploaded_by'  => Auth::id(),
        ]);

        // Auto-assign to all current active staff if it is mandatory
        if ($material->is_mandatory) {
            $allStaff = User::pluck('id');
            foreach ($allStaff as $staffId) {
                StaffTrainingRecord::firstOrCreate([
                    'user_id'              => $staffId,
                    'training_material_id' => $material->id,
                    'version_acknowledged' => $material->version,
                ], [
                    'due_date' => now()->addDays(14),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Training material uploaded and assigned successfully.');
    }

    /**
     * Secure Stream/Download for staff
     */
    public function stream($id)
    {
        $material = TrainingMaterial::findOrFail($id);

        if (!Storage::disk('local')->exists($material->file_path)) {
            return redirect()->back()->with('error', 'File not found on storage disk.');
        }

        $fullPath = Storage::disk('local')->path($material->file_path);

        return response()->file($fullPath, [
            'Content-Disposition' => 'inline; filename="' . $material->file_name . '"'
        ]);
    }

    /**
     * Staff Action: Acknowledge & Mark Read (Legal Timestamp & IP recorded)
     */
    public function acknowledge(Request $request, $id)
    {
        $material = TrainingMaterial::findOrFail($id);

        StaffTrainingRecord::updateOrCreate([
            'user_id'              => Auth::id(),
            'training_material_id' => $material->id,
            'version_acknowledged' => $material->version,
        ], [
            'completed_at' => now(),
            'ip_address'   => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Training module marked as completed and acknowledged.');
    }

    /**
     * HR Compliance Report (Disciplinary audit log)
     */
    public function complianceReport()
    {
        $records = StaffTrainingRecord::with(['staff', 'material'])
            ->latest('updated_at')
            ->get();

        return view('hr.training.compliance', compact('records'));
    }
}