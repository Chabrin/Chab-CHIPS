<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staff document that was removed or replaced. The file stays on disk; this row says what it was, who removed it and when.
 * See EmployeeDocument::removeKeepingHistory().
 */
class EmployeeDocumentRemoval extends Model
{
    public $timestamps = false;

    protected $table = 'employee_document_removals';

    protected $fillable = [
        'original_id', 'employee_id', 'contract_id', 'document_type_id', 'title', 'file_path', 'file_name', 'file_size',
        'mime_type', 'uploaded_by', 'uploaded_at', 'removed_by', 'removed_at', 'reason',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function remover()
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
