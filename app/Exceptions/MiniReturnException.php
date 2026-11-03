<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a mini return cannot be generated, for example because the month's main return is not approved yet or
 * nothing has come in since the last return. The message is written for the person generating, so it is safe to
 * show on screen.
 */
class MiniReturnException extends RuntimeException
{
}
