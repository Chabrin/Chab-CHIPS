<?php

namespace App\Traits;

use App\Services\Performance\PerformanceException;
use Illuminate\Http\JsonResponse;

/** Lets a Performance Review controller action throw a PerformanceException and answer with its message as JSON. */
trait RespondsToPerformanceErrors
{
    protected function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (PerformanceException $exception) {
            return response()->json(['success' => false, 'error' => $exception->getMessage()], $exception->status());
        }
    }
}
