<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a posting to a client's account cannot be made. The message is written for the
 * person approving, so it is safe to show on screen.
 */
class ClientAccountException extends RuntimeException
{
}
