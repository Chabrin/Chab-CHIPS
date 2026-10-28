<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeUploadGuard
{
    /** Check file contents across photos and documents before anything is saved. */
    public function validate(array $files): void
    {
        $fingerprints = [];
        foreach ($files as $field => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $fingerprints[$field] = hash_file('sha256', $file->getRealPath());
            }
        }
        $this->validateHashes($fingerprints);
    }

    /** Browser preflight uses fingerprints; saving always hashes the actual files again. */
    public function validateHashes(array $fingerprints): void
    {
        $hashes = [];
        $errors = [];

        foreach ($fingerprints as $field => $hash) {
            if (isset($hashes[$hash])) {
                $errors[$field] = 'This file is selected in another upload field. Please select a different file.';
            }
            $hashes[$hash][] = $field;
        }

        if (!$hashes) {
            return;
        }

        // Read existing files too, so uploads made before this check are covered.
        foreach (DB::table('employee_documents')->select('file_path')->cursor() as $document) {
            $this->checkStoredFile('local', $document->file_path, $hashes, $errors);
        }
        foreach (DB::table('sys_staff')->whereNotNull('photo')->select('photo')->cursor() as $staff) {
            $this->checkStoredFile('public', $staff->photo, $hashes, $errors);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function checkStoredFile(string $disk, ?string $path, array $hashes, array &$errors): void
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
            $errors[$field] = 'This file has already been uploaded. Please select a different file.';
        }
    }
}
