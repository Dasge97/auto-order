<?php

declare(strict_types=1);

namespace App\Order;

use App\Entity\Call;
use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Guarda el pedido de una llamada.
 *
 * Todo el servicio existe para cumplir dos cosas: que una llamada no genere dos
 * pedidos aunque el agente reintente, y que no entre en el pedido nada que no esté
 * en la carta de esa llamada.
 */
class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orders,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws OrderException
     */
    public function createOrder(Call $call, OrderDraft $draft): Order
    {
        if (!$draft->confirmed) {
            throw new OrderException(
                OrderException::CONFIRMATION_REQUIRED,
                'Repite el pedido al cliente y espera a que lo confirme antes de guardarlo.',
            );
        }

        // Si la llamada ya tiene pedido, se devuelve el mismo. Es lo que hace que un
        // reintento tras un timeout no cree un segundo pedido.
        $existing = $this->orders->findByCall($call);

        if (null !== $existing) {
            return $existing;
        }

        $menuVersion = $call->getMenuVersion();

        if (null === $menuVersion || 0 === $menuVersion->countItems()) {
            throw new OrderException(
                OrderException::MENU_NOT_READY,
                'Este negocio todavía no tiene carta publicada.',
            );
        }

        if ([] === $draft->items) {
            throw new OrderException(OrderException::EMPTY_ORDER, 'El pedido no tiene productos.');
        }

        $resolved = [];

        foreach ($draft->items as $line) {
            if ($line->quantity < 1 || $line->quantity > 99) {
                throw new OrderException(
                    OrderException::INVALID_QUANTITY,
                    \sprintf('La cantidad de "%s" no es válida.', $line->name ?? 'un producto'),
                );
            }

            $resolved[] = [$this->resolveMenuItem($line, $menuVersion->getItems()->toArray()), $line];
        }

        $order = new Order($call->getBusiness(), $call, $this->orders->generateReference());
        $order->setCustomerName($draft->customerName);
        $order->setFulfillment($draft->fulfillment);
        $order->setNotes($draft->notes);

        foreach ($resolved as [$menuItem, $line]) {
            $orderItem = new OrderItem($order, $menuItem->getName(), $line->quantity, $menuItem);
            $orderItem->setModifications($line->modifications);
            $this->em->persist($orderItem);
        }

        $order->recalculateTotal();
        $this->em->persist($order);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Dos peticiones a la vez para la misma llamada: gana la primera y la
            // segunda devuelve el pedido que ya existe.
            $this->em->clear();
            $call = $this->em->find(Call::class, $call->getId());
            $already = null === $call ? null : $this->orders->findByCall($call);

            if (null === $already) {
                throw new \RuntimeException('No se ha podido guardar el pedido.');
            }

            $this->logger->info('Pedido duplicado evitado en la llamada {call}', ['call' => $already->getCall()->getProviderCallId()]);

            return $already;
        }

        return $order;
    }

    /**
     * @param list<MenuItem> $menuItems
     *
     * @throws OrderException
     */
    private function resolveMenuItem(OrderLine $line, array $menuItems): MenuItem
    {
        if (null !== $line->menuItemId) {
            foreach ($menuItems as $menuItem) {
                if ($menuItem->getId() === $line->menuItemId) {
                    return $menuItem;
                }
            }
        }

        $wanted = $this->normalize((string) $line->name);

        if ('' !== $wanted) {
            $exact = [];

            foreach ($menuItems as $menuItem) {
                if ($this->normalize($menuItem->getName()) === $wanted) {
                    $exact[] = $menuItem;
                }
            }

            if (1 === \count($exact)) {
                return $exact[0];
            }

            if (\count($exact) > 1) {
                throw new OrderException(
                    OrderException::UNKNOWN_ITEM,
                    \sprintf('Hay varios productos que se llaman "%s". Pregunta cuál quiere.', $line->name),
                    array_map(static fn (MenuItem $i): string => $i->getName(), $exact),
                );
            }
        }

        throw new OrderException(
            OrderException::UNKNOWN_ITEM,
            \sprintf('"%s" no está en la carta. Ofrece los productos que sí están.', $line->name ?? 'El producto'),
            $this->suggestSimilar($wanted, $menuItems),
        );
    }

    /**
     * @param list<MenuItem> $menuItems
     *
     * @return list<string>
     */
    private function suggestSimilar(string $wanted, array $menuItems): array
    {
        if ('' === $wanted) {
            return [];
        }

        $scored = [];

        foreach ($menuItems as $menuItem) {
            $name = $this->normalize($menuItem->getName());

            if (str_contains($name, $wanted) || str_contains($wanted, $name)) {
                $scored[] = $menuItem->getName();
                continue;
            }

            similar_text($name, $wanted, $percent);

            if ($percent >= 70) {
                $scored[] = $menuItem->getName();
            }
        }

        return \array_slice($scored, 0, 5);
    }

    /**
     * Compara nombres sin acentos, sin mayúsculas y sin espacios de más, para que
     * "Durum de Pollo" y "durum de pollo" sean el mismo producto.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/\s+/u', ' ', $value);
    }
}
