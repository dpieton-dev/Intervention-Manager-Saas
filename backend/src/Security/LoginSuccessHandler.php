<?php

namespace App\Security;

use App\Entity\User;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class LoginSuccessHandler
{
    public function __construct(
        private AuditLogService $auditLogService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function onAuthenticationSuccess(
        AuthenticationSuccessEvent $event
    ): void {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->auditLogService->log(
            'LOGIN_SUCCESS',
            sprintf(
                'User "%s" logged in successfully',
                $user->getEmail()
            ),
            $user,
            'User',
            $user->getId()
        );

        $this->entityManager->flush();
    }
}