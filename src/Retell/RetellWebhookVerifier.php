<?php

declare(strict_types=1);

namespace App\Retell;

use Symfony\Component\HttpFoundation\Request;

/**
 * Comprueba que un webhook viene de verdad de Retell.
 *
 * Retell manda la cabecera X-Retell-Signature con el formato "v={timestamp},d={hash}".
 * El hash es HMAC-SHA256 del cuerpo crudo seguido del timestamp, usando la clave de la
 * cuenta. Hay que usar el cuerpo tal cual llega, no uno reconstruido desde el JSON.
 */
class RetellWebhookVerifier
{
    private const HEADER = 'X-Retell-Signature';
    private const MAX_AGE_SECONDS = 300;

    public function __construct(private readonly string $webhookSecret)
    {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->webhookSecret;
    }

    public function isValid(Request $request): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $header = $request->headers->get(self::HEADER);

        if (null === $header || 1 !== preg_match('/^v=(\d+),d=(.+)$/', trim($header), $matches)) {
            return false;
        }

        [, $timestamp, $digest] = $matches;

        if (abs(time() - (int) ((int) $timestamp / 1000)) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent().$timestamp, $this->webhookSecret);

        return hash_equals($expected, $digest);
    }
}
