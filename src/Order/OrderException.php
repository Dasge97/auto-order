<?php

declare(strict_types=1);

namespace App\Order;

/**
 * Fallo al guardar un pedido, con un código que el agente entiende y puede explicar
 * por teléfono sin inventarse nada.
 */
class OrderException extends \RuntimeException
{
    public const MENU_NOT_READY = 'MENU_NOT_READY';
    public const UNKNOWN_ITEM = 'UNKNOWN_ITEM';
    public const INVALID_QUANTITY = 'INVALID_QUANTITY';
    public const CONFIRMATION_REQUIRED = 'CONFIRMATION_REQUIRED';
    public const EMPTY_ORDER = 'EMPTY_ORDER';

    /**
     * @param list<string> $suggestions productos parecidos, para que el agente pregunte
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $suggestions = [],
    ) {
        parent::__construct($message);
    }
}
