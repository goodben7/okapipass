<?php

namespace App\Exception;

/**
 * Resource conflict (seat taken, already issued, already declared…).
 * Mapped to HTTP 409.
 */
class ConflictException extends \RuntimeException
{
}
