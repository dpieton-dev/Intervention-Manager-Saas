<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseService
{
    public function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function error(
        string $message,
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        return new JsonResponse([
            'success' => false,
            'code' => $statusCode,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}