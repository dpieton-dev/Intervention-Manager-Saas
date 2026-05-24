<?php

namespace App\Exception;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Access denied') 
    {
        parent::__construct($message, 403);
    }
}