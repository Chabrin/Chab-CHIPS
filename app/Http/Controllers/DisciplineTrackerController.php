<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Staff;
use App\Models\Incident;
use App\Models\Position;
use App\Models\ActionTaken;

use Illuminate\Http\Request;
use App\Models\DisciplineCase;
use App\Models\DisciplineHearing;
use App\Models\DisciplineOutcome;
use App\Services\ErrorDispatcher;
use App\Models\DisciplineEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Auth;
use App\Models\DisciplineActivityLog;
use App\Models\DisciplineCaseClosure;
use Illuminate\Support\Facades\Crypt;
use App\Models\DisciplineEvidenceFile;
use App\Models\DisciplineInvestigator;
use App\Models\DisciplineHistoryUpdate;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendDisciplineNotification;
use App\Models\DisciplineHearingMinutes;
use App\Models\DisciplineIncidentReport;
use App\Models\DisciplineHearingDocument;
use App\Models\DisciplineOutcomeDocument;
use App\Models\DisciplineShowCauseNotice;
use App\Models\DisciplineWitnessDocument;
use App\Models\DisciplineCaseRegistration;
use App\Models\DisciplineEmployeeResponse;
use App\Models\DisciplineIncidentDocument;
use App\Models\DisciplineResponseDocument;
use App\Models\DisciplineWitnessStatement;
use App\Models\DisciplineDeadlineExtension;
use App\Models\DisciplineInvestigationNote;
use App\Models\DisciplineShowCauseDocument;
use App\Models\DisciplineTrainingAssignment;
use App\Models\DisciplineSuspension;
use App\Models\DisciplineAppeal;
use App\Models\EmployeeTermination;
use App\Models\ExitInterview;
use App\Models\FinalDues;
use App\Models\Contract;
use App\Traits\PdfGenerationTrait;
use Illuminate\Validation\ValidationException;


class DisciplineTrackerController extends Controller
{
    use PdfGenerationTrait;

    /**
     * Display discipline cases list
     */
    public function index()
    {
        return view('discipline_tracker.index');
    }

    /**
     * Get all discipline cases for DataTable
     */
    public function casesList()
    {
        try {
            $cases = DisciplineCase::with([
                'staff:id,full_names,staff_number',
                'position:id,position_name',
                'createdBy:id,name'
            ])
            ->orderBy('date_time', 'desc')
            ->get()
            ->map(function ($case) {
                return [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'staff' => [
                        'id' => $case->staff->id ?? null,
                        'name' => $case->staff->full_names ?? '-',
                        'staff_number' => $case->staff->staff_number ?? '-',
                        'encrypted_id' => $case->staff ? Crypt::encrypt($case->staff->id) : null,
                    ],
                    'position' => $case->position->position_name ?? '-',
                    'current_step' => $case->current_step,
                    'step_name' => $this->getStepName($case->current_step),
                    'severity_level' => $case->severity_level,
                    'current_status_id' => $case->current_status_id,
                    'created_by' => $case->createdBy->name ?? '-',
                    'date_time' => $case->date_time,
                    'encrypted_case_id' => Crypt::encrypt($case->id),
                    'can_view' =>Auth::user()->isAbleTo('read_discipline_records'),
                    'can_update' =>Auth::user()->isAbleTo('update_discipline_records'),
                ];
            });

            return response()->json(['cases' => $cases]);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to load cases'], 500);
        }
    }

