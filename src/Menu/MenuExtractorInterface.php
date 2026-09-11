<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\MenuImport;

interface MenuExtractorInterface
{
    /**
     * Lee la carta de una importación y devuelve sus productos.
     *
     * @return list<ExtractedItem>
     *
     * @throws MenuExtractionException si no se puede leer o la respuesta no vale
     */
    public function extract(MenuImport $import): array;
}
