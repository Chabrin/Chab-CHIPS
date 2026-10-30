<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeUploadGuard
{
    /** Check file contents across photos and documents before anything is saved. */
    public function validate(array $files, ?int $staffId = null): void
    {
        $fingerprints = [];
        foreach ($files as $field => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $fingerprints[$field] = hash_file('sha256', $file->getRealPath());
            }
        }
        $this->validateHashes($fingerprints, $staffId);
    }

    /** Browser preflight uses fingerprints; saving always hashes the actual files again. */
    public function validateHashes(array $fingerprints, ?int $staffId = null): void
    {
        $hashes = [];
        $errors = [];

        foreach ($fingerprints as $field => $hash) {
            if (isset($hashes[$hash])) {
                $other = $this->label($hashes[$hash][0]);
                $errors[$field] = $this->label($field) . ": This file is already selected for {$other}. Choose a different file.";
            }
            $hashes[$hash][] = $field;
        }

        if (!$hashes) {
            return;
        }

        // Read existing files too, so uploads made before this check are covered.
        foreach (DB::table('employee_documents')->select('file_path', 'employee_id')->cursor() as $document) {
            $this->checkStoredFile('local', $document->file_path, $hashes, $errors, $staffId, (int) $document->employee_id);
        }
        foreach (DB::table('sys_staff')->whereNotNull('photo')->select('id', 'photo')->cursor() as $staff) {
            $this->checkStoredFile('public', $staff->photo, $hashes, $errors, $staffId, (int) $staff->id);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function checkStoredFile(string $disk, ?string $path, array $hashes, array &$errors, ?int $staffId, int $ownerId): void
    {
        if (!$path || !Storage::disk($disk)->exists($path)) {
            return;
        }

        $stream = Storage::disk($disk)->readStream($path);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to read an existing employee upload.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            $hash = hash_final($context);
        } finally {
            fclose($stream);
        }

        foreach ($hashes[$hash] ?? [] as $field) {
            $owner = $staffId === $ownerId ? 'this employee' : 'another employee';
            $errors[$field] = $this->label($field) . ": This exact file is already saved for {$owner}. Choose a different file; renaming it will not remove the duplicate.";
        }
    }

    private function label(string $field): string
    {
        return match ($field) {
            'photo' => 'Staff photo', 'documents.national_id' => 'National ID / Passport copy',
            'documents.kra_pin' => 'KRA PIN certificate', 'documents.contract', 'signed_contract' => 'Signed employment contract',
            'documents.cv' => 'CV', 'documents.academic' => 'Academic certificates', default => 'Document',
        };
    }
}
