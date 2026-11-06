<?php

namespace App\Services;

use App\Models\TenantOnboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantOnboardingFiles
{
    public function stage(Request $request, array $existing = []): array
    {
        $documents = $existing;
        $stored = [];
        try {
            foreach (\Illuminate\Support\Arr::dot($request->allFiles()) as $field => $file) {
                if (!preg_match('/^(tenant_photo|tenant_files\.\d+\.file|inspection_(photos|videos)\.\d+)$/', $field)) {
                    continue;
                }
                $path = $file->store('tenant-onboarding', 'local');
                if (!$path) {
                    throw new \RuntimeException('The attachment could not be saved. Please try again.');
                }
                $stored[] = $path;
                $documents[$field] = [
                    'path' => $path, 'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(), 'extension' => $file->extension(),
                    'hash' => hash_file('sha256', $file->getRealPath()),
                ];
            }
            return $documents;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }
    }

    public function publish(TenantOnboarding $submission): array
    {
        $paths = [];
        try {
            foreach ($submission->documents as $field => $document) {
                $content = Storage::disk('local')->get($document['path']);
                if ($content === null || !hash_equals($document['hash'], hash('sha256', $content))) {
                    throw new \RuntimeException('A reviewed attachment is missing or changed. Return this submission for correction.');
                }
                $extension = $document['extension'];
                $path = 'tenants/onboarding/'.$submission->id.'/'.Str::uuid().'.'.$extension;
                if (!Storage::disk('public')->put($path, $content)) {
                    throw new \RuntimeException('The approved attachment could not be saved.');
                }
                $paths[$field] = $path;
            }
            return $paths;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete(array_values($paths));
            throw $e;
        }
    }
}
