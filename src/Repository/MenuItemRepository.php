<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * Busca un producto dentro de una versión concreta de la carta.
     *
     * Es la comprobación que impide que el agente meta en el pedido un producto de
     * otro negocio o de otra versión.
     */
    public function findOneInVersion(int $id, MenuVersion $menuVersion): ?MenuItem
    {
        return $this->findOneBy(['id' => $id, 'menuVersion' => $menuVersion]);
    }

    /** @return list<MenuItem> */
    public function findByNameInVersion(string $name, MenuVersion $menuVersion): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.menuVersion = :version')
            ->andWhere('LOWER(i.name) = LOWER(:name)')
            ->setParameter('version', $menuVersion)
            ->setParameter('name', trim($name))
            ->getQuery()
            ->getResult();
    }
}
