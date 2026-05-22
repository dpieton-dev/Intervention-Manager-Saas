<?php

namespace App\Exception;

class UnauthorizedException extends ApiException
{
    public function __construct(
        string $message = 'User not authenticated'
    ) {
        parent::__construct($message, 401);
    }
}