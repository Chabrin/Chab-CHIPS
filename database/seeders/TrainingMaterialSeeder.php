<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\StaffTrainingRecord;
use App\Models\TrainingMaterial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TrainingMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the private local training directory exists
        Storage::disk('local')->makeDirectory('training_materials');

        $now = now();
        $firstStaffId = Staff::first()?->id;

        $materials = [
            [
                'title' => 'Chabrin Agencies Ltd Employee Code of Conduct',
                'description' => 'Mandatory guidelines on workplace behavior, professional ethics, and company standards.',
                'file_path' => 'training_materials/code_of_conduct.pdf',
                'file_name' => 'Chabrin_Code_of_Conduct_v1.0.pdf',
                'file_type' => 'pdf',
                'version' => '1.0',
                'is_mandatory' => true,
                'uploaded_by' => $firstStaffId,
            ],
            [
                'title' => 'Property Management ERP Standard Operating Procedures (SOP)',
                'description' => 'Operational instructions for managing property listings, lease tracking, and tenant accounts in the system.',
                'file_path' => 'training_materials/erp_sop.pdf',
                'file_name' => 'ERP_Management_SOP_v1.0.pdf',
                'file_type' => 'pdf',
                'version' => '1.0',
                'is_mandatory' => true,
                'uploaded_by' => $firstStaffId,
            ],
            [
                'title' => 'Data Privacy & Information Security Policy',
                'description' => 'Guidelines on handling sensitive client and financial property data securely within company infrastructure.',
                'file_path' => 'training_materials/security_policy.pdf',
                'file_name' => 'Data_Security_Policy_v1.0.pdf',
                'file_type' => 'pdf',
                'version' => '1.0',
                'is_mandatory' => false,
                'uploaded_by' => $firstStaffId,
            ],
        ];

        foreach ($materials as $data) {
            $material = TrainingMaterial::updateOrCreate(
                ['title' => $data['title']],
                $data
            );

            // If mandatory, batch-assign to active staff matching the store controller logic
            if ($material->is_mandatory) {
                $activeStaffIds = Staff::where('current_status_id', 1)->pluck('id');

                if ($activeStaffIds->isNotEmpty()) {
                    $rows = $activeStaffIds->map(fn($staffId) => [
                        'staff_id' => $staffId,
                        'training_material_id' => $material->id,
                        'version_acknowledged' => $material->version,
                        'due_date' => $now->copy()->addDays(14)->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    StaffTrainingRecord::insertOrIgnore($rows);
                }
            }
        }
    }
}