<?php

declare(strict_types=1);

namespace App\Tests\Menu;

use App\Entity\Business;
use App\Entity\MenuImport;
use App\Menu\ExtractedItem;
use App\Menu\MenuExtractionException;
use App\Menu\MenuExtractorInterface;
use App\Menu\MenuImportProcessor;
use App\Tests\DatabaseTestCase;
use Psr\Log\NullLogger;

class MenuImportProcessorTest extends DatabaseTestCase
{
    public function testGuardaLosProductosLeidosComoBorrador(): void
    {
        $business = $this->createBusinessWithMenu(publish: false);
        $import = $this->createImport($business);

        $this->processWith($import, [
            new ExtractedItem(name: 'Durum de pollo', category: 'Durums', price: 7.50),
            new ExtractedItem(name: 'Ensalada', priceText: 'desde 4 €'),
        ]);

        self::assertSame(MenuImport::STATUS_READY, $import->getStatus());
        self::assertSame(2, $import->getMenuVersion()?->countItems());
        self::assertFalse($import->getMenuVersion()?->isPublished());
    }

    public function testUnProductoSinPrecioSeGuardaConPrecioVacioNoConCero(): void
    {
        $business = $this->createBusinessWithMenu(publish: false);
        $import = $this->createImport($business);

        $this->processWith($import, [new ExtractedItem(name: 'Baklava')]);

        $item = $import->getMenuVersion()?->getItems()->first();

        self::assertNotFalse($item);
        self::assertNull($item->getPrice());
    }

    public function testUnaLecturaSinProductosQuedaEnErrorYNoCreaCarta(): void
    {
        $business = $this->createBusinessWithMenu(publish: false);
        $import = $this->createImport($business);

        $this->processWith($import, []);

        self::assertSame(MenuImport::STATUS_ERROR, $import->getStatus());
        self::assertNull($import->getMenuVersion());
        self::assertStringContainsString('No se ha reconocido ningún producto', (string) $import->getError());
    }

    public function testUnFalloDeLecturaNoTocaLaCartaPublicada(): void
    {
        $business = $this->createBusinessWithMenu();
        $publicadaAntes = $business->getPublishedMenu();
        $import = $this->createImport($business);

        $processor = new MenuImportProcessor($this->em, $this->failingExtractor('OpenAI no responde'), new NullLogger());
        $processor->process($import);

        self::assertSame(MenuImport::STATUS_ERROR, $import->getStatus());
        self::assertSame('OpenAI no responde', $import->getError());
        self::assertSame($publicadaAntes, $business->getPublishedMenu());
        self::assertSame(2, $business->getPublishedMenu()?->countItems());
    }

    public function testUnaImportacionYaTerminadaNoSeVuelveAProcesar(): void
    {
        $business = $this->createBusinessWithMenu(publish: false);
        $import = $this->createImport($business);
        $import->markError('fallo anterior');
        $this->em->flush();

        $this->processWith($import, [new ExtractedItem(name: 'Producto nuevo')]);

        self::assertSame(MenuImport::STATUS_ERROR, $import->getStatus());
        self::assertNull($import->getMenuVersion());
    }

    private function createImport(Business $business): MenuImport
    {
        $import = new MenuImport($business, MenuImport::SOURCE_TEXT);
        $import->setSourceText('Durum de pollo 7,50');
        $this->em->persist($import);
        $this->em->flush();

        return $import;
    }

    /**
     * @param list<ExtractedItem> $items
     */
    private function processWith(MenuImport $import, array $items): void
    {
        $extractor = new class($items) implements MenuExtractorInterface {
            /** @param list<ExtractedItem> $items */
            public function __construct(private readonly array $items)
            {
            }

            public function extract(MenuImport $import): array
            {
                return $this->items;
            }
        };

        (new MenuImportProcessor($this->em, $extractor, new NullLogger()))->process($import);
    }

    private function failingExtractor(string $message): MenuExtractorInterface
    {
        return new class($message) implements MenuExtractorInterface {
            public function __construct(private readonly string $message)
            {
            }

            public function extract(MenuImport $import): array
            {
                throw new MenuExtractionException($this->message);
            }
        };
    }
}
