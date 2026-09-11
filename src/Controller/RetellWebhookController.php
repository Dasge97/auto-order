<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Call;
use App\Repository\WebhookReceiptRepository;
use App\Retell\CallResolver;
use App\Retell\RetellWebhookVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recibe los eventos de llamada de Retell.
 *
 * Solo sirven para seguimiento: aquí nunca se crea un pedido. El análisis que Retell
 * manda al terminar la llamada no puede inventar un pedido que el cliente no confirmó.
 */
class RetellWebhookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RetellWebhookVerifier $verifier,
        private readonly CallResolver $callResolver,
        private readonly WebhookReceiptRepository $receipts,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/webhook/retell', name: 'app_retell_webhook', methods: ['POST'])]
    public function receive(Request $request): JsonResponse
    {
        if (!$this->verifier->isValid($request)) {
            $this->logger->warning('Webhook de Retell rechazado por firma incorrecta.');

            return new JsonResponse(['error' => 'firma no válida'], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'cuerpo no válido'], 400);
        }

        $event = (string) ($payload['event'] ?? '');
        $callData = \is_array($payload['call'] ?? null) ? $payload['call'] : [];
        $providerCallId = (string) ($callData['call_id'] ?? '');

        if ('' === $event || '' === $providerCallId) {
            return new JsonResponse(['error' => 'faltan datos del evento'], 400);
        }

        // Un evento repetido no se vuelve a procesar. La clave la da la base de datos.
        if (!$this->receipts->markProcessedIfNew($event.':'.$providerCallId)) {
            return new JsonResponse(['ok' => true, 'repetido' => true]);
        }

        $call = $this->callResolver->findOrCreate($providerCallId);

        if (null === $call) {
            $this->logger->warning('Evento de Retell sin negocio activo al que asignarlo.');

            return new JsonResponse(['ok' => true, 'ignorado' => 'no hay negocio activo']);
        }

        $this->applyEvent($call, $event, $callData);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @param array<string, mixed> $callData
     */
    private function applyEvent(Call $call, string $event, array $callData): void
    {
        if (null === $call->getFromNumber() && \is_string($callData['from_number'] ?? null)) {
            $call->setFromNumber(mb_substr($callData['from_number'], 0, 40));
        }

        if (null === $call->getToNumber() && \is_string($callData['to_number'] ?? null)) {
            $call->setToNumber(mb_substr($callData['to_number'], 0, 40));
        }

        match ($event) {
            'call_started' => $call->advanceStatus(Call::STATUS_ONGOING),
            'call_ended' => $this->applyCallEnded($call, $callData),
            'call_analyzed' => $call->advanceStatus(Call::STATUS_ANALYZED),
            default => null,
        };

        if (\is_array($callData['transcript_object'] ?? null)) {
            $call->setTranscript($this->flattenTranscript($callData['transcript_object']));
        } elseif (\is_string($callData['transcript'] ?? null)) {
            $call->setTranscript(mb_substr($callData['transcript'], 0, 100000));
        }
    }

    /**
     * @param array<string, mixed> $callData
     */
    private function applyCallEnded(Call $call, array $callData): void
    {
        $call->advanceStatus(Call::STATUS_ENDED);

        if (null === $call->getEndedAt()) {
            $call->setEndedAt(new \DateTimeImmutable());
        }

        if (is_numeric($callData['duration_ms'] ?? null)) {
            $call->setDurationSeconds((int) round(((float) $callData['duration_ms']) / 1000));
        }
    }

    /**
     * @param list<mixed> $turns
     */
    private function flattenTranscript(array $turns): string
    {
        $lines = [];

        foreach ($turns as $turn) {
            if (!\is_array($turn)) {
                continue;
            }

            $role = 'agent' === ($turn['role'] ?? '') ? 'Agente' : 'Cliente';
            $content = \is_string($turn['content'] ?? null) ? trim($turn['content']) : '';

            if ('' !== $content) {
                $lines[] = $role.': '.$content;
            }
        }

        return mb_substr(implode("\n", $lines), 0, 100000);
    }
}
