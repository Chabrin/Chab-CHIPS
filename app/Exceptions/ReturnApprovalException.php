<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a client return cannot be sent for approval or moved on to the next level, for example because the
 * returns approval workflow is not set up. The message is written for the person using the screen, so it is safe
 * to show on screen.
 */
class ReturnApprovalException extends RuntimeException
{
}
