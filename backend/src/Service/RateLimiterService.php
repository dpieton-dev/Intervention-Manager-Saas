<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimiterService
{
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $forgotPasswordLimiter,
        private RateLimiterFactory $resetPasswordLimiter,
        private RateLimiterFactory $uploadLimiter,
        private RateLimiterFactory $apiLimiter
    ) {
    }

    public function consumeLogin(Request $request): void
    {
        $this->consume($this->loginLimiter, $request, 'Too many login attempts. Please try again later.');
    }

    public function consumeForgotPassword(Request $request): void
    {
        $this->consume($this->forgotPasswordLimiter, $request, 'Too many password reset requests. Please try again later.');
    }

    public function consumeResetPassword(Request $request): void
    {
        $this->consume($this->resetPasswordLimiter, $request, 'Too many reset password attempts. Please try again later.');
    }

    public function consumeUpload(Request $request): void
    {
        $this->consume(
            $this->uploadLimiter,
            $request,
            'Too many upload attempts. Please try again later.'
        );
    }

    public function consumeApi(Request $request): void
    {
        $this->consume(
            $this->apiLimiter,
            $request,
            'Too many API requests. Please try again later.'
        );
    }

    private function consume(
        RateLimiterFactory $limiterFactory,
        Request $request,
        string $message
    ): void {
        $key = sprintf(
            '%s_%s',
            $request->getClientIp() ?? 'anonymous',
            $request->getPathInfo()
        );

        $limiter = $limiterFactory->create($key);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                null,
                $message
            );
        }
    }

    
}