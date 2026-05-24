<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseService
{
    public function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse 
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function error(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'code' => $statusCode,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    public function validationError(iterable $errors): JsonResponse
    {
        return $this->error(
            'Validation failed',
            422,
            $this->formatValidationErrors($errors)
        );
    }

    public function formatValidationErrors(iterable $errors): array
    {
        $validationErrors = [];

        foreach ($errors as $error) {
            $validationErrors[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage(),
            ];
        }

        return $validationErrors;
    }
}