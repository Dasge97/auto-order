<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Business;
use App\Entity\MenuImport;
use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use App\Repository\BusinessRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PanelTest extends WebTestCase
{
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
    }

    public function testSinIniciarSesionElPanelRedirigeAlLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testConUsuarioYContrasenaCorrectosSeEntra(): void
    {
        $this->login();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pedidos');
    }

    public function testSeCreaUnNegocioYPasaAAtenderLasLlamadas(): void
    {
        $this->login();

        $this->client->request('GET', '/negocios');
        $this->client->submitForm('Crear negocio', ['name' => 'Pizzeria de prueba']);

        $business = self::getContainer()->get(BusinessRepository::class)->findOneBy(['name' => 'Pizzeria de prueba']);

        self::assertInstanceOf(Business::class, $business);
        self::assertTrue($business->isActiveForDemo());
    }

    public function testSoloUnNegocioAtiendeLasLlamadasALaVez(): void
    {
        $this->login();
        $primero = $this->createBusiness('Kebab');
        $segundo = $this->createBusiness('Pizzeria');

        $repository = self::getContainer()->get(BusinessRepository::class);
        $repository->makeActiveForDemo($primero);
        $repository->makeActiveForDemo($segundo);

        $primero = $this->em->find(Business::class, $primero->getId());
        $this->em->refresh($primero);

        self::assertFalse($primero->isActiveForDemo());
        self::assertTrue($segundo->isActiveForDemo());
        self::assertSame($segundo->getId(), $repository->findActiveForDemo()?->getId());
    }

    public function testSubirUnArchivoQueNoEsCartaSeRechaza(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');

        $path = tempnam(sys_get_temp_dir(), 'carta').'.exe';
        file_put_contents($path, 'MZ contenido cualquiera');

        $this->client->request('POST', '/negocios/'.$business->getId().'/cartas', [
            '_token' => $this->csrfTokenFrom('/negocios/'.$business->getId(), 'form[enctype]'),
        ], [
            'files' => [new UploadedFile($path, 'carta.exe', 'application/octet-stream', null, true)],
        ]);

        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash-error', 'no es un PDF ni una imagen');
        self::assertCount(0, $this->em->getRepository(MenuImport::class)->findAll());

        @unlink($path);
    }

    public function testPublicarUnaCartaLaDejaComoLaQueUsanLasLlamadas(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');
        $version = $this->createDraft($business, ['Durum de pollo']);

        $item = $version->getItems()->first();

        $this->client->request('POST', '/negocios/cartas/'.$version->getId().'/guardar', [
            '_token' => $this->csrfTokenFrom('/negocios/cartas/'.$version->getId()),
            'publish' => '1',
            'items' => [
                (string) $item->getId() => ['name' => 'Durum de pollo', 'category' => 'Durums', 'description' => '', 'options' => '', 'price' => '7,50', 'price_text' => ''],
            ],
        ]);

        // Tras la peticion HTTP las entidades del test quedan desligadas, asi que se
        // vuelven a leer de la base de datos.
        $business = $this->em->find(Business::class, $business->getId());
        $item = $this->em->find(MenuItem::class, $item->getId());

        self::assertSame($version->getId(), $business->getPublishedMenu()?->getId());
        self::assertSame('7.50', $item->getPrice());
    }

    public function testUnPrecioQueNoEsNumeroSeQuedaVacioYNoEnCero(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');
        $version = $this->createDraft($business, ['Ensalada']);
        $item = $version->getItems()->first();

        $this->client->request('POST', '/negocios/cartas/'.$version->getId().'/guardar', [
            '_token' => $this->csrfTokenFrom('/negocios/cartas/'.$version->getId()),
            'items' => [
                (string) $item->getId() => ['name' => 'Ensalada', 'category' => '', 'description' => '', 'options' => '', 'price' => 'consultar', 'price_text' => 'desde 4 €'],
            ],
        ]);

        $item = $this->em->find(MenuItem::class, $item->getId());

        self::assertNull($item->getPrice());
        self::assertSame('desde 4 €', $item->getPriceText());
    }

    public function testNoSePublicaUnaCartaSinProductos(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');
        $version = $this->createDraft($business, ['Durum de pollo']);
        $item = $version->getItems()->first();

        // Dejar el nombre vacío borra el producto, así que la carta se queda sin nada.
        $this->client->request('POST', '/negocios/cartas/'.$version->getId().'/guardar', [
            '_token' => $this->csrfTokenFrom('/negocios/cartas/'.$version->getId()),
            'publish' => '1',
            'items' => [(string) $item->getId() => ['name' => '', 'category' => '', 'description' => '', 'options' => '', 'price' => '', 'price_text' => '']],
        ]);

        $this->client->followRedirect();
        $business = $this->em->find(Business::class, $business->getId());

        self::assertNull($business->getPublishedMenu());
        self::assertSelectorTextContains('.flash-error', 'sin productos');
    }

    public function testElSimuladorCreaUnPedidoMarcadoComoSimulado(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');
        $version = $this->createDraft($business, ['Durum de pollo']);
        $version->publish();
        $business->setPublishedMenu($version);
        $this->em->flush();
        self::getContainer()->get(BusinessRepository::class)->makeActiveForDemo($business);

        $item = $version->getItems()->first();

        $this->client->request('POST', '/simular', [
            '_token' => $this->csrfTokenFrom('/simular'),
            'items' => [['item_id' => (string) $item->getId(), 'quantity' => '2', 'modifications' => 'sin cebolla']],
            'customer_name' => 'Marta',
            'fulfillment' => 'domicilio',
        ]);

        $orders = self::getContainer()->get(OrderRepository::class)->findRecent();

        self::assertCount(1, $orders);
        self::assertTrue($orders[0]->getCall()->isSimulated());
        self::assertSame('Marta', $orders[0]->getCustomerName());
        self::assertSame('sin cebolla', $orders[0]->getItems()->first()->getModifications());
    }

    public function testElTicketSeAbreYMarcaQueEsUnaDemostracion(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');
        $version = $this->createDraft($business, ['Durum de pollo']);
        $version->publish();
        $business->setPublishedMenu($version);
        $this->em->flush();
        self::getContainer()->get(BusinessRepository::class)->makeActiveForDemo($business);

        $this->client->request('POST', '/simular', [
            '_token' => $this->csrfTokenFrom('/simular'),
            'items' => [['item_id' => (string) $version->getItems()->first()->getId(), 'quantity' => '1']],
        ]);

        $order = self::getContainer()->get(OrderRepository::class)->findRecent()[0];
        $this->client->request('GET', '/pedidos/'.$order->getId().'/ticket');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.demo-mark', 'DEMOSTRACION');
    }

    public function testElPanelAvisaDeLosPedidosNuevos(): void
    {
        $this->login();
        $business = $this->createBusiness('Kebab');
        $version = $this->createDraft($business, ['Durum de pollo']);
        $version->publish();
        $business->setPublishedMenu($version);
        $this->em->flush();
        self::getContainer()->get(BusinessRepository::class)->makeActiveForDemo($business);

        $this->client->request('GET', '/pedidos/nuevos');
        $vacio = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('POST', '/simular', [
            '_token' => $this->csrfTokenFrom('/simular'),
            'items' => [['item_id' => (string) $version->getItems()->first()->getId(), 'quantity' => '1']],
        ]);

        $this->client->request('GET', '/pedidos/nuevos');
        $conPedido = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertCount(0, $vacio['pedidos']);
        self::assertCount(1, $conPedido['pedidos']);
        self::assertTrue($conPedido['pedidos'][0]['simulado']);
    }

    private function login(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Entrar', ['_username' => 'admin', '_password' => 'admin']);
        $this->client->followRedirect();
    }

    /**
     * Saca el token de la página real, que es como lo envía el navegador. Generarlo
     * desde el contenedor no vale: el token vive en la sesión del cliente.
     */
    private function csrfTokenFrom(string $url, string $formSelector = 'form'): string
    {
        $crawler = $this->client->request('GET', $url);

        return (string) $crawler->filter($formSelector.' input[name="_token"]')->first()->attr('value');
    }

    private function createBusiness(string $name): Business
    {
        $business = new Business($name);
        $this->em->persist($business);
        $this->em->flush();

        return $business;
    }

    /**
     * @param list<string> $names
     */
    private function createDraft(Business $business, array $names): MenuVersion
    {
        $version = new MenuVersion($business);
        $this->em->persist($version);

        foreach ($names as $index => $name) {
            $item = new MenuItem($version, $name);
            $item->setPrice('7.50');
            $item->setPosition($index);
            $this->em->persist($item);
        }

        $this->em->flush();

        return $version;
    }
}
