<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Call;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Call>
 */
class CallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Call::class);
    }

    public function findByProviderCallId(string $providerCallId): ?Call
    {
        return $this->findOneBy(['providerCallId' => $providerCallId]);
    }

    /** @return list<Call> */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
