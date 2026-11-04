<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A staff member's own signature, given either as an uploaded image (signature_file)
 * or drawn on screen (signature_data, a PNG data URL). Stored on the private local disk.
 */
trait HandlesStaffSignature
{
    private const SIGNATURE_MAX_BYTES = 1024 * 1024;

    protected function signatureRules(): array
    {
        return [
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            // Base64 inflates ~4/3, so this comfortably covers a 1 MB PNG
            'signature_data' => 'nullable|string|max:1400000',
        ];
    }

    protected function hasSignatureInput(Request $request): bool
    {
        return $request->hasFile('signature_file') || filled($request->input('signature_data'));
    }

    /** Save the submitted signature under $directory and return its path, or null when none was given. */
    protected function storeSignature(Request $request, string $directory): ?string
    {
        if ($request->hasFile('signature_file')) {
            return $request->file('signature_file')->store($directory, 'local');
        }

        $data = (string) $request->input('signature_data');
        if ($data === '') {
            return null;
        }

        $prefix = 'data:image/png;base64,';
        $png = str_starts_with($data, $prefix) ? base64_decode(substr($data, strlen($prefix)), true) : false;
        if ($png === false || !str_starts_with($png, "\x89PNG\r\n\x1a\n") || strlen($png) > self::SIGNATURE_MAX_BYTES || @getimagesizefromstring($png) === false) {
            throw ValidationException::withMessages(['signature_data' => 'The drawn signature could not be read. Clear it and draw it again, or upload an image instead.']);
        }

        $path = trim($directory, '/') . '/signature-' . Str::random(32) . '.png';
        Storage::disk('local')->put($path, $png);
        return $path;
    }
}
