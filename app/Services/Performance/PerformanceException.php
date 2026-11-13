<?php

namespace App\Services\Performance;

use RuntimeException;

/** Something the person asked for cannot be done; the message is written to be shown to them as it is. */
class PerformanceException extends RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
