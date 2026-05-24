<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findFilteredProjectsForUser(User $user, array $filters, int $page = 1, int $limit = 10): array 
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->join('p.members', 'm')
            ->join('m.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $user->getId());

        if (!empty($filters['status'])) {
            $queryBuilder
                ->andWhere('p.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder
                ->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $total = count($queryBuilder->getQuery()->getResult());

        $projects = $queryBuilder
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $projects,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }
}