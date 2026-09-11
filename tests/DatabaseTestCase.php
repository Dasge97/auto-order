<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Business;
use App\Entity\Call;
use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base de las pruebas que tocan la base de datos.
 *
 * Rehace el esquema entero antes de cada prueba, para que una no dependa de lo que
 * haya dejado otra.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    /**
     * @param list<array{0: string, 1: float|null, 2?: string|null}> $items nombre, precio, precio en texto
     */
    protected function createBusinessWithMenu(string $name = 'Kebab de prueba', array $items = [['Durum de pollo', 7.50], ['Refresco', 1.80]], bool $publish = true): Business
    {
        $business = new Business($name);
        $this->em->persist($business);

        $version = new MenuVersion($business);
        $this->em->persist($version);

        foreach ($items as $index => $definition) {
            $item = new MenuItem($version, $definition[0]);
            $item->setPrice(null === $definition[1] ? null : number_format($definition[1], 2, '.', ''));
            $item->setPriceText($definition[2] ?? null);
            $item->setPosition($index);
            $this->em->persist($item);
        }

        if ($publish) {
            $version->publish();
            $business->setPublishedMenu($version);
        }

        $business->setActiveForDemo(true);
        $this->em->flush();

        return $business;
    }

    protected function createCall(Business $business, string $providerCallId = 'call_prueba'): Call
    {
        $call = new Call($providerCallId, $business, $business->getPublishedMenu());
        $this->em->persist($call);
        $this->em->flush();

        return $call;
    }

    protected function findMenuItem(Business $business, string $name): MenuItem
    {
        foreach ($business->getPublishedMenu()?->getItems() ?? [] as $item) {
            if ($item->getName() === $name) {
                return $item;
            }
        }

        self::fail(\sprintf('No existe el producto "%s" en la carta.', $name));
    }
}
