<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Business;
use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use App\Repository\BusinessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crea un negocio con una carta ya publicada, para poder probar el panel, el ticket y
 * las herramientas del agente sin gastar una lectura de OpenAI.
 *
 * No es la carta de ninguna demostración real: para eso se carga la del cliente.
 */
#[AsCommand(name: 'app:cargar-ejemplo', description: 'Crea un negocio de prueba con carta publicada')]
class LoadSampleBusinessCommand extends Command
{
    /** @var list<array{0: string, 1: string, 2: float|null, 3: string|null}> */
    private const SAMPLE_ITEMS = [
        ['Durum de pollo', 'Durums', 7.50, null],
        ['Durum mixto', 'Durums', 7.50, null],
        ['Kebab de ternera', 'Kebabs', 6.50, null],
        ['Kebab mixto', 'Kebabs', 6.50, null],
        ['Plato combinado de kebab', 'Platos', 9.90, null],
        ['Falafel', 'Vegetariano', 6.00, null],
        ['Ensalada de la casa', 'Vegetariano', null, 'desde 4 €'],
        ['Patatas fritas', 'Complementos', 2.50, null],
        ['Patatas con queso', 'Complementos', 3.50, null],
        ['Refresco', 'Bebidas', 1.80, null],
        ['Agua', 'Bebidas', 1.20, null],
        ['Baklava', 'Postres', null, null],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BusinessRepository $businesses,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $business = new Business('Kebab de prueba');
        $this->em->persist($business);

        $version = new MenuVersion($business);
        $this->em->persist($version);

        $position = 0;

        foreach (self::SAMPLE_ITEMS as [$name, $category, $price, $priceText]) {
            $item = new MenuItem($version, $name);
            $item->setCategory($category);
            $item->setPrice(null === $price ? null : number_format($price, 2, '.', ''));
            $item->setPriceText($priceText);
            $item->setSourceRef('ejemplo');
            $item->setPosition($position++);
            $this->em->persist($item);
        }

        $version->publish();
        $business->setPublishedMenu($version);
        $this->em->flush();

        $this->businesses->makeActiveForDemo($business);

        $io->success(\sprintf('Creado "%s" con %d productos y marcado como activo.', $business->getName(), \count(self::SAMPLE_ITEMS)));

        return Command::SUCCESS;
    }
}
