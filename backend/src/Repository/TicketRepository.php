<?php

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findFilteredTickets(array $filters, int $page = 1, int $limit = 10): array 
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->leftJoin('t.assignedTo', 'a')
            ->leftJoin('t.createdBy', 'c')
            ->andWhere('t.deleteAt IS NULL');

        // FILTERS
        // Status
        if (!empty($filters['status'])) {
            $queryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Priority
        if (!empty($filters['priority'])) {
            $queryBuilder
                ->andWhere('t.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        // Project
        if (!empty($filters['project'])) {
            $queryBuilder
                ->andWhere('p.id = :project')
                ->setParameter('project', $filters['project']);
        }

        // Assigned To
        if (!empty($filters['assignedTo'])) {
            $queryBuilder
                ->andWhere('a.id = :assignedTo')
                ->setParameter('assignedTo', $filters['assignedTo']);
        }

        // Created By
        if (!empty($filters['createdBy'])) {
            $queryBuilder
                ->andWhere('c.id = :createdBy')
                ->setParameter('createdBy', $filters['createdBy']);
        }

        // Search
        if (!empty($filters['search'])) {
            $queryBuilder
                ->andWhere('t.title LIKE :search OR t.description LIKE :search')
                ->setParameter(
                    'search',
                    '%' . $filters['search'] . '%'
                );
        }

        // PAGINATION

        $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('t.createdAt', 'DESC');

        // RESULTS

        $tickets = $queryBuilder
            ->getQuery()
            ->getResult();

        // TOTAL

        $countQueryBuilder = clone $queryBuilder;

        $total = count(
            $countQueryBuilder
                ->getQuery()
                ->getResult()
        );

        return [
            'data' => $tickets,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    public function findDeletedTickets(int $page = 1, int $limit = 10): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->leftJoin('t.assignedTo', 'a')
            ->leftJoin('t.createdBy', 'c')
            ->andWhere('t.deleteAt IS NOT NULL')
            ->orderBy('t.deleteAt', 'DESC');

        $total = count(
            (clone $queryBuilder)
                ->getQuery()
                ->getResult()
        );

        $tickets = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $tickets,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    public function countDeletedTickets(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.deleteAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByProjectAndStatus(
        Project $project,
        string $status
    ): int {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.project = :project')
            ->andWhere('t.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function countByProjectAndPriority(
        Project $project,
        string $priority
    ): int {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.project = :project')
            ->andWhere('t.priority = :priority')
            ->setParameter('project', $project)
            ->setParameter('priority', $priority)
            ->getQuery()
            ->getSingleScalarResult();
    }

    
}
