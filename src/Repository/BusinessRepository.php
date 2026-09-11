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
     *
     * Se hace con una sola consulta sobre la base de datos, no marcando objetos en
     * memoria, para que el cambio se aplique aunque la entidad venga de otro sitio.
     */
    public function makeActiveForDemo(Business $business): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE '.Business::class.' b SET b.activeForDemo = CASE WHEN b.id = :id THEN true ELSE false END')
            ->setParameter('id', $business->getId())
            ->execute();

        // El objeto en memoria se queda al día para quien siga usándolo después.
        $business->setActiveForDemo(true);
    }
}
