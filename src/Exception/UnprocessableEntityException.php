<?php

namespace App\Exception;

/**
 * Business / input validation that should surface as HTTP 422.
 * Does not replace Symfony Validator 422 on DTO constraints.
 */
class UnprocessableEntityException extends \InvalidArgumentException
{
}
