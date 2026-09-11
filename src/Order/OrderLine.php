<?php

declare(strict_types=1);

namespace App\Order;

/**
 * Una línea del pedido tal y como la dice el agente.
 */
final class OrderLine
{
    public function __construct(
        public readonly ?int $menuItemId,
        public readonly ?string $name,
        public readonly int $quantity,
        public readonly ?string $modifications = null,
    ) {
    }
}
