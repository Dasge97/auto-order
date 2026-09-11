<?php

declare(strict_types=1);

namespace App\Tests\Order;

use App\Entity\Order;
use App\Order\OrderDraft;
use App\Order\OrderException;
use App\Order\OrderLine;
use App\Order\OrderService;
use App\Tests\DatabaseTestCase;

class OrderServiceTest extends DatabaseTestCase
{
    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = self::getContainer()->get(OrderService::class);
    }

    public function testNoGuardaSinConfirmacionDelCliente(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        $draft = new OrderDraft([new OrderLine(null, 'Durum de pollo', 1)], confirmed: false);

        try {
            $this->orderService->createOrder($call, $draft);
            self::fail('Tendría que haber fallado por falta de confirmación.');
        } catch (OrderException $e) {
            self::assertSame(OrderException::CONFIRMATION_REQUIRED, $e->errorCode);
        }
    }

    public function testGuardaElPedidoConfirmado(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        $order = $this->orderService->createOrder($call, new OrderDraft(
            [new OrderLine(null, 'Durum de pollo', 2), new OrderLine(null, 'Refresco', 1)],
            confirmed: true,
            customerName: 'Dani',
            fulfillment: Order::FULFILLMENT_PICKUP,
        ));

        self::assertCount(2, $order->getItems());
        self::assertSame('Dani', $order->getCustomerName());
        self::assertSame(Order::FULFILLMENT_PICKUP, $order->getFulfillment());
        self::assertNotSame('', $order->getReference());
    }

    public function testSumaElTotalCuandoTodosLosProductosTienenPrecio(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        $order = $this->orderService->createOrder($call, new OrderDraft(
            [new OrderLine(null, 'Durum de pollo', 2), new OrderLine(null, 'Refresco', 1)],
            confirmed: true,
        ));

        self::assertSame(16.80, $order->getTotalAsFloat());
    }

    public function testNoHayTotalSiFaltaElPrecioDeAlgunProducto(): void
    {
        $business = $this->createBusinessWithMenu(items: [['Durum de pollo', 7.50], ['Ensalada', null, 'desde 4 €']]);
        $call = $this->createCall($business);

        $order = $this->orderService->createOrder($call, new OrderDraft(
            [new OrderLine(null, 'Durum de pollo', 1), new OrderLine(null, 'Ensalada', 1)],
            confirmed: true,
        ));

        self::assertNull($order->getTotal());
    }

    public function testUnaLlamadaNoPuedeGenerarDosPedidos(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        $first = $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Durum de pollo', 1)], confirmed: true));
        $second = $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Refresco', 5)], confirmed: true));

        self::assertSame($first->getId(), $second->getId());
        self::assertSame($first->getReference(), $second->getReference());
        self::assertCount(1, $second->getItems());
    }

    public function testRechazaUnProductoQueNoEstaEnLaCarta(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        try {
            $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Pizza barbacoa', 1)], confirmed: true));
            self::fail('Tendría que haber rechazado un producto que no está en la carta.');
        } catch (OrderException $e) {
            self::assertSame(OrderException::UNKNOWN_ITEM, $e->errorCode);
        }
    }

    public function testSugiereProductosParecidosParaQueElAgentePregunte(): void
    {
        $business = $this->createBusinessWithMenu(items: [['Durum de pollo', 7.50], ['Durum mixto', 7.50]]);
        $call = $this->createCall($business);

        try {
            $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Durum', 1)], confirmed: true));
            self::fail('Un nombre incompleto no debería resolverse solo.');
        } catch (OrderException $e) {
            self::assertSame(OrderException::UNKNOWN_ITEM, $e->errorCode);
            self::assertContains('Durum de pollo', $e->suggestions);
            self::assertContains('Durum mixto', $e->suggestions);
        }
    }

    public function testRechazaUnaCantidadQueNoVale(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        try {
            $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Refresco', 0)], confirmed: true));
            self::fail('Una cantidad de cero no debería aceptarse.');
        } catch (OrderException $e) {
            self::assertSame(OrderException::INVALID_QUANTITY, $e->errorCode);
        }
    }

    public function testRechazaElPedidoSiElNegocioNoTieneCartaPublicada(): void
    {
        $business = $this->createBusinessWithMenu(publish: false);
        $call = $this->createCall($business);

        try {
            $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Durum de pollo', 1)], confirmed: true));
            self::fail('Sin carta publicada no se puede tomar el pedido.');
        } catch (OrderException $e) {
            self::assertSame(OrderException::MENU_NOT_READY, $e->errorCode);
        }
    }

    public function testNoAceptaProductosDeOtroNegocio(): void
    {
        $suyo = $this->createBusinessWithMenu('Kebab A', [['Durum de pollo', 7.50]]);
        $ajeno = $this->createBusinessWithMenu('Pizzeria B', [['Pizza margarita', 9.00]]);

        $call = $this->createCall($suyo, 'call_a');
        $pizzaAjena = $this->findMenuItem($ajeno, 'Pizza margarita');

        try {
            $this->orderService->createOrder($call, new OrderDraft(
                [new OrderLine($pizzaAjena->getId(), 'Pizza margarita', 1)],
                confirmed: true,
            ));
            self::fail('No debería poder pedirse un producto de otro negocio.');
        } catch (OrderException $e) {
            self::assertSame(OrderException::UNKNOWN_ITEM, $e->errorCode);
        }
    }

    public function testElPedidoConservaElNombreAunqueCambieLaCarta(): void
    {
        $business = $this->createBusinessWithMenu();
        $call = $this->createCall($business);

        $order = $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'Durum de pollo', 1)], confirmed: true));

        $item = $this->findMenuItem($business, 'Durum de pollo');
        $item->setName('Durum de pollo (nuevo nombre)');
        $this->em->flush();
        $this->em->refresh($order);

        self::assertSame('Durum de pollo', $order->getItems()->first()->getNameCopy());
    }

    public function testReconoceElProductoAunqueCambienMayusculasYAcentos(): void
    {
        $business = $this->createBusinessWithMenu(items: [['Menú del día', 9.90]]);
        $call = $this->createCall($business);

        $order = $this->orderService->createOrder($call, new OrderDraft([new OrderLine(null, 'MENU DEL DIA', 1)], confirmed: true));

        self::assertSame('Menú del día', $order->getItems()->first()->getNameCopy());
    }
}
