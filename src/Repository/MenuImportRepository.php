<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Business;
use App\Entity\MenuImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuImport>
 */
class MenuImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuImport::class);
    }

    /** @return list<MenuImport> */
    public function findForBusiness(Business $business): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.business = :business')
            ->setParameter('business', $business)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<MenuImport> */
    public function findPending(int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', MenuImport::STATUS_PENDING)
            ->orderBy('i.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
