<?php

namespace App\Security;

use App\Service\RateLimiterService;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class ApiRateLimitListener
{
    public function __construct(
        private RateLimiterService $rateLimiterService
    ) {
    }

    public function onKernelRequest(
        RequestEvent $event
    ): void {
        $request = $event->getRequest();

        $path = $request->getPathInfo();

        /*
        |--------------------------------------------------------------------------
        | EXCLUDED ROUTES
        |--------------------------------------------------------------------------
        */

        if (
            $path === '/api/login'
            || $path === '/api/forgot-password'
            || $path === '/api/reset-password'
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ONLY API ROUTES
        |--------------------------------------------------------------------------
        */

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $this->rateLimiterService->consumeApi(
            $request
        );
    }
}