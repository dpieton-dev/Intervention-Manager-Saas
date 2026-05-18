<?php

namespace App\Controller\Api;

use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class TicketController extends AbstractController
{
    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository): JsonResponse
    {
        $tickets = $ticketRepository->findAll();

        $data = [];

        foreach ($tickets as $ticket) {
            $data[] = [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'description' => $ticket->getDescription(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
                'createdAt' => $ticket->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
        // Récuération des données JSON envoyées
        $data = json_decode($request->getContent(), true);
        
        // Création du ticket
        $ticket = new Ticket();

        // Remplissage des attributs de ticket
        $ticket->setTitle($data['title'] ?? '');
        $ticket->setDescription($data['description'] ?? '');
        $ticket->setPriority($data['priority'] ?? '');

        // Sauvegarde en BDD
        $entityManager->persist($ticket);
        $entityManager->flush();

        // Réponse JSON
        return $this->json([
            'message' => 'Ticket created successfully',
            'ticket' => [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'status' => $ticket->getStatus(),
            ]
        ], 201);
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket): JsonResponse
    {
        return $this->json([
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus(),
            'priority' => $ticket->getPriority(),
            'createdAt' => $ticket->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $ticket->getUpdateAt()->format('Y-m-d H:i:s'), 
        ]);
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
            'ticket' => [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
            ],
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
}
