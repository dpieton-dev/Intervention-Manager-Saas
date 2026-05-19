<?php

namespace App\Controller\Api;

use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;

class TicketController extends AbstractController
{
    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository): JsonResponse
    {
        $tickets = $ticketRepository->findAll();

        $data = [];

        foreach ($tickets as $ticket) {
            $data[] = $this->formatTicket($ticket);
        }

        return $this->json($data);
    }

    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Récupération de l'utilisateur via le token JWT
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User)
        {
            return $this->json([
                'message' => 'User not authenticated',
            ], 401);
        }
    
        // Récuération des données JSON envoyées
        $data = json_decode($request->getContent(), true);
        
        // Création du ticket
        $ticket = new Ticket();

        // Remplissage des attributs de ticket
        $ticket->setTitle($data['title'] ?? '');
        $ticket->setDescription($data['description'] ?? '');
        $ticket->setPriority($data['priority'] ?? '');
        // Statut par défaut
        $ticket->setStatus('open');
        // Créateur automatique du Ticket
        $ticket->setCreatedBy($user);
        // AssignedTo reste null par défaut

        // Sauvegarde en BDD
        $entityManager->persist($ticket);
        $entityManager->flush();

        // Réponse JSON
        return $this->json([
            'message' => 'Ticket created successfully',
            'ticket' => $this->formatTicket($ticket),
        ], 201);
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket): JsonResponse
    {
        return $this->json($this->formatTicket($ticket));
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_update', methods: ['PUT'])]
    public function update(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Récupération du JSON envoyé
        $data = json_decode($request->getContent(), true);

        // Mise à jour uniquement si la donnée existe
        $ticket->setTitle($data['title'] ?? $ticket->getTitle());
        $ticket->setDescription($data['description'] ?? $ticket->getDescription());
        $ticket->setStatus($data['status'] ?? $ticket->getStatus());
        $ticket->setPriority($data['priority'] ?? $ticket->getPriority());

        // Date de modification
        $ticket->setUpdateAt(new \DateTimeImmutable());

        // Sauvegarde en base
        $entityManager->flush();

        return $this->json([
            'message' => 'Ticket updated successfully',
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_delete', methods: ['DELETE'])]
    public function delete(Ticket $ticket, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Suppression du ticket
        $entityManager->remove($ticket);

        // Exécution SQL
        $entityManager->flush();

        return $this->json([
            'message' => 'Ticket deleted successfully',
        ]);
    }

    #[Route('/api/tickets/{id}/assign', name: 'api_ticket_assign', methods: ['PATCH'])]
    public function assign(
        Ticket $ticket,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Récupère le JSON envoyé
        $data = json_decode($request->getContent(), true);

        // Vérifie que assignedTo est présent
        if (!isset($data['assignedTo'])) {
            return $this->json([
                'message' => 'assignedTo is required',
            ], 400);
        }

        // Recherche l'utilisateur à assigner
        $user = $userRepository->find($data['assignedTo']);

        if (!$user) {
            return $this->json([
                'message' => 'User not found',
            ], 404);
        }

        // Assigne le ticket à l'utilisateur trouvé
        $ticket->setAssignedTo($user);

        // Met à jour la date de modification
        $ticket->setUpdateAt(new \DateTimeImmutable());

        // Sauvegarde
        $entityManager->flush();

        return $this->json([
            'message' => 'Ticket assigned successfully',
            'ticket' => $this->formatTicket($ticket),
        ]);
    }


    private function formatTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus(),
            'priority' => $ticket->getPriority(),
            'createdAt' => $ticket->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $ticket->getUpdateAt()?->format('Y-m-d H:i:s'),

            'createdBy' => $ticket->getCreatedBy() ? [
                'id' => $ticket->getCreatedBy()->getId(),
                'email' => $ticket->getCreatedBy()->getEmail(),
            ] : null,

            'assignedTo' => $ticket->getAssignedTo() ? [
                'id' => $ticket->getAssignedTo()->getId(),
                'email' => $ticket->getAssignedTo()->getEmail(),
            ] : null,
        ];
    }
}
