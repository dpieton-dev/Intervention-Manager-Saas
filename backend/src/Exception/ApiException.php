<?php

namespace App\Exception;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        string $message,
        private int $statusCode = 400,
        private mixed $errors = null
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): mixed
    {
        return $this->errors;
    }
}