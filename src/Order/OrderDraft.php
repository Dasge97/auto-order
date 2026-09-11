<?php

declare(strict_types=1);

namespace App\Order;

use App\Entity\Order;

/**
 * El pedido tal y como llega del agente, antes de comprobarlo y guardarlo.
 */
final class OrderDraft
{
    /**
     * @param list<OrderLine> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly bool $confirmed,
        public readonly ?string $customerName = null,
        public readonly ?string $fulfillment = null,
        public readonly ?string $notes = null,
    ) {
    }

    /**
     * Construye el borrador a partir del JSON que manda la herramienta del agente.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $lines = [];

        foreach ($payload['items'] ?? $payload['productos'] ?? [] as $raw) {
            if (!\is_array($raw)) {
                continue;
            }

            $lines[] = new OrderLine(
                menuItemId: isset($raw['item_id']) && is_numeric($raw['item_id']) ? (int) $raw['item_id'] : null,
                name: self::cleanString($raw['nombre'] ?? $raw['name'] ?? null, 200),
                quantity: (int) ($raw['cantidad'] ?? $raw['quantity'] ?? 1),
                modifications: self::cleanString($raw['modificaciones'] ?? $raw['modifications'] ?? null, 500),
            );
        }

        $fulfillment = self::cleanString($payload['modalidad'] ?? $payload['fulfillment'] ?? null, 20);

        if (null !== $fulfillment) {
            $fulfillment = match (mb_strtolower($fulfillment)) {
                'recoger', 'recogida', 'pickup', 'local' => Order::FULFILLMENT_PICKUP,
                'domicilio', 'reparto', 'delivery', 'a domicilio' => Order::FULFILLMENT_DELIVERY,
                default => null,
            };
        }

        return new self(
            items: $lines,
            confirmed: filter_var($payload['confirmado'] ?? $payload['confirmed'] ?? false, \FILTER_VALIDATE_BOOL),
            customerName: self::cleanString($payload['nombre_cliente'] ?? $payload['customer_name'] ?? null, 120),
            fulfillment: $fulfillment,
            notes: self::cleanString($payload['observaciones'] ?? $payload['notes'] ?? null, 2000),
        );
    }

    private static function cleanString(mixed $value, int $maxLength): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : mb_substr($value, 0, $maxLength);
    }
}
