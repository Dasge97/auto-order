<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MenuImport;
use App\Menu\MenuImportProcessor;
use App\Repository\BusinessRepository;
use App\Storage\ImportFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Lee una carta desde la terminal y enseña lo que ha entendido el modelo.
 *
 * Sirve para probar la lectura sin abrir el panel ni tener el worker en marcha, y
 * para ver de un vistazo si una carta concreta se extrae bien antes de una demo.
 */
#[AsCommand(name: 'app:leer-carta', description: 'Lee una carta (PDF, imagen o texto) y muestra los productos que saca')]
class ReadMenuCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BusinessRepository $businesses,
        private readonly MenuImportProcessor $processor,
        private readonly ImportFileStorage $storage,
        private readonly string $uploadDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('negocio', InputArgument::REQUIRED, 'Id del negocio')
            ->addArgument('fichero', InputArgument::REQUIRED, 'Ruta de un PDF, una imagen o un fichero de texto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $business = $this->businesses->find((int) $input->getArgument('negocio'));

        if (null === $business) {
            $io->error('No existe ese negocio. Míralos con: php bin/console dbal:run-sql "SELECT id, name FROM business"');

            return Command::FAILURE;
        }

        $path = (string) $input->getArgument('fichero');

        if (!is_readable($path)) {
            $io->error('No se puede leer el fichero: '.$path);

            return Command::FAILURE;
        }

        $import = $this->buildImport($business->getId(), $business, $path);
        $this->em->persist($import);
        $this->em->flush();

        $io->text('Leyendo la carta...');
        $this->processor->process($import);

        if (MenuImport::STATUS_READY !== $import->getStatus()) {
            $io->error($import->getError() ?? 'La lectura ha fallado.');

            return Command::FAILURE;
        }

        $rows = [];

        foreach ($import->getMenuVersion()?->getItems() ?? [] as $item) {
            $rows[] = [
                $item->getName(),
                $item->getCategory() ?? '',
                $item->getPrice() ?? '',
                $item->getPriceText() ?? '',
                $item->getSourceRef() ?? '',
                $item->needsReview() ? 'sí' : '',
            ];
        }

        $io->table(['Producto', 'Categoría', 'Precio', 'Precio texto', 'Origen', 'Dudas'], $rows);
        $io->success(\sprintf('%d productos. Revísalos y publícalos en /negocios/cartas/%d', \count($rows), $import->getMenuVersion()?->getId()));

        return Command::SUCCESS;
    }

    private function buildImport(?int $businessId, \App\Entity\Business $business, string $path): MenuImport
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        if (\in_array($extension, ['txt', 'md'], true)) {
            $import = new MenuImport($business, MenuImport::SOURCE_TEXT);
            $import->setSourceText((string) file_get_contents($path));

            return $import;
        }

        // El fichero se copia a la carpeta de subidas para que el extractor lo
        // encuentre por el mismo camino que si lo hubieras subido por el panel.
        $relativeDir = 'negocio-'.$businessId;
        $name = bin2hex(random_bytes(8)).'.'.$extension;
        (new Filesystem())->copy($path, $this->storage->absolutePath($relativeDir.'/'.$name));

        $import = new MenuImport($business, 'pdf' === $extension ? MenuImport::SOURCE_PDF : MenuImport::SOURCE_IMAGES);
        $import->setSourceFiles([$relativeDir.'/'.$name]);

        return $import;
    }
}
