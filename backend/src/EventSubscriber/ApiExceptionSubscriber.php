<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(
        ExceptionEvent $event
    ): void {
        $exception = $event->getThrowable();

        // Vérifie si c'est une exception API personnalisée
        if (!$exception instanceof ApiException) {
            return;
        }

        $response = new JsonResponse([
            'success' => false,
            'code' => $exception->getStatusCode(),
            'message' => $exception->getMessage(),
            'errors' => $exception->getErrors(),
        ], $exception->getStatusCode());

        $event->setResponse($response);
    }
}