<?php

declare(strict_types=1);

namespace App\Retell;

use Symfony\Component\HttpFoundation\Request;

/**
 * Comprueba el token que traen las llamadas a las herramientas del agente.
 *
 * En Retell se configura como una cabecera fija de la función personalizada. Sin este
 * token cualquiera que conozca la dirección podría crear pedidos.
 */
class ToolAuthenticator
{
    public function __construct(private readonly string $toolToken)
    {
    }

    public function isValid(Request $request): bool
    {
        if ('' === $this->toolToken) {
            return false;
        }

        $header = (string) $request->headers->get('Authorization');
        $given = str_starts_with($header, 'Bearer ') ? substr($header, 7) : (string) $request->headers->get('X-Auto-Order-Token');

        return '' !== $given && hash_equals($this->toolToken, $given);
    }
}
