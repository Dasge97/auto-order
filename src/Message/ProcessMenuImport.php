<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Encarga la lectura de una carta al worker, para que la pantalla no se quede
 * esperando a que el modelo termine.
 */
final class ProcessMenuImport
{
    public function __construct(public readonly int $menuImportId)
    {
    }
}
