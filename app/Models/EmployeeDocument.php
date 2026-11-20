<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $fillable = [
        'employee_id',
        'contract_id',
        'document_type_id',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Take this document out of use without losing it: the row leaves employee_documents (so there is one current copy and the
     * rest of the system never sees the old one), the FILE STAYS on disk, and a row in employee_document_removals records what it
     * was, who removed it, when and why. Use this instead of delete() for any document a person might need to look back at.
     */
    public function removeKeepingHistory(?int $removedBy = null, string $reason = 'Removed'): void
    {
        DB::transaction(function () use ($removedBy, $reason) {
            if (Schema::hasTable('employee_document_removals')) {
                EmployeeDocumentRemoval::create([
                    'original_id' => $this->id,
                    'employee_id' => $this->employee_id,
                    'contract_id' => $this->contract_id,
                    'document_type_id' => $this->document_type_id,
                    'title' => $this->title,
                    'file_path' => $this->file_path,
                    'file_name' => $this->file_name,
                    'file_size' => $this->file_size,
                    'mime_type' => $this->mime_type,
                    'uploaded_by' => $this->uploaded_by,
                    'uploaded_at' => $this->created_at,
                    'removed_by' => $removedBy,
                    'removed_at' => now(),
                    'reason' => $reason,
                ]);
            } else {
                // The history table comes with a migration. Never lose the file because it has not been run yet.
                Log::warning('employee_document_removals is missing: the file of document ' . $this->id . ' is kept on disk but its history row was not written.');
            }
            $this->delete();
        });
    }
}