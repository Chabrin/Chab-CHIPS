<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\TempBatchTenant;
use App\Models\TempBatchUnit;
use App\Services\ErrorDispatcher;
use App\Services\UnitTenantUpload\UnitTenantSubmitService;
use App\Services\UnitTenantUpload\UnitTenantUploadBatchService;
use App\Services\UnitTenantUpload\UnitTenantUploadParserService;
use App\Services\UnitTenantUpload\UnitTenantUploadRejected;
use App\Services\UnitTenantUpload\UnitTenantUploadTemplateService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Units & tenants bulk upload (client page "Bulk Upload" tab): HTTP only, the work is in the services.
 */
class UnitTenantUploadController extends Controller
{
    private UnitTenantUploadTemplateService $templates;
    private UnitTenantUploadParserService $parser;
    private UnitTenantUploadBatchService $batches;
    private UnitTenantSubmitService $submitter;

    /**
     * Inject the upload services.
     */
    public function __construct(UnitTenantUploadTemplateService $templates, UnitTenantUploadParserService $parser, UnitTenantUploadBatchService $batches, UnitTenantSubmitService $submitter)
    {
        $this->templates = $templates;
        $this->parser = $parser;
        $this->batches = $batches;
        $this->submitter = $submitter;
    }

    /**
     * Whether the user may create units, tenants and leases (the same permissions as the manual forms).
     */
    private function allowed(): bool
    {
        $user = Auth::user();

        return $user && $user->hasPermission('create_units') && $user->hasPermission('create_tenants') && $user->hasPermission('create_lease_agreements');
    }

    /**
     * Download the Excel template for a property.
     */
    public function downloadTemplate(Request $request)
    {
        if (!$this->allowed()) {
            abort(403, 'You do not have permission to use the bulk upload.');
        }

        try {
            $request->validate(['property_id' => 'required|integer|min:1|max:2147483647']);
            $property = Property::findOrFail($request->property_id);
            $spreadsheet = $this->templates->build($property);

            return response()->streamDownload(function () use ($spreadsheet) {
                (new Xlsx($spreadsheet))->save('php://output');
            }, $this->templates->fileName($property), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        } catch (ValidationException | ModelNotFoundException $e) {
            return redirect()->back()->with('error', 'Select a valid property before downloading the template.');
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating units/tenants template: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate the template');
        }
    }

    /**
     * Parse an uploaded file and stage it as a new batch.
     */
    public function import(Request $request)
    {
        return $this->run(function () use ($request) {
            $request->validate([
                'property_id' => 'required|integer|min:1|max:2147483647',
                'bulk_file' => 'required|file|mimes:xlsx|max:10240',
            ]);

            $property = Property::findOrFail($request->property_id);
            $parsed = $this->parser->parse($request->file('bulk_file')->getPathname());
            $batch = $this->batches->stage($property, $parsed, $request->file('bulk_file')->getClientOriginalName(), Auth::id());

            return ['message' => "Staged {$batch->total_units} unit(s) and {$batch->total_tenants} tenant(s).", 'batch_id' => $batch->id];
        }, 'Failed to process the upload');
    }

    /**
     * Batches for a property.
     */
    public function batches(Request $request)
    {
        return $this->run(function () use ($request) {
            $request->validate(['property_id' => 'required|integer|min:1|max:2147483647']);

            return ['data' => $this->batches->listFor(Property::findOrFail($request->property_id))];
        }, 'Failed to load batches');
    }

    /**
     * One batch with its rows, for the review screen.
     */
    public function show($id)
    {
        return $this->run(fn() => $this->batches->details($this->batches->find((int) $id)), 'Failed to load the batch');
    }

    /**
     * Edit a staged unit row.
     */
    public function updateUnit(Request $request, $id)
    {
        return $this->run(fn() => ['message' => 'Row updated.', 'row' => $this->batches->updateUnit(TempBatchUnit::findOrFail((int) $id), $request->all())], 'Failed to update the row');
    }

    /**
     * Edit a staged tenant row.
     */
    public function updateTenant(Request $request, $id)
    {
        return $this->run(fn() => ['message' => 'Row updated.', 'row' => $this->batches->updateTenant(TempBatchTenant::findOrFail((int) $id), $request->all())], 'Failed to update the row');
    }

    /**
     * Delete a staged unit row (and its tenant row).
     */
    public function destroyUnit($id)
    {
        return $this->run(function () use ($id) {
            $this->batches->deleteUnit(TempBatchUnit::findOrFail((int) $id));
            return ['message' => 'Unit row deleted.'];
        }, 'Failed to delete the row');
    }

    /**
     * Delete a staged tenant row.
     */
    public function destroyTenant($id)
    {
        return $this->run(function () use ($id) {
            $this->batches->deleteTenant(TempBatchTenant::findOrFail((int) $id));
            return ['message' => 'Tenant row deleted.'];
        }, 'Failed to delete the row');
    }

    /**
     * Submit a batch: create the units, tenants and leases.
     */
    public function submit($id)
    {
        return $this->run(function () use ($id) {
            @set_time_limit(300);
            $result = $this->submitter->submit($this->batches->find((int) $id), Auth::id());

            return ['message' => "Submitted {$result['submitted']} row(s). {$result['failed']} row(s) still need attention.", 'submitted' => $result['submitted'], 'failed' => $result['failed']];
        }, 'Failed to submit the batch');
    }

    /**
     * Cancel a staged batch.
     */
    public function cancel($id)
    {
        return $this->run(function () use ($id) {
            $this->batches->cancel($this->batches->find((int) $id), Auth::id());
            return ['message' => 'Batch cancelled.'];
        }, 'Failed to cancel the batch');
    }

    /**
     * Soft delete a batch.
     */
    public function destroyBatch($id)
    {
        return $this->run(function () use ($id) {
            $this->batches->deleteBatch($this->batches->find((int) $id));
            return ['message' => 'Batch deleted.'];
        }, 'Failed to delete the batch');
    }

    /**
     * Run an action and turn its result or failure into JSON.
     */
    private function run(callable $action, string $failure)
    {
        if (!$this->allowed()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to use the bulk upload.'], 403);
        }

        try {
            return response()->json(['success' => true] + $action());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (UnitTenantUploadRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (DomainException | InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        } catch (\Throwable $e) {
            ErrorDispatcher::dispatch($e);
            Log::error($failure . ': ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $failure], 500);
        }
    }
}
