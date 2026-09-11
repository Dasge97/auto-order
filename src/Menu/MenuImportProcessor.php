<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\MenuImport;
use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Coge una importación pendiente, la manda leer y guarda el resultado como borrador.
 *
 * Un fallo aquí deja la importación en error y no toca la carta publicada, que es lo
 * que permite reintentar sin quedarte sin menú a mitad de demostración.
 */
class MenuImportProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MenuExtractorInterface $extractor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(MenuImport $import): void
    {
        if ($import->isFinished()) {
            return;
        }

        $import->markProcessing();
        $this->em->flush();

        try {
            $items = $this->extractor->extract($import);
        } catch (MenuExtractionException $e) {
            $this->logger->warning('Fallo al leer la carta: {message}', ['message' => $e->getMessage()]);
            $import->markError($e->getMessage());
            $this->em->flush();

            return;
        } catch (\Throwable $e) {
            $this->logger->error('Fallo inesperado al leer la carta', ['exception' => $e]);
            $import->markError('Fallo inesperado al leer la carta: '.$e->getMessage());
            $this->em->flush();

            return;
        }

        if ([] === $items) {
            $import->markError('No se ha reconocido ningún producto. Prueba con otra imagen, con el PDF, o pega el texto de la carta.');
            $this->em->flush();

            return;
        }

        $version = new MenuVersion($import->getBusiness());
        $this->em->persist($version);

        $position = 0;

        foreach ($items as $extracted) {
            $item = new MenuItem($version, $extracted->name);
            $item->setCategory($extracted->category);
            $item->setDescription($extracted->description);
            $item->setOptions($extracted->options);
            $item->setPrice(null === $extracted->price ? null : number_format($extracted->price, 2, '.', ''));
            $item->setPriceText($extracted->priceText);
            $item->setSourceRef($extracted->sourceRef);
            $item->setNeedsReview($extracted->needsReview);
            $item->setPosition($position++);

            $this->em->persist($item);
        }

        $import->markReady($version);
        $this->em->flush();
    }
}
