<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    public function __construct(private EntityManagerInterface $entityManager, private RequestStack $requestStack) 
    {
    }

    /**
     * Crée un log d’audit.
     *
     * Le service ne fait volontairement pas de flush().
     * Le controller ou le service appelant garde le contrôle de la transaction.
     */
    public function log(string $action, string $message, ?User $createdBy = null, ?string $targetType = null, ?int $targetId = null): void 
    {
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();

        $auditLog->setAction($action);
        $auditLog->setMessage($message);
        $auditLog->setCreatedBy($createdBy);
        $auditLog->setTargetType($targetType);
        $auditLog->setTargetId($targetId);

        // Adresse IP de l’utilisateur
        $auditLog->setIpAddress(
            $request?->getClientIp()
        );

        // Navigateur / client HTTP
        $auditLog->setUserAgent(
            $request?->headers->get('User-Agent')
        );

        $this->entityManager->persist($auditLog);
    }

}