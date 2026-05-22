<?php

namespace App\Exception;

class ValidationException extends ApiException
{
    public function __construct(
        mixed $errors = null,
        string $message = 'Validation failed'
    ) {
        parent::__construct($message, 422, $errors);
    }
}