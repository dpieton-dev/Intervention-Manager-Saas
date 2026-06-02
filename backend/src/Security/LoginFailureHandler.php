<?php

namespace App\Security;

use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginFailureHandler
{
    public function __construct(
        private AuditLogService $auditLogService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        $data = json_decode(
            $request->getContent(),
            true
        );

        $email = $data['email'] ?? 'unknown';

        $this->auditLogService->log(
            'LOGIN_FAILED',
            sprintf('Failed login attempt for "%s"', $email),
            null,
            'User',
            null
        );

        $this->entityManager->flush();
    }
}