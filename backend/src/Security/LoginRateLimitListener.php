<?php

namespace App\Security;

use App\Service\RateLimiterService;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class LoginRateLimitListener
{
    public function __construct(
        private RateLimiterService $rateLimiterService
    ) {
    }

    public function onKernelRequest(
        RequestEvent $event
    ): void {
        $request = $event->getRequest();

        if (
            $request->getPathInfo() !== '/api/login'
            || $request->getMethod() !== 'POST'
        ) {
            return;
        }

        $this->rateLimiterService->consumeLogin(
            $request
        );
    }
}