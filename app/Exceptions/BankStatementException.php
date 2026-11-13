<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a bank statement cannot be uploaded, confirmed or matched. The message is written for the person using
 * the Bank Statements page, so it is safe to show on screen.
 */
class BankStatementException extends RuntimeException
{
}
