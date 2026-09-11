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

class RetellWebhookControllerTest extends WebTestCase
{
    private const SECRET = 'secreto-de-pruebas';

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

        $business = new Business('Kebab de prueba');
        $this->em->persist($business);

        $version = new MenuVersion($business);
        $this->em->persist($version);

        $item = new MenuItem($version, 'Durum de pollo');
        $item->setPrice('7.50');
        $this->em->persist($item);

        $version->publish();
        $business->setPublishedMenu($version);
        $business->setActiveForDemo(true);
        $this->em->flush();
    }

    public function testRechazaUnEventoSinFirma(): void
    {
        $this->client->request('POST', '/webhook/retell', server: ['CONTENT_TYPE' => 'application/json'], content: '{"event":"call_started","call":{"call_id":"c1"}}');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testRechazaUnaFirmaQueNoCuadra(): void
    {
        $body = json_encode(['event' => 'call_started', 'call' => ['call_id' => 'c1']]);
        $timestamp = (string) (time() * 1000);

        $this->client->request('POST', '/webhook/retell', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RETELL_SIGNATURE' => 'v='.$timestamp.',d='.hash_hmac('sha256', $body.$timestamp, 'otro-secreto'),
        ], content: $body);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testRechazaUnaFirmaVieja(): void
    {
        $body = json_encode(['event' => 'call_started', 'call' => ['call_id' => 'c1']]);
        $timestamp = (string) ((time() - 3600) * 1000);

        $this->client->request('POST', '/webhook/retell', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RETELL_SIGNATURE' => 'v='.$timestamp.',d='.hash_hmac('sha256', $body.$timestamp, self::SECRET),
        ], content: $body);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testGuardaLosDatosDeLaLlamada(): void
    {
        $this->sendEvent('call_ended', [
            'call_id' => 'c1',
            'from_number' => '+34600111222',
            'to_number' => '+34931234567',
            'duration_ms' => 65000,
        ]);

        $call = self::getContainer()->get(CallRepository::class)->findByProviderCallId('c1');

        self::assertInstanceOf(Call::class, $call);
        self::assertSame('+34600111222', $call->getFromNumber());
        self::assertSame(65, $call->getDurationSeconds());
        self::assertSame(Call::STATUS_ENDED, $call->getStatus());
    }

    public function testUnEventoRepetidoNoSeProcesaDosVeces(): void
    {
        $body = json_encode(['event' => 'call_started', 'call' => ['call_id' => 'c1']], \JSON_THROW_ON_ERROR);
        $timestamp = (string) (time() * 1000);
        $signature = 'v='.$timestamp.',d='.hash_hmac('sha256', $body.$timestamp, self::SECRET);

        $this->client->request('POST', '/webhook/retell', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RETELL_SIGNATURE' => $signature], content: $body);
        $primera = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('POST', '/webhook/retell', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RETELL_SIGNATURE' => $signature], content: $body);
        $segunda = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertArrayNotHasKey('repetido', $primera);
        self::assertTrue($segunda['repetido']);
    }

    public function testUnEventoDesordenadoNoDevuelveLaLlamadaAlEstadoAnterior(): void
    {
        $this->sendEvent('call_analyzed', ['call_id' => 'c1']);
        $this->sendEvent('call_started', ['call_id' => 'c1']);

        $call = self::getContainer()->get(CallRepository::class)->findByProviderCallId('c1');

        self::assertInstanceOf(Call::class, $call);
        self::assertSame(Call::STATUS_ANALYZED, $call->getStatus());
    }

    public function testElAnalisisDeRetellNuncaCreaUnPedido(): void
    {
        $this->sendEvent('call_analyzed', [
            'call_id' => 'c1',
            'call_analysis' => [
                'custom_analysis_data' => ['pedido' => ['Durum de pollo x2']],
                'call_summary' => 'El cliente pidió dos durums de pollo.',
            ],
        ]);

        self::assertCount(0, self::getContainer()->get(OrderRepository::class)->findRecent());
    }

    public function testGuardaLaTranscripcion(): void
    {
        $this->sendEvent('call_ended', [
            'call_id' => 'c1',
            'transcript_object' => [
                ['role' => 'agent', 'content' => 'Hola, ¿qué desea pedir?'],
                ['role' => 'user', 'content' => 'Un durum de pollo.'],
            ],
        ]);

        $call = self::getContainer()->get(CallRepository::class)->findByProviderCallId('c1');

        self::assertInstanceOf(Call::class, $call);
        self::assertStringContainsString('Agente: Hola, ¿qué desea pedir?', (string) $call->getTranscript());
        self::assertStringContainsString('Cliente: Un durum de pollo.', (string) $call->getTranscript());
    }

    /**
     * @param array<string, mixed> $callData
     */
    private function sendEvent(string $event, array $callData): void
    {
        $body = json_encode(['event' => $event, 'call' => $callData], \JSON_THROW_ON_ERROR);
        $timestamp = (string) (time() * 1000);

        $this->client->request('POST', '/webhook/retell', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RETELL_SIGNATURE' => 'v='.$timestamp.',d='.hash_hmac('sha256', $body.$timestamp, self::SECRET),
        ], content: $body);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }
}
