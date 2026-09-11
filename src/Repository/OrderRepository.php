<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Call;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByCall(Call $call): ?Order
    {
        return $this->findOneBy(['call' => $call]);
    }

    /** @return list<Order> */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Los pedidos creados después del que el panel ya tiene en pantalla.
     *
     * @return list<Order>
     */
    public function findNewerThan(?int $lastId): array
    {
        $qb = $this->createQueryBuilder('o')->orderBy('o.id', 'DESC')->setMaxResults(50);

        if (null !== $lastId) {
            $qb->where('o.id > :lastId')->setParameter('lastId', $lastId);
        }

        return $qb->getQuery()->getResult();
    }

    public function generateReference(): string
    {
        return strtoupper(bin2hex(random_bytes(3)));
    }
}
