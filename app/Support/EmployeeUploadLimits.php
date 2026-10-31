<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class EmployeeUploadLimits
{
    public static function current(): array
    {
        $raw = trim((string) ini_get('post_max_size'));
        $size = (float) $raw;
        $multiplier = match (strtolower(substr($raw, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };

        return ['file' => UploadedFile::getMaxFilesize(), 'post' => (int) ($size * $multiplier)];
    }
}
