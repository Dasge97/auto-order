<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Order\OrderDraft;
use App\Order\OrderLine;
use App\Order\OrderService;
use App\Repository\OrderRepository;
use App\Tests\DatabaseTestCase;

/**
 * El panel pregunta cada dos segundos si hay pedidos nuevos, usando el id del primero
 * de la lista. Si esos dos números no cuadran, la página se recarga sola sin parar
 * delante del cliente.
 */
class PanelPollingTest extends DatabaseTestCase
{
    public function testElPrimeroDeLaListaEsElDeIdMasAltoAunqueCompartanSegundo(): void
    {
        $business = $this->createBusinessWithMenu();
        $orderService = self::getContainer()->get(OrderService::class);

        for ($i = 1; $i <= 3; ++$i) {
            $call = $this->createCall($business, 'call_'.$i);
            $orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Refresco', 1)], confirmed: true));
        }

        $orders = self::getContainer()->get(OrderRepository::class)->findRecent();
        $ids = array_map(static fn (Order $o): int => (int) $o->getId(), $orders);

        self::assertSame(max($ids), $ids[0]);
    }

    public function testNoDevuelvePedidosQueElPanelYaTieneEnPantalla(): void
    {
        $business = $this->createBusinessWithMenu();
        $orderService = self::getContainer()->get(OrderService::class);
        $repository = self::getContainer()->get(OrderRepository::class);

        $call = $this->createCall($business, 'call_1');
        $primero = $orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Refresco', 1)], confirmed: true));

        self::assertCount(0, $repository->findNewerThan((int) $primero->getId()));

        $otraLlamada = $this->createCall($business, 'call_2');
        $segundo = $orderService->createOrder($otraLlamada, new OrderDraft([new OrderLine(null, 'Refresco', 2)], confirmed: true));

        $nuevos = $repository->findNewerThan((int) $primero->getId());

        self::assertCount(1, $nuevos);
        self::assertSame($segundo->getId(), $nuevos[0]->getId());
    }
}
