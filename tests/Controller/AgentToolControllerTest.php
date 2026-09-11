<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Business;
use App\Entity\Call;
use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use App\Repository\CallRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AgentToolControllerTest extends WebTestCase
{
    private const TOKEN = 'token-de-pruebas';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Sin esto el kernel se reinicia en cada peticion y las entidades del test
        // dejan de estar ligadas al mismo EntityManager que usa la aplicacion.
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->createBusiness();
    }

    public function testSinTokenNoSePuedeConsultarLaCarta(): void
    {
        $this->client->request('POST', '/api/agent/get_menu', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['call' => ['call_id' => 'c1']]));

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testConTokenDevuelveLaCartaPublicada(): void
    {
        $data = $this->callTool('get_menu', ['call' => ['call_id' => 'c1']]);

        self::assertSame('Kebab de prueba', $data['negocio']);
        self::assertCount(3, $data['productos']);
    }

    public function testLaCartaNoDaPrecioCuandoNoLoHay(): void
    {
        $data = $this->callTool('get_menu', ['call' => ['call_id' => 'c1']]);
        $ensalada = array_values(array_filter($data['productos'], static fn (array $p): bool => 'Ensalada' === $p['nombre']))[0];

        self::assertArrayNotHasKey('precio_eur', $ensalada);
        self::assertSame('desde 4 €', $ensalada['precio_texto']);
    }

    public function testGuardaElPedidoYDevuelveReferencia(): void
    {
        $data = $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => [
                'items' => [['nombre' => 'Durum de pollo', 'cantidad' => 2]],
                'confirmado' => true,
                'nombre_cliente' => 'Dani',
                'modalidad' => 'recoger',
            ],
        ]);

        self::assertTrue($data['guardado']);
        self::assertEqualsWithDelta(15.0, $data['total_eur'], 0.001);
        self::assertNotEmpty($data['referencia']);
    }

    public function testUnaSegundaLlamadaALaHerramientaDevuelveElMismoPedido(): void
    {
        $primero = $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => ['items' => [['nombre' => 'Durum de pollo', 'cantidad' => 1]], 'confirmado' => true],
        ]);

        $segundo = $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => ['items' => [['nombre' => 'Refresco', 'cantidad' => 9]], 'confirmado' => true],
        ]);

        self::assertSame($primero['referencia'], $segundo['referencia']);
        self::assertCount(1, self::getContainer()->get(OrderRepository::class)->findRecent());
    }

    public function testSinConfirmarNoGuardaNada(): void
    {
        $data = $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => ['items' => [['nombre' => 'Durum de pollo', 'cantidad' => 1]], 'confirmado' => false],
        ]);

        self::assertSame('CONFIRMATION_REQUIRED', $data['error']);
        self::assertCount(0, self::getContainer()->get(OrderRepository::class)->findRecent());
    }

    public function testUnProductoInventadoDevuelveSugerencias(): void
    {
        $data = $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => ['items' => [['nombre' => 'Durum', 'cantidad' => 1]], 'confirmado' => true],
        ]);

        self::assertSame('UNKNOWN_ITEM', $data['error']);
        self::assertContains('Durum de pollo', $data['sugerencias']);
    }

    public function testGetCurrentOrderRecuperaElPedidoDeLaLlamada(): void
    {
        $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => ['items' => [['nombre' => 'Durum de pollo', 'cantidad' => 1]], 'confirmado' => true],
        ]);

        $data = $this->callTool('get_current_order', ['call' => ['call_id' => 'c1']]);

        self::assertTrue($data['existe']);
        self::assertSame('Durum de pollo', $data['productos'][0]['nombre']);
    }

    public function testGetCurrentOrderDiceQueNoHayPedidoSiNoSeGuardo(): void
    {
        $data = $this->callTool('get_current_order', ['call' => ['call_id' => 'llamada_sin_pedido']]);

        self::assertFalse($data['existe']);
    }

    public function testLaLlamadaSeQuedaConLaCartaQueHabiaAlEmpezar(): void
    {
        $this->callTool('get_menu', ['call' => ['call_id' => 'c1']]);

        $this->publishSecondMenu();

        $data = $this->callTool('get_menu', ['call' => ['call_id' => 'c1']]);
        $nombres = array_column($data['productos'], 'nombre');

        self::assertContains('Durum de pollo', $nombres);
        self::assertNotContains('Carta nueva', $nombres);
    }

    public function testUnPedidoNoSePuedeHacerConProductosDeLaCartaNueva(): void
    {
        $this->callTool('get_menu', ['call' => ['call_id' => 'c1']]);
        $this->publishSecondMenu();

        $data = $this->callTool('create_order', [
            'call' => ['call_id' => 'c1'],
            'args' => ['items' => [['nombre' => 'Carta nueva', 'cantidad' => 1]], 'confirmado' => true],
        ]);

        self::assertSame('UNKNOWN_ITEM', $data['error']);
    }

    public function testSinNegocioActivoNoSeTomaElPedido(): void
    {
        foreach ($this->em->getRepository(Business::class)->findAll() as $business) {
            $business->setActiveForDemo(false);
        }
        $this->em->flush();

        $data = $this->callTool('get_menu', ['call' => ['call_id' => 'llamada_huerfana']]);

        self::assertSame('MENU_NOT_READY', $data['error']);
        self::assertNull(self::getContainer()->get(CallRepository::class)->findByProviderCallId('llamada_huerfana'));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function callTool(string $tool, array $payload): array
    {
        $this->client->request(
            'POST',
            '/api/agent/'.$tool,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createBusiness(): Business
    {
        $business = new Business('Kebab de prueba');
        $this->em->persist($business);

        $version = new MenuVersion($business);
        $this->em->persist($version);

        foreach ([['Durum de pollo', '7.50', null], ['Refresco', '1.80', null], ['Ensalada', null, 'desde 4 €']] as $index => [$name, $price, $priceText]) {
            $item = new MenuItem($version, $name);
            $item->setPrice($price);
            $item->setPriceText($priceText);
            $item->setPosition($index);
            $this->em->persist($item);
        }

        $version->publish();
        $business->setPublishedMenu($version);
        $business->setActiveForDemo(true);
        $this->em->flush();

        return $business;
    }

    private function publishSecondMenu(): void
    {
        $business = $this->em->getRepository(Business::class)->findOneBy(['name' => 'Kebab de prueba']);
        self::assertInstanceOf(Business::class, $business);

        $version = new MenuVersion($business);
        $this->em->persist($version);

        $item = new MenuItem($version, 'Carta nueva');
        $item->setPrice('5.00');
        $this->em->persist($item);

        $version->publish();
        $business->setPublishedMenu($version);
        $this->em->flush();
    }

    public function testElPedidoQuedaLigadoALaLlamadaCorrecta(): void
    {
        $this->callTool('create_order', [
            'call' => ['call_id' => 'c_uno'],
            'args' => ['items' => [['nombre' => 'Refresco', 'cantidad' => 1]], 'confirmado' => true],
        ]);

        $call = self::getContainer()->get(CallRepository::class)->findByProviderCallId('c_uno');
        self::assertInstanceOf(Call::class, $call);

        $order = self::getContainer()->get(OrderRepository::class)->findByCall($call);
        self::assertNotNull($order);
        self::assertSame('c_uno', $order->getCall()->getProviderCallId());
    }
}