    /**
     * Show case details page (single page with all steps)
     */
    public function show($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            
            $case = DisciplineCase::with([
                'staff',
                'position',
                'createdBy',
                'incidentReports.documents',          // Changed from incidentReport
                'incidentReports.reportedBy',          // Changed from incidentReport
                'registration',                        // Changed from caseRegistration
                'investigators.investigator',
                'suspensions',
                'evidence.files',
                'witnessStatements.documents',
                'investigationNotes',
                'showCauseNotice.documents',
                'hearings.minutes.documents',
                'hearings.chairperson',
                'hearings.minutes',
                'employeeResponse.submittedBy', 
                'employeeResponse.receivedBy',         // Changed from hearing
                'employeeResponse.documents',
                'outcome',
                'outcome.documents',
                'outcome.decidedBy',
                'outcome.approvedBy',
                'appeals',
                'trainingAssignments',
                'historyUpdates',
                'closure',                             // Changed from caseClosure
                'activityLogs.performedBy'             // Changed from activityLog
            ])->findOrFail($id);

            // dd($case);
            // Get available steps based on current progress
            $availableSteps = $this->getAvailableSteps($case);

            return view('discipline_tracker.show', compact('case', 'availableSteps'));
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return redirect()->route('discipline.tracker.index')->with('error', 'Case not found');
        }
    }

    /**
     * Show create incident report page (Step 1)
     */
    public function createIncident()
    {
        return view('discipline_tracker.create_incident');
    }

    /**
     * Store incident report (Step 1)
     */
    public function storeIncident(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'staff_id' => 'required',
                'position_id' => 'nullable',
                'incident_date_time' => 'required|date',
                'location' => 'nullable|string|max:255',
                'incident_type_id' => 'nullable|exists:incidents,id',
                'incident_description' => 'required|string',
                'immediate_action_taken' => 'nullable|string',
                'reported_by' => 'required',
                'witness_present' => 'boolean',
                'police_involved' => 'boolean',
                'severity_level' => 'required|in:Minor,Moderate,Serious,Gross Misconduct',
                'is_anonymous' => 'boolean',
                'documents.*' => 'nullable|file|max:10240', // 10MB max
            ]);

            // Generate case number
            $year = date('Y');
            $lastCase = DisciplineCase::where('case_number', 'LIKE', "DSC-{$year}-%")
                ->orderBy('case_number', 'desc')
                ->first();
            
            if ($lastCase) {
                $lastNumber = (int) substr($lastCase->case_number, -3);
                $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
                Log::info('Last case number for year ' . $year . ' is ' . $lastCase->case_number);
            } else {
                $newNumber = '001';
                Log::info('No previous cases found for year ' . $year);
            }
            $caseNumber = "DSC-{$year}-{$newNumber}";

            // Create main case
            $case = DisciplineCase::create([
                'case_number' => $caseNumber,
                'staff_id' => $validated['staff_id'],
                'position_id' => $validated['position_id'],
                'current_step' => 1,
                'current_status_id' => 1, // Open
                'severity_level' => $validated['severity_level'],
                'is_anonymous' => $validated['is_anonymous'] ?? false,
                'created_by' => Auth::id(),
                'date_time' => now(),
            ]);
            Log::info('Created Discipline Case with ID: ' . $case->id);

            // Create incident report
            $incidentReport = DisciplineIncidentReport::create([
                'case_id' => $case->id,
                'incident_date_time' => $validated['incident_date_time'],
                'location' => $validated['location'],
                'incident_type_id' => $validated['incident_type_id'],
                'incident_description' => $validated['incident_description'],
                'immediate_action_taken' => $validated['immediate_action_taken'],
                'reported_by' => $validated['reported_by'],
                'witness_present' => $validated['witness_present'] ?? false,
                'police_involved' => $validated['police_involved'] ?? false,
            ]);
            Log::info('Created Incident Report with ID: ' . $incidentReport->id);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                Log::info('Uploading documents for Incident Report ID: ' . $incidentReport->id);
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline/incidents/' . $case->id, 'public');
                    
                    DisciplineIncidentDocument::create([
                        'incident_report_id' => $incidentReport->id,
                        'document_name' => $file->getClientOriginalName(),
                        'document_path' => $path,
                        'document_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            }

            // Log activity
            Log::info('Logging activity for Case ID: ' . $case->id);
            $activityLog = $this->logActivity($case->id, 1, 'Step Completed', 'Incident report created');
            Log::info('Activity logged with ID: ' . $activityLog->id);

            // Send notifications (HR, relevant managers)
            $this->sendStepNotification($case, 1, 'incident_reported');

            DB::commit();

            // Move to step 2 automatically after incident creation
            $case->update(['current_step' => 2]);

            Log::info('Incident report created successfully for Case ID: ' . $case->id);

            return redirect()->route('discipline.tracker.index', Crypt::encrypt($case->id))
                ->with('success', 'Incident report created successfully');

        } catch (ValidationException $e) {
            Log::info('ValidationException: ' . json_encode($e->errors()));
            DB::rollBack();
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return redirect()->back()->with('error', 'Failed to create incident report')->withInput();
        }
    }

    /**
     * Register case (Step 2)
     */
    public function registerCase(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $id = Crypt::decrypt($caseId);

            Log::info('Register Case - Decrypted ID: ' . $id);

            $case = DisciplineCase::findOrFail($id);

            $validated = $request->validate([
                'decision' => 'nullable|in:Proceed,Reject',
                'hr_notes' => 'nullable|string',
                'priority' => 'required_if:decision,Proceed|nullable|in:Low,Medium,High,Critical',
                'estimated_closure_date' => 'nullable|date',
                'rejection_reason' => 'required_if:decision,Reject|nullable|string',
            ]);

            $decision = $validated['decision'] ?? 'Proceed';

            DisciplineCaseRegistration::create([
                'case_id' => $case->id,
                'registered_by' => Auth::id(),
                'registration_date' => now(),
                'hr_notes' => $validated['hr_notes'] ?? null,
                'priority' => $validated['priority'] ?? null,
                'estimated_closure_date' => $validated['estimated_closure_date'] ?? null,
                'decision' => $decision,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'hr_notified' => true,
                'notification_sent_at' => now(),
            ]);

            if ($decision === 'Reject') {
                DisciplineCaseClosure::create([
                    'case_id' => $case->id,
                    'closure_date' => now(),
                    'closure_reason' => 'No Case to Answer',
                    'closure_notes' => 'Rejected at registration: ' . $validated['rejection_reason'],
                    'closed_by' => Auth::id(),
                ]);

                $case->update([
                    'current_step' => 14,
                    'current_status_id' => 42, // Closed
                    'closed_at' => now(),
                    'closed_by' => Auth::id(),
                ]);

                $this->logActivity($case->id, 2, 'Case Rejected', 'Case rejected at registration: ' . $validated['rejection_reason']);
                $this->sendStepNotification($case, 2, 'case_rejected');

                DB::commit();

                return response()->json(['success' => true, 'message' => 'Case rejected and closed']);
            }

            // Update case step
            $case->update(['current_step' => 3]);

            // Log activity
            $this->logActivity($case->id, 2, 'Step Completed', 'Case registered by HR');

            // Send notification
            $this->sendStepNotification($case, 2, 'case_registered');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Case registered successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to register case'], 500);
        }
    }

    /**
     * Assign investigator (Step 3)
     */
    public function assignInvestigator(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'investigator_id' => 'required',
                'investigation_start_date' => 'nullable|date',
                'investigation_deadline' => 'nullable|date',
            ]);

            DisciplineInvestigator::create([
                'case_id' => $case->id,
                'investigator_id' => $validated['investigator_id'],
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'investigation_start_date' => $validated['investigation_start_date'] ?? null,
                'investigation_deadline' => $validated['investigation_deadline'] ?? null,
                'status' => 'Assigned',
            ]);

            // Update case step
            $case->update(['current_step' => 4]);

            // Log activity
            $this->logActivity($case->id, 3, 'Step Completed', 'Investigator assigned');

            // Send notification to investigator, and separately inform the employee
            // that an investigation has been opened (previously they were only told
            // much later, at the Show Cause stage).
            $this->sendStepNotification($case, 3, 'investigator_assigned');
            $this->sendStepNotification($case, 3, 'investigation_opened');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Investigator assigned successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to assign investigator'], 500);
        }
    }

    /**
     * Suspend an employee on full pay as a protective measure during an investigation —
     * distinct from "Suspension" as a final disciplinary decision (Step 11). Available once
     * an investigation is underway and there's no already-active protective suspension.
     */
    public function suspendPendingInvestigation(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);

            if ($case->activeSuspension()) {
                return response()->json(['error' => 'This case already has an active protective suspension'], 422);
            }

            $validated = $request->validate([
                'reason' => 'required|string',
                'pay_status' => 'nullable|string|max:50',
            ]);

            DisciplineSuspension::create([
                'case_id' => $case->id,
                'suspended_by' => Auth::id(),
                'suspended_at' => now(),
                'reason' => $validated['reason'],
                'pay_status' => $validated['pay_status'] ?? 'Full Pay',
            ]);

            $this->logActivity($case->id, $case->current_step, 'Protective Suspension', 'Employee suspended pending investigation');
            $this->sendStepNotification($case, $case->current_step, 'investigation_suspension');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Employee suspended pending investigation']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to suspend employee'], 500);
        }
    }

    /**
     * Lift an active protective suspension.
     */
    public function liftSuspension(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            $suspension = $case->activeSuspension();

            if (!$suspension) {
                return response()->json(['error' => 'There is no active protective suspension on this case'], 422);
            }

            $validated = $request->validate([
                'notes' => 'nullable|string',
            ]);

            $suspension->update([
                'lifted_by' => Auth::id(),
                'lifted_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->logActivity($case->id, $case->current_step, 'Suspension Lifted', 'Protective suspension lifted');
            $this->sendStepNotification($case, $case->current_step, 'suspension_lifted');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Suspension lifted']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to lift suspension'], 500);
        }
    }

    /**
     * Upload evidence (Step 4)
     */
    public function uploadEvidence(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'evidence_type' => 'required|in:Document,Photo,Video,Audio,Physical Item,Digital,Other',
                'evidence_description' => 'required|string',
                'evidence_source' => 'nullable|string|max:255',
                'chain_of_custody' => 'nullable|string',
                'is_original' => 'boolean',
                'files.*' => 'nullable|file|max:20480', // 20MB
            ]);

            $evidence = DisciplineEvidence::create([
                'case_id' => $case->id,
                'evidence_type' => $validated['evidence_type'],
                'evidence_description' => $validated['evidence_description'],
                'evidence_source' => $validated['evidence_source'],
                'chain_of_custody' => $validated['chain_of_custody'],
                'is_original' => $validated['is_original'] ?? true,
                'uploaded_by' => Auth::id(),
            ]);

            // Handle file uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('discipline/evidence/' . $case->id, 'public');
                    
                    DisciplineEvidenceFile::create([
                        'evidence_id' => $evidence->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Update case step if first evidence
            $case->update(['current_step' => 5]);

            // Log activity
            $this->logActivity($case->id, 4, 'Document Added', 'Evidence uploaded');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Evidence uploaded successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to upload evidence'], 500);
        }
    }

    /**
     * Add witness statement (Step 5)
     */
    public function addWitnessStatement(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'witness_name' => 'required|string|max:255',
                'witness_staff_id' => 'nullable',
                'witness_contact' => 'nullable|string|max:255',
                'witness_role' => 'nullable|string|max:255',
                'statement_text' => 'required|string',
                'statement_date' => 'required|date',
                'is_sworn' => 'boolean',
                'sworn_before' => 'nullable|string|max:255',
                'documents.*' => 'nullable|file|max:10240',
            ]);

            $witnessStatement = DisciplineWitnessStatement::create([
                'case_id' => $case->id,
                'witness_name' => $validated['witness_name'],
                'witness_staff_id' => $validated['witness_staff_id'],
                'witness_contact' => $validated['witness_contact'],
                'witness_role' => $validated['witness_role'],
                'statement_text' => $validated['statement_text'],
                'statement_date' => $validated['statement_date'],
                'is_sworn' => $validated['is_sworn'] ?? false,
                'sworn_before' => $validated['sworn_before'],
                'recorded_by' => Auth::id(),
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline/witness/' . $case->id, 'public');
                    
                    DisciplineWitnessDocument::create([
                        'witness_statement_id' => $witnessStatement->id,
                        'document_name' => $file->getClientOriginalName(),
                        'document_path' => $path,
                    ]);
                }
            }

            // Update case step if first witness
            $case->update(['current_step' => 6]);

            // Log activity
            $this->logActivity($case->id, 5, 'Step Completed', 'Witness statement added');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Witness statement added successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to add witness statement'], 500);
        }
    }

    /**
     * Add investigation note (Step 6)
     */
    public function addInvestigationNote(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'note_date' => 'required|date',
                'note_text' => 'required|string',
                'note_type' => 'required|in:Interview,Site Visit,Analysis,Correspondence,General',
                'is_confidential' => 'boolean',
            ]);

            DisciplineInvestigationNote::create([
                'case_id' => $case->id,
                'note_date' => $validated['note_date'],
                'note_text' => $validated['note_text'],
                'note_type' => $validated['note_type'],
                'is_confidential' => $validated['is_confidential'] ?? false,
                'created_by' => Auth::id(),
            ]);

            // Update case step if first note
            $case->update(['current_step' => 7]);

            // Log activity
            $this->logActivity($case->id, 6, 'Step Completed', 'Investigation note added');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Investigation note added successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to add investigation note'], 500);
        }
    }

    /**
     * Issue show cause notice (Step 7)
     */
    public function issueShowCause(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'notice_number' => 'nullable|string|max:50',
                'issue_date' => 'required|date',
                'response_deadline' => [
                    'required',
                    'date',
                    'after:issue_date',
                    function ($attribute, $value, $fail) use ($request) {
                        $minDeadline = \Carbon\Carbon::parse($request->input('issue_date'))->addHours(48);
                        if (\Carbon\Carbon::parse($value)->lt($minDeadline)) {
                            $fail('The response deadline must be at least 48 hours after the issue date.');
                        }
                    },
                ],
                'allegations' => 'required|string',
                'potential_consequences' => 'nullable|string',
                'delivered_method' => 'required|in:Hand Delivery,Email,Registered Mail,Courier',
                'documents.*' => 'nullable|file|max:10240',
                'evidence_file_ids' => 'nullable|array',
                'evidence_file_ids.*' => 'integer|exists:discipline_evidence_files,id',
                'witness_document_ids' => 'nullable|array',
                'witness_document_ids.*' => 'integer|exists:discipline_witness_documents,id',
            ]);

            // Auto-generate notice number if not provided
            if (empty($validated['notice_number'])) {
                $year = date('Y');
                $lastNotice = DisciplineShowCauseNotice::where('notice_number', 'LIKE', "SCN-{$year}-%")
                    ->orderBy('notice_number', 'desc')
                    ->first();
                
                if ($lastNotice) {
                    $lastNumber = (int) substr($lastNotice->notice_number, -3);
                    $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = '001';
                }
                
                $validated['notice_number'] = "SCN-{$year}-{$newNumber}";
            }

            $showCause = DisciplineShowCauseNotice::create([
                'case_id' => $case->id,
                'notice_number' => $validated['notice_number'],
                'issue_date' => $validated['issue_date'],
                'response_deadline' => $validated['response_deadline'],
                'allegations' => $validated['allegations'],
                'potential_consequences' => $validated['potential_consequences'] ?? null,
                'issued_by' => Auth::id(),
                'delivered_method' => $validated['delivered_method'],
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline/show_cause/' . $case->id, 'public');

                    DisciplineShowCauseDocument::create([
                        'show_cause_id' => $showCause->id,
                        'document_name' => $file->getClientOriginalName(),
                        'document_path' => $path,
                    ]);
                }
            }

            // Carry through evidence/witness statement files already gathered during the
            // investigation, rather than requiring them to be re-uploaded here. Files are
            // physically copied into the show-cause storage path (not just referenced) so the
            // notice's attachments remain intact even if the original evidence is later edited.
            if (!empty($validated['evidence_file_ids'])) {
                $evidenceFiles = DisciplineEvidenceFile::whereIn('id', $validated['evidence_file_ids'])
                    ->whereHas('evidence', function ($q) use ($case) {
                        $q->where('case_id', $case->id);
                    })->get();

                foreach ($evidenceFiles as $evidenceFile) {
                    $newPath = 'discipline/show_cause/' . $case->id . '/' . basename($evidenceFile->file_path);
                    Storage::disk('public')->copy($evidenceFile->file_path, $newPath);

                    DisciplineShowCauseDocument::create([
                        'show_cause_id' => $showCause->id,
                        'document_name' => $evidenceFile->file_name,
                        'document_path' => $newPath,
                        'source_evidence_file_id' => $evidenceFile->id,
                    ]);
                }
            }

            if (!empty($validated['witness_document_ids'])) {
                $witnessDocuments = DisciplineWitnessDocument::whereIn('id', $validated['witness_document_ids'])
                    ->whereHas('witnessStatement', function ($q) use ($case) {
                        $q->where('case_id', $case->id);
                    })->get();

                foreach ($witnessDocuments as $witnessDocument) {
                    $newPath = 'discipline/show_cause/' . $case->id . '/' . basename($witnessDocument->document_path);
                    Storage::disk('public')->copy($witnessDocument->document_path, $newPath);

                    DisciplineShowCauseDocument::create([
                        'show_cause_id' => $showCause->id,
                        'document_name' => $witnessDocument->document_name,
                        'document_path' => $newPath,
                        'source_witness_document_id' => $witnessDocument->id,
                    ]);
                }
            }

            // Update case step
            $case->update(['current_step' => 7]);

            // Log activity
            $this->logActivity($case->id, 7, 'Step Completed', 'Show cause notice issued');

            // Send notification to employee
            $this->sendStepNotification($case, 7, 'show_cause_issued');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Show cause notice issued successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to issue show cause notice'], 500);
        }
    }

    public function requestDeadlineExtension(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:discipline_cases,id',
            'extension_reason' => 'required|string|max:1000',
            'requested_days' => 'required|integer|min:1|max:14',
        ]);
    
        $case = DisciplineCase::findOrFail($validated['case_id']);

        // Verify the requester is the subject of the case (staff_id is a staff row,
        // not a user row — resolve the requester's own staff record via UserStaffLink,
        // the same way SelfServiceController does, rather than comparing to Auth::id()).
        $staffLink = \App\Models\UserStaffLink::where('user_id', Auth::id())->first();
        if (!$staffLink || $case->staff_id !== $staffLink->staff_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if there's already a pending request
        $existingRequest = DisciplineDeadlineExtension::where('case_id', $case->id)
            ->where('requested_by', Auth::id())
            ->where('request_status', 'Pending')
            ->whereNull('reviewed_at')
            ->first();
    
        if ($existingRequest) {
            return response()->json(['error' => 'You already have a pending extension request for this case.'], 400);
        }
    
        // Get current deadline
        $showCauseNotice = $case->showCauseNotice;
        if (!$showCauseNotice || !$showCauseNotice->response_deadline) {
            return response()->json(['error' => 'No deadline found for this case.'], 400);
        }
    
        $originalDeadline = Carbon::parse($showCauseNotice->response_deadline);
        
        // Check for existing extensions
        $latestExtension = $case->deadlineExtensions()
            ->where('request_status', 'Approved')
            ->whereNotNull('new_deadline')
            ->orderBy('extended_at', 'desc')
            ->first();
        
        if ($latestExtension) {
            $originalDeadline = Carbon::parse($latestExtension->new_deadline);
        }
    
        // Create extension request
        DisciplineDeadlineExtension::create([
            'case_id' => $case->id,
            'original_deadline' => $originalDeadline,
            'extension_reason' => $validated['extension_reason'],
            'requested_days' => $validated['requested_days'],
            'requested_by' => Auth::id(),
            'requested_at' => now(),
            'deadline_lapsed_before_extension' => now()->greaterThan($originalDeadline),
            'request_status' => 'Pending',
        ]);
    
        // Log activity using the helper function
        $this->logActivity(
            $case->id,
            $case->current_step,
            'Extension Request',
            "Employee requested {$validated['requested_days']} additional days. Reason: {$validated['extension_reason']}"
        );
    
        return response()->json([
            'success' => true,
            'message' => 'Extension request submitted successfully. HR will review your request.'
        ]);
    }

    public function extendResponseDeadline(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            $showCauseNotice = $case->showCauseNotice;
            
            if (!$showCauseNotice) {
                return response()->json(['error' => 'No show cause notice found for this case'], 404);
            }
            
            $validated = $request->validate([
                'new_deadline' => 'required|date|after:now',
                'extension_reason' => 'required|string|max:500',
            ]);
            
            // Determine original deadline
            $latestExtension = $case->deadlineExtensions()->where('request_status', 'Approved')->orderBy('extended_at', 'desc')->first();
            $originalDeadline = $latestExtension 
                ? $latestExtension->new_deadline 
                : $showCauseNotice->response_deadline;
            
            // Check if deadline has already lapsed
            $deadlineLapsed = now()->greaterThan(Carbon::parse($originalDeadline));
            
            // Calculate requested days
            $originalDeadlineCarbon = Carbon::parse($originalDeadline);
            $newDeadlineCarbon = Carbon::parse($validated['new_deadline']);
            $requestedDays = $originalDeadlineCarbon->diffInDays($newDeadlineCarbon);
            
            DisciplineDeadlineExtension::create([
                'case_id' => $case->id,
                'original_deadline' => $originalDeadline,
                'new_deadline' => $validated['new_deadline'],
                'requested_days' => $requestedDays,
                'extension_reason' => $validated['extension_reason'],
                'extended_by' => Auth::id(),
                'extended_at' => now(),
                'deadline_lapsed_before_extension' => $deadlineLapsed,
                'request_status' => 'Approved', // HR manual extension is automatically approved
            ]);
            
            // Log activity
            $this->logActivity(
                $case->id, 
                7,  // Changed from 8 to 7 since this is step 7
                'Deadline Extended', 
                'Response deadline extended to ' . Carbon::parse($validated['new_deadline'])->format('d M Y, h:i A') . '. Reason: ' . $validated['extension_reason']
            );
            
            DB::commit();
            
            return response()->json([
                'success' => true, 
                'message' => 'Deadline extended successfully'
            ]);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to extend deadline'], 500);
        }
    }

    public function lapsedDeadlinesReport()
    {
        $lapsedCases = DisciplineCase::where('current_step', 8)
            ->whereHas('showCauseNotice', function($query) {
                $query->where('response_deadline', '<', now());
            })
            ->whereDoesntHave('employeeResponse')
            ->with(['staff', 'showCauseNotice', 'deadlineExtensions' => function($query) {
                $query->orderBy('extended_at', 'desc');
            }])
            ->get()
            ->filter(function($case) {
                // Double check with extensions
                $latestExtension = $case->deadlineExtensions->first();
                if ($latestExtension) {
                    return now()->greaterThan(Carbon::parse($latestExtension->new_deadline));
                }
                return true;
            });
        
        return view('discipline_tracker.reports.lapsed_deadlines', compact('lapsedCases'));
    }

    public function reviewExtensionRequest(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'request_id' => 'required|exists:cb_discipline_deadline_extensions,id',
                'action' => 'required|in:approve,reject',
                'new_deadline' => 'required_if:action,approve|nullable|date|after:now',
                'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
            ]);
    
            $extensionRequest = DisciplineDeadlineExtension::findOrFail($validated['request_id']);
            
            // Verify it's still pending
            if ($extensionRequest->request_status !== 'Pending') {
                return response()->json(['error' => 'This request has already been reviewed.'], 400);
            }
    
            if ($validated['action'] === 'approve') {
                // Approve the request
                $extensionRequest->update([
                    'request_status' => 'Approved',
                    'new_deadline' => $validated['new_deadline'],
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
    
                // Log activity
                $this->logActivity(
                    $extensionRequest->case_id,
                    7,
                    'Extension Request Approved',
                    "Employee extension request approved. New deadline: " . Carbon::parse($validated['new_deadline'])->format('d M Y, h:i A')
                );
    
                $message = 'Extension request approved successfully';
            } else {
                // Reject the request
                $extensionRequest->update([
                    'request_status' => 'Rejected',
                    'rejection_reason' => $validated['rejection_reason'],
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
    
                // Log activity
                $this->logActivity(
                    $extensionRequest->case_id,
                    7,
                    'Extension Request Rejected',
                    "Employee extension request rejected. Reason: {$validated['rejection_reason']}"
                );
    
                $message = 'Extension request rejected';
            }
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
    
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to review extension request'], 500);
        }
    }

    
    /**
     * Submit employee response (Step 8)
     */
    public function submitEmployeeResponse(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'response_date' => 'required|date',
                'response_text' => 'required|string',
                'admits_allegations' => 'nullable|boolean',
                'mitigating_circumstances' => 'nullable|string',
                'documents.*' => 'nullable|file|max:10240',
            ]);

            $response = DisciplineEmployeeResponse::create([
                'case_id' => $case->id,
                'response_date' => $validated['response_date'],
                'response_text' => $validated['response_text'],
                'admits_allegations' => $validated['admits_allegations'] ?? null,
                'mitigating_circumstances' => $validated['mitigating_circumstances'] ?? null,
                'submitted_by' => Auth::id(),
                'received_by' => Auth::id(),
                'received_at' => now(),
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline/response/' . $case->id, 'public');
                    
                    DisciplineResponseDocument::create([
                        'response_id' => $response->id,
                        'document_name' => $file->getClientOriginalName(),
                        'document_path' => $path,
                    ]);
                }
            }

            // Update case step
            $case->update(['current_step' => 9]);

            // Log activity
            $this->logActivity($case->id, 8, 'Step Completed', 'Employee response submitted');

            // Send notification to HR
            $this->sendStepNotification($case, 8, 'employee_response_received');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Response submitted successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to submit response'], 500);
        }
    }

    /**
     * Schedule hearing (Step 9)
     */
    public function scheduleHearing(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'hearing_date' => 'required|date',
                'hearing_location' => 'nullable|string|max:255',
                'hearing_type' => 'required|in:Preliminary,Full Hearing,Appeal',
                'chairperson_id' => 'nullable',
                'panel_members' => 'nullable|array',
                'employee_representative' => 'nullable|string|max:255',
            ]);

            // Conflict of interest: the investigator on this case cannot also sit on the
            // hearing panel/chair it.
            $investigatorIds = DisciplineInvestigator::where('case_id', $case->id)
                ->pluck('investigator_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $panelIds = array_map('strval', $validated['panel_members'] ?? []);
            $proposedIds = array_filter(array_merge(
                [(string) ($validated['chairperson_id'] ?? '')],
                $panelIds
            ));
            if (array_intersect($investigatorIds, $proposedIds)) {
                throw ValidationException::withMessages([
                    'chairperson_id' => ['The investigator assigned to this case cannot also serve on the hearing panel.'],
                ]);
            }

            DisciplineHearing::create([
                'case_id' => $case->id,
                'hearing_date' => $validated['hearing_date'],
                'hearing_location' => $validated['hearing_location'] ?? null,
                'hearing_type' => $validated['hearing_type'],
                'chairperson_id' => $validated['chairperson_id'] ?? null,
                'panel_members' => !empty($validated['panel_members']) ? json_encode($validated['panel_members']) : null,
                'employee_representative' => $validated['employee_representative'] ?? null,
                'scheduled_by' => Auth::id(),
            ]);

            // Update case step
            $case->update(['current_step' => 10]);

            // Log activity
            $this->logActivity($case->id, 9, 'Step Completed', 'Hearing scheduled');

            // Send notification to employee and panel
            $this->sendStepNotification($case, 9, 'hearing_scheduled');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Hearing scheduled successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to schedule hearing'], 500);
        }
    }

    /**
     * Record hearing minutes (Step 10)
     */
    public function recordHearingMinutes(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            $hearing = $case->hearings()->first();
    
            if (!$hearing) {
                return response()->json(['error' => 'Hearing not found'], 404);
            }
            
            $validated = $request->validate([
                'attendees' => 'nullable|array',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'proceedings_summary' => 'required|string',
                'employee_statement' => 'nullable|string',
                'panel_questions' => 'nullable|string',
                'panel_recommendation' => 'nullable|string',
                'minute_taker_id' => 'nullable',
                'employee_attended' => 'nullable|boolean',
                'documents.*' => 'nullable|file|max:10240',
                'signed_copy' => 'nullable|file|max:10240',
            ]);

            $minutes = DisciplineHearingMinutes::create([
                'hearing_id' => $hearing->id,
                'attendees' => !empty($validated['attendees']) ? json_encode($validated['attendees']) : null,
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'] ?? null,
                'proceedings_summary' => $validated['proceedings_summary'],
                'employee_statement' => $validated['employee_statement'] ?? null,
                'panel_questions' => $validated['panel_questions'] ?? null,
                'panel_recommendation' => $validated['panel_recommendation'] ?? null,
                'minute_taker_id' => $validated['minute_taker_id'] ?? null,
            ]);
    
            // Update hearing record with employee attendance status
            $hearing->update([
                'employee_attended' => $request->has('employee_attended') ? true : false
            ]);
    
            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline/hearing/' . $case->id, 'public');

                    DisciplineHearingDocument::create([
                        'hearing_id' => $hearing->id,
                        'document_name' => $file->getClientOriginalName(),
                        'document_path' => $path,
                        'document_type' => 'Minutes',
                    ]);
                }
            }

            // A signed hard copy is tagged distinctly from the generic documents above so
            // it's identifiable as the attested record, not just another attachment.
            if ($request->hasFile('signed_copy')) {
                $file = $request->file('signed_copy');
                $path = $file->store('discipline/hearing/' . $case->id, 'public');

                DisciplineHearingDocument::create([
                    'hearing_id' => $hearing->id,
                    'document_name' => $file->getClientOriginalName(),
                    'document_path' => $path,
                    'document_type' => 'Signed Minutes',
                ]);
            }

            // Update case step
            $case->update(['current_step' => 11]);
    
            // Log activity
            $attendanceStatus = $hearing->employee_attended ? 'attended' : 'did not attend';
            $this->logActivity($case->id, 10, 'Step Completed', "Hearing minutes recorded - Employee {$attendanceStatus} the hearing");
    
            DB::commit();
    
            return response()->json(['success' => true, 'message' => 'Hearing minutes recorded successfully']);
    
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to record hearing minutes'], 500);
        }
    }

    /**
     * Approve/sign hearing minutes. This app has no canvas-signature infrastructure
     * elsewhere, so signing is a typed-name attestation (matching the confirmation-style
     * conventions already used throughout this codebase) rather than a drawn signature.
     */
    public function approveHearingMinutes(Request $request, $caseId)
    {
        try {
            $case = DisciplineCase::findOrFail($caseId);
            $hearing = $case->hearings()->first();

            if (!$hearing || !$hearing->minutes) {
                return response()->json(['error' => 'Hearing minutes not found'], 404);
            }

            $validated = $request->validate([
                'signature_attestation' => 'required|string|max:255',
            ]);

            $hearing->minutes->update([
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'signature_attestation' => $validated['signature_attestation'],
            ]);

            $this->logActivity($case->id, 10, 'Minutes Approved', 'Hearing minutes signed/approved by ' . (Auth::user()->name ?? Auth::id()));

            return response()->json(['success' => true, 'message' => 'Minutes approved and signed']);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to approve minutes'], 500);
        }
    }

    /**
     * Save final outcome (Step 11)
     */
    public function saveFinalOutcome(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'decision_date' => 'required|date',
                'decision' => 'required|in:No Action,Caution,Verbal Warning,Written Warning,Final Warning,Suspension,Demotion,Dismissal,Summary Dismissal,Other',
                'action_taken_id' => 'nullable|exists:action_taken,id',
                'findings' => 'required|string',
                'reasoning' => 'required|string',
                'sanctions' => 'nullable|string',
                'effective_date' => 'nullable|date',
                'suspension_days' => 'nullable|integer|min:1',
                'appeal_allowed' => 'boolean',
                'appeal_deadline' => 'nullable|date',
                'approved_by' => 'nullable',
                'decided_by_id' => 'required',
                'documents.*' => 'nullable|file|max:10240',
            ]);

            $outcome = DisciplineOutcome::create([
                'case_id' => $case->id,
                'decision_date' => $validated['decision_date'],
                'decision' => $validated['decision'],
                'action_taken_id' => $validated['action_taken_id'] ?? null,
                'findings' => $validated['findings'],
                'reasoning' => $validated['reasoning'],
                'sanctions' => $validated['sanctions'] ?? null,
                'effective_date' => $validated['effective_date'] ?? null,
                'suspension_days' => $validated['suspension_days'] ?? null,
                'appeal_allowed' => $validated['appeal_allowed'] ?? false,
                'appeal_deadline' => $validated['appeal_deadline'] ?? null,
                'decided_by' => $validated['decided_by_id'],
                'approved_by' => $validated['approved_by'] ?? null,
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('discipline/outcome/' . $case->id, 'public');
                    
                    DisciplineOutcomeDocument::create([
                        'outcome_id' => $outcome->id,
                        'document_name' => $file->getClientOriginalName(),
                        'document_path' => $path,
                    ]);
                }
            }

            // Dismissal decisions feed into normal HR separation instead of ending here —
            // auto-create a linked (Pending) termination record so HR completes severance/
            // final-dues detail in the existing termination screens rather than re-entering
            // everything from scratch in a disconnected module.
            if (in_array($validated['decision'], ['Dismissal', 'Summary Dismissal'])) {
                $this->createLinkedTermination($case, $outcome);
            }

            // Update case step
            $case->update(['current_step' => 12]);

            // Log activity
            $this->logActivity($case->id, 11, 'Step Completed', 'Final outcome recorded');

            // Send notification to employee
            $this->sendStepNotification($case, 11, 'outcome_decided');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Outcome saved successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to save outcome'], 500);
        }
    }

    /**
     * Assign training (Step 12 - Optional)
     */
    public function assignTraining(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'training_type' => 'required|string|max:255',
                'training_provider' => 'nullable|string|max:255',
                'training_description' => 'nullable|string',
                'scheduled_date' => 'nullable|date',
                'completion_deadline' => 'nullable|date',
            ]);

            DisciplineTrainingAssignment::create([
                'case_id' => $case->id,
                'training_type' => $validated['training_type'],
                'training_provider' => $validated['training_provider'],
                'training_description' => $validated['training_description'],
                'scheduled_date' => $validated['scheduled_date'],
                'completion_deadline' => $validated['completion_deadline'],
                'assigned_by' => Auth::id(),
            ]);

            // Update case step
            $case->update(['current_step' => 13]);

            // Log activity
            $this->logActivity($case->id, 12, 'Step Completed', 'Training assigned');

            // Send notification to employee
            $this->sendStepNotification($case, 12, 'training_assigned');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Training assigned successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to assign training'], 500);
        }
    }

    /**
     * Update disciplinary history (Step 13)
     */
    public function updateDisciplinaryHistory(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'update_type' => 'required|in:Warning Added,Sanction Recorded,Performance Review Flag,Promotion Block,Record Expunged',
                'details' => 'nullable|string',
                'expiry_date' => 'nullable|date',
            ]);

            DisciplineHistoryUpdate::create([
                'case_id' => $case->id,
                'update_type' => $validated['update_type'],
                'details' => $validated['details'],
                'expiry_date' => $validated['expiry_date'],
                'updated_by' => Auth::id(),
            ]);

            // Update case step
            $case->update(['current_step' => 14]);

            // Log activity
            $this->logActivity($case->id, 13, 'Step Completed', 'Disciplinary history updated');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Disciplinary history updated successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to update disciplinary history'], 500);
        }
    }

    /**
     * Close case (Step 14)
     */
    public function closeCase(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'closure_date' => 'required|date',
                'closure_reason' => 'required|in:Resolved,Withdrawn,No Case to Answer,Settled,Other',
                'closure_notes' => 'nullable|string',
                'lessons_learned' => 'nullable|string',
                'policy_recommendations' => 'nullable|string',
            ]);

            DisciplineCaseClosure::create([
                'case_id' => $case->id,
                'closure_date' => $validated['closure_date'],
                'closure_reason' => $validated['closure_reason'],
                'closure_notes' => $validated['closure_notes'],
                'lessons_learned' => $validated['lessons_learned'],
                'policy_recommendations' => $validated['policy_recommendations'],
                'closed_by' => Auth::id(),
            ]);

            // Update case
            $case->update([
                'current_step' => 14,
                'current_status_id' => 42, // Closed
                'closed_at' => now(),
                'closed_by' => Auth::id(),
            ]);

            // Log activity
            $this->logActivity($case->id, 14, 'Step Completed', 'Case closed');

            // Send final notification
            $this->sendStepNotification($case, 14, 'case_closed');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Case closed successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to close case'], 500);
        }
    }

    /**
     * Employee files an appeal against a decided outcome — self-service, gated by
     * outcome.appeal_allowed and, critically, actually enforces the appeal_deadline
     * server-side (previously nothing did).
     */
    public function fileAppeal(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            $outcome = $case->outcome;

            if (!$outcome) {
                return response()->json(['error' => 'This case has no decision to appeal'], 422);
            }

            if (!$outcome->appeal_allowed) {
                return response()->json(['error' => 'This decision is not open to appeal'], 422);
            }

            if ($outcome->appeal_deadline && now()->startOfDay()->gt(\Carbon\Carbon::parse($outcome->appeal_deadline)->endOfDay())) {
                return response()->json(['error' => 'The appeal window (7 days) for this decision has closed'], 422);
            }

            $existing = DisciplineAppeal::where('outcome_id', $outcome->id)
                ->where('status', '!=', 'Withdrawn')
                ->first();
            if ($existing) {
                return response()->json(['error' => 'An appeal has already been filed for this decision'], 422);
            }

            // Only the employee themselves may appeal their own case.
            $staffLink = \App\Models\UserStaffLink::where('user_id', Auth::id())->first();
            if (!$staffLink || $case->staff_id !== $staffLink->staff_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'grounds' => 'required|string',
            ]);

            DisciplineAppeal::create([
                'case_id' => $case->id,
                'outcome_id' => $outcome->id,
                'filed_by' => Auth::id(),
                'filed_at' => now(),
                'grounds' => $validated['grounds'],
                'status' => 'Pending',
            ]);

            $this->logActivity($case->id, $case->current_step, 'Appeal Filed', 'Employee filed an appeal against the decision');
            $this->sendStepNotification($case, $case->current_step, 'appeal_filed');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Appeal filed successfully']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to file appeal'], 500);
        }
    }

    /**
     * HR review screen — list pending appeals.
     */
    public function appealsIndex()
    {
        $appeals = DisciplineAppeal::with(['case.staff', 'outcome', 'filedBy'])
            ->orderBy('filed_at', 'desc')
            ->get();

        return view('discipline_tracker.appeals.index', compact('appeals'));
    }

    /**
     * HR decides an appeal: uphold the original decision, overturn it, or modify it.
     */
    public function decideAppeal(Request $request, $appealId)
    {
        DB::beginTransaction();
        try {
            $appeal = DisciplineAppeal::findOrFail($appealId);

            if ($appeal->status !== 'Pending') {
                return response()->json(['error' => 'This appeal has already been decided'], 422);
            }

            $validated = $request->validate([
                'decision_action' => 'required|in:Uphold Original Decision,Overturn Decision,Modify Decision',
                'review_notes' => 'required|string',
            ]);

            $newStatus = $validated['decision_action'] === 'Uphold Original Decision' ? 'Rejected' : 'Upheld';

            $appeal->update([
                'status' => $newStatus,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'],
                'decision_action' => $validated['decision_action'],
            ]);

            $this->logActivity($appeal->case_id, $appeal->case->current_step, 'Appeal Decided', $validated['decision_action'] . ': ' . $validated['review_notes']);
            $this->sendStepNotification($appeal->case, $appeal->case->current_step, 'appeal_decided');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Appeal decision recorded']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to record appeal decision'], 500);
        }
    }

    /**
     * Auto-create a (Pending) EmployeeTermination linked back to this discipline case/outcome,
     * so a Dismissal decision actually feeds into the normal HR separation process instead of
     * requiring HR to re-enter everything from scratch in the unrelated termination module.
     * Best-effort: pre-fills what's knowable from the staff's latest contract; HR still reviews
     * and completes severance/final-dues detail via the existing termination screens.
     */
    private function createLinkedTermination(DisciplineCase $case, DisciplineOutcome $outcome): void
    {
        $contract = Contract::where('staff_id', $case->staff_id)->latest('date_start')->first();

        if (!$contract) {
            Log::warning('createLinkedTermination: no contract found for staff, skipping auto-linked termination', [
                'case_id' => $case->id,
                'staff_id' => $case->staff_id,
            ]);
            return;
        }

        $yearsServed = $contract->date_start ? abs(now()->diffInYears($contract->date_start)) : 0;

        EmployeeTermination::create([
            'date_time' => now(),
            'staff_id' => $case->staff_id,
            'discipline_case_id' => $case->id,
            'discipline_outcome_id' => $outcome->id,
            'contract_id' => $contract->id,
            'years_served' => $yearsServed,
            'pay_per_year' => (float) $contract->basic_salary * 12,
            'severerance_payment' => 0,
            'termination_reason_id' => 3, // Termination — the only fixed option that fits a disciplinary dismissal
            'documents' => 'NO',
            'current_status_id' => 1, // Pending — HR completes severance/final-dues detail
            'created_by_id' => Auth::id(),
        ]);
    }

    /**
     * Generate the decision/implementation letter for a decided case.
     */
    public function generateDecisionLetter($caseId)
    {
        $case = DisciplineCase::with(['staff', 'position', 'outcome'])->findOrFail($caseId);

        if (!$case->outcome) {
            abort(404, 'No decision recorded for this case yet');
        }

        $pdfContent = $this->generatePdf('discipline_tracker.pdf.decision-letter', [
            'case' => $case,
            'outcome' => $case->outcome,
        ]);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Decision_Letter_' . $case->case_number . '.pdf"',
        ]);
    }

    /**
     * Generate a certificate of service once a linked termination is marked Completed.
     */
    public function generateCertificateOfService($terminationId)
    {
        $termination = EmployeeTermination::with(['staff', 'contract'])->findOrFail($terminationId);

        $pdfContent = $this->generatePdf('discipline_tracker.pdf.certificate-of-service', [
            'termination' => $termination,
        ]);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Certificate_of_Service_' . ($termination->staff->full_names ?? $termination->id) . '.pdf"',
        ]);
    }

    /**
     * Save (create or update) the exit interview for a termination record.
     */
    public function saveExitInterview(Request $request, $terminationId)
    {
        try {
            $termination = EmployeeTermination::findOrFail($terminationId);

            $validated = $request->validate([
                'reason_confirmation' => 'nullable|string',
                'feedback' => 'nullable|string',
                'assets_returned' => 'nullable|boolean',
                'assets_notes' => 'nullable|string',
                'would_recommend_rating' => 'nullable|integer|min:1|max:5',
            ]);

            ExitInterview::updateOrCreate(
                ['termination_id' => $termination->id],
                array_merge($validated, [
                    'conducted_by' => Auth::id(),
                    'conducted_at' => now(),
                ])
            );

            return response()->json(['success' => true, 'message' => 'Exit interview recorded']);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to save exit interview'], 500);
        }
    }

    /**
     * Save (create or update) the itemized final-dues settlement for a termination record.
     * Amounts are HR-entered (unpaid salary, leave payout, other earnings, deductions,
     * severance) — the total is computed here rather than trusting a client-submitted total.
     */
    public function saveFinalDues(Request $request, $terminationId)
    {
        try {
            $termination = EmployeeTermination::findOrFail($terminationId);

            $validated = $request->validate([
                'unpaid_salary' => 'nullable|numeric|min:0',
                'leave_payout' => 'nullable|numeric|min:0',
                'other_earnings' => 'nullable|numeric|min:0',
                'deductions' => 'nullable|numeric|min:0',
                'severance_payment' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $total = (float) ($validated['unpaid_salary'] ?? 0)
                + (float) ($validated['leave_payout'] ?? 0)
                + (float) ($validated['other_earnings'] ?? 0)
                - (float) ($validated['deductions'] ?? 0)
                + (float) ($validated['severance_payment'] ?? 0);

            FinalDues::updateOrCreate(
                ['termination_id' => $termination->id],
                array_merge($validated, [
                    'total_amount' => $total,
                    'computed_by' => Auth::id(),
                    'computed_at' => now(),
                ])
            );

            // Keep the termination's own severance field in sync — it's the figure the rest
            // of the HR termination module (reports, etc.) already reads.
            $termination->update(['severerance_payment' => $validated['severance_payment'] ?? 0]);

            return response()->json(['success' => true, 'message' => 'Final dues recorded', 'total' => $total]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to save final dues'], 500);
        }
    }

    /**
     * Skip a step
     */
    public function skipStep(Request $request, $caseId)
    {
        DB::beginTransaction();
        try {
            $case = DisciplineCase::findOrFail($caseId);
            
            $validated = $request->validate([
                'step_number' => 'required|integer|min:1|max:14',
                'reason' => 'required|string',
            ]);

            // Update case to next step
            $case->update(['current_step' => ($validated['step_number'] + 1)]);

            // Log activity
            $this->logActivity(
                $case->id, 
                $validated['step_number'], 
                'Step Skipped', 
                'Reason: ' . $validated['reason']
            );

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Step skipped successfully']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Exception $ex) {
                Log::error('ErrorDispatcher failed: ' . $ex->getMessage());
            }
            return response()->json(['error' => 'Failed to skip step'], 500);
        }
    }

    /**
     * Get activity timeline for a case
     */
    public function getTimeline($caseId)
    {
        try {
            $timeline = DisciplineActivityLog::where('case_id', $caseId)
                ->with('performedBy:id,name')
                ->orderBy('performed_at', 'desc')
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'step_number' => $activity->step_number,
                        'step_name' => $activity->step_name,
                        'activity_type' => $activity->activity_type,
                        'description' => $activity->activity_description,
                        'performed_by' => $activity->performedBy->name ?? 'System',
                        'performed_at' => $activity->performed_at->format('Y-m-d H:i:s'),
                        'metadata' => $activity->metadata,
                    ];
                });

            return response()->json(['timeline' => $timeline]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load timeline'], 500);
        }
    }

    // Helper Methods

    /**
     * Get step name from number
     */
    private function getStepName($stepNumber)
    {
        $steps = [
            1 => 'Create Incident Report',
            2 => 'Register Case & Notify HR',
            3 => 'Assign Investigator',
            4 => 'Upload Evidence',
            5 => 'Add Witness Statements',
            6 => 'Record Investigation Notes',
            7 => 'Issue Show Cause Notice',
            8 => 'Employee Submits Response',
            9 => 'Schedule Hearing',
            10 => 'Record Hearing Minutes',
            11 => 'Save Final Outcome',
            12 => 'Assign Training (Optional)',
            13 => 'Update Employee Disciplinary History',
            14 => 'Close Case',
        ];

        return $steps[$stepNumber] ?? 'Unknown Step';
    }

    /**
     * Get available steps based on current progress
     */
    private function getAvailableSteps($case)
    {
        $allSteps = [
            1 => ['name' => 'Create Incident Report', 'required' => true],
            2 => ['name' => 'Register Case & Notify HR', 'required' => true],
            3 => ['name' => 'Assign Investigator', 'required' => false],
            4 => ['name' => 'Upload Evidence', 'required' => false],
            5 => ['name' => 'Add Witness Statements', 'required' => false],
            6 => ['name' => 'Record Investigation Notes', 'required' => false],
            7 => ['name' => 'Issue Show Cause Notice', 'required' => true],
            8 => ['name' => 'Employee Submits Response', 'required' => true],
            9 => ['name' => 'Schedule Hearing', 'required' => false],
            10 => ['name' => 'Record Hearing Minutes', 'required' => false],
            11 => ['name' => 'Save Final Outcome', 'required' => true],
            12 => ['name' => 'Assign Training (Optional)', 'required' => false],
            13 => ['name' => 'Update Employee Disciplinary History', 'required' => true],
            14 => ['name' => 'Close Case', 'required' => true],
        ];

        return $allSteps;
    }

    /**
     * Log activity
     */
    private function logActivity($caseId, $stepNumber, $activityType, $description, $metadata = null)
    {
        try {
            Log::info("Attempting to log activity: Case ID {$caseId}, Step {$stepNumber}, Type: {$activityType}");
            
            $activityLog = DisciplineActivityLog::create([
                'case_id' => $caseId,
                'step_number' => $stepNumber,
                'step_name' => $this->getStepName($stepNumber),
                'activity_type' => $activityType,
                'activity_description' => $description,
                'performed_by' => Auth::id(),
                'metadata' => $metadata ? json_encode($metadata) : null,
            ]);
    
            Log::info("Discipline Activity Logged Successfully: ID {$activityLog->id}, Case ID {$caseId}, Step {$stepNumber}, Type: {$activityType}");
            
            return $activityLog;
            
        } catch (\Exception $e) {
            Log::error("Failed to log discipline activity: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            // Don't throw exception - let the main process continue even if logging fails
            return null;
        }
    }

    /**
     * Send step notification
     */
    private function sendStepNotification($case, $stepNumber, $notificationType)
    {
        // Queue notification job
        SendDisciplineNotification::dispatch($case, $stepNumber, $notificationType);
    }
}