<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\DocumentType;
use App\Services\EmployeeUploadGuard;
use App\Services\StaffDocumentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EmployeeDocumentController extends Controller
{
    public function checkDuplicates(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('update_staff') || auth()->user()->hasPermission('create_users'), 403);

        $validated = $request->validate([
            'hashes' => 'required|array|min:1|max:10',
            'hashes.*' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'staff_id' => 'nullable|integer|min:1',
        ]);
        app(EmployeeUploadGuard::class)->validateHashes($validated['hashes'], isset($validated['staff_id']) ? (int) $validated['staff_id'] : null);

        return response()->json(['valid' => true]);
    }

    /**
     * Upload an employee document (REQ-01)
     */
    public function store(Request $request, Employee $employee)
    {
        abort_unless(auth()->user()->hasPermission('update_staff'), 403);

        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'title' => 'required|string|max:255',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // max 5MB
        ]);

        $file = $request->file('document_file');
        app(EmployeeUploadGuard::class)->validate(['document_file' => $file], (int) $employee->id);
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = round($file->getSize() / 1024); // KB

        // Store privately inside storage/app/employee_documents/
        $storedPath = $file->store('employee_documents/' . $employee->id, 'local');

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type_id' => $request->document_type_id,
            'title' => $request->title,
            'file_path' => $storedPath,
            'file_name' => $originalName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Securely download/view an employee document (REQ-01)
     */
    public function download(EmployeeDocument $document)
    {
        // Staff documents are for the HR role and the Directors only, as well as the usual staff permission
        abort_unless(app(StaffDocumentAccess::class)->allows(auth()->user()), 403);

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(Response::HTTP_NOT_FOUND, 'The requested document does not exist.');
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
        /**
     * Open an employee document in the browser (PDF/images)
     */
    public function view(EmployeeDocument $document)
    {
        abort_unless(app(StaffDocumentAccess::class)->allows(auth()->user()), 403);

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(Response::HTTP_NOT_FOUND, 'The requested document does not exist.');
        }

        return Storage::disk('local')->response($document->file_path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    /**
     * Remove an uploaded document. It leaves the staff member's documents (and the contract it was attached to) but is not
     * destroyed: the file stays on disk and the removal is recorded with who did it and when (employee_document_removals).
     */
    public function destroy(Request $request, EmployeeDocument $document)
    {
        abort_unless(app(StaffDocumentAccess::class)->allowsChanges(auth()->user()), 403);

        $document->removeKeepingHistory(auth()->id(), 'Removed');
        $message = 'Document removed. A copy is kept in the history.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Upload or update employee staff photo
     */
    public function updatePhoto(Request $request, Employee $employee)
    {
        abort_unless(auth()->user()->hasPermission('update_staff'), 403);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // max 2MB
        ]);

        app(EmployeeUploadGuard::class)->validate(['photo' => $request->file('photo')], (int) $employee->id);

        // Delete old photo if present
        if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
            Storage::disk('public')->delete($employee->photo);
        }

        // Store inside storage/app/public/staff_photos/
        $photoPath = $request->file('photo')->store('staff_photos', 'public');

        $employee->update(['photo' => $photoPath]);

        return back()->with('success', 'Staff photo updated successfully.');
    }

    /**
     * REQ-01: Document Management & Compliance Report
     */
    public function report(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('read_staff'), 403);

        $query = EmployeeDocument::with(['employee', 'documentType', 'uploader']);

        // Filter by Document Type
        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->document_type_id);
        }

        // Filter by Employee Search
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('employee', function ($q) use ($searchTerm) {
                $q->where('full_names', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('staff_number', 'ILIKE', "%{$searchTerm}%");
            });
        }

        // Filter by Date Range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $documents = $query->latest()->paginate(20);
        $documentTypes = DocumentType::all();

        return view('hr.reports.employee_documents', compact('documents', 'documentTypes'));
    }
}
