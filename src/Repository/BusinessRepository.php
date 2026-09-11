<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Business;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Business>
 */
class BusinessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Business::class);
    }

    /** @return list<Business> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.activeForDemo', 'DESC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * El negocio que atiende las llamadas entrantes ahora mismo.
     */
    public function findActiveForDemo(): ?Business
    {
        return $this->findOneBy(['activeForDemo' => true]);
    }

    /**
     * Marca uno como activo y desactiva el resto, para que nunca haya dos.
     */
    public function makeActiveForDemo(Business $business): void
    {
        $em = $this->getEntityManager();

        $em->createQuery('UPDATE '.Business::class.' b SET b.activeForDemo = false WHERE b.activeForDemo = true')
            ->execute();

        $business->setActiveForDemo(true);
        $em->flush();
    }
}
