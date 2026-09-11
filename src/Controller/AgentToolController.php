<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Order\OrderDraft;
use App\Order\OrderException;
use App\Order\OrderService;
use App\Repository\OrderRepository;
use App\Retell\CallResolver;
use App\Retell\ToolAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Las tres herramientas que llama el agente durante la conversación.
 *
 * El negocio y la llamada salen del token y del identificador de llamada, nunca de un
 * argumento que elija el modelo. Así el agente no puede tocar otro negocio.
 */
#[Route('/api/agent')]
class AgentToolController extends AbstractController
{
    public function __construct(
        private readonly ToolAuthenticator $authenticator,
        private readonly CallResolver $callResolver,
        private readonly OrderService $orderService,
        private readonly OrderRepository $orders,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/get_menu', name: 'app_tool_get_menu', methods: ['POST'])]
    public function getMenu(Request $request): JsonResponse
    {
        if (!$this->authenticator->isValid($request)) {
            return $this->unauthorized();
        }

        $payload = $this->decode($request);
        $callId = $this->extractCallId($payload);
        $call = $this->callResolver->findOrCreate($callId);

        if (null === $call) {
            return new JsonResponse($this->describeMissingCall($callId), 200);
        }

        $menuVersion = $call->getMenuVersion();

        if (null === $menuVersion || 0 === $menuVersion->countItems()) {
            return new JsonResponse([
                'error' => OrderException::MENU_NOT_READY,
                'mensaje' => 'Este negocio todavía no tiene carta publicada. Discúlpate y no tomes el pedido.',
            ], 200);
        }

        $items = [];

        foreach ($menuVersion->getItems() as $item) {
            $items[] = $item->toAgentArray();
        }

        return new JsonResponse([
            'negocio' => $call->getBusiness()->getName(),
            'productos' => $items,
            'aviso' => 'Usa solo estos productos. Si falta un precio, no lo inventes ni lo des por cero.',
        ]);
    }

    #[Route('/create_order', name: 'app_tool_create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        if (!$this->authenticator->isValid($request)) {
            return $this->unauthorized();
        }

        $payload = $this->decode($request);
        $callId = $this->extractCallId($payload);
        $call = $this->callResolver->findOrCreate($callId);

        if (null === $call) {
            return new JsonResponse($this->describeMissingCall($callId), 200);
        }

        try {
            $order = $this->orderService->createOrder($call, OrderDraft::fromArray($this->extractArguments($payload)));
        } catch (OrderException $e) {
            return new JsonResponse(array_filter([
                'error' => $e->errorCode,
                'mensaje' => $e->getMessage(),
                'sugerencias' => $e->suggestions,
            ]), 200);
        } catch (\Throwable $e) {
            $this->logger->error('Fallo al guardar un pedido', ['exception' => $e]);

            return new JsonResponse([
                'error' => 'ERROR_TECNICO',
                'mensaje' => 'No se ha podido guardar el pedido. No le digas al cliente que está registrado.',
            ], 200);
        }

        return new JsonResponse([
            'guardado' => true,
            'referencia' => $order->getReference(),
            'total_eur' => $order->getTotalAsFloat(),
            'mensaje' => $this->confirmationMessage($order),
        ]);
    }

    #[Route('/get_current_order', name: 'app_tool_get_current_order', methods: ['POST'])]
    public function getCurrentOrder(Request $request): JsonResponse
    {
        if (!$this->authenticator->isValid($request)) {
            return $this->unauthorized();
        }

        $payload = $this->decode($request);
        $call = $this->callResolver->find($this->extractCallId($payload));
        $order = null === $call ? null : $this->orders->findByCall($call);

        if (null === $order) {
            return new JsonResponse(['existe' => false, 'mensaje' => 'Esta llamada todavía no tiene pedido guardado.']);
        }

        $items = [];

        foreach ($order->getItems() as $item) {
            $items[] = array_filter([
                'nombre' => $item->getNameCopy(),
                'cantidad' => $item->getQuantity(),
                'modificaciones' => $item->getModifications(),
            ], static fn ($value): bool => null !== $value);
        }

        return new JsonResponse([
            'existe' => true,
            'referencia' => $order->getReference(),
            'productos' => $items,
            'total_eur' => $order->getTotalAsFloat(),
            'mensaje' => 'El pedido ya está registrado. No lo guardes otra vez.',
        ]);
    }

    /**
     * Dos motivos distintos para no tener llamada: que la petición no diga de qué
     * llamada habla, o que no haya negocio atendiendo. Se distinguen para que el
     * agente no cuente una cosa por otra.
     *
     * @return array<string, mixed>
     */
    private function describeMissingCall(string $callId): array
    {
        if ('' === $callId) {
            $this->logger->warning('Una herramienta del agente ha llegado sin identificador de llamada.');

            return [
                'error' => 'ERROR_TECNICO',
                'mensaje' => 'No se ha podido identificar esta llamada. No tomes el pedido.',
            ];
        }

        return [
            'error' => OrderException::MENU_NOT_READY,
            'mensaje' => 'No hay ningún negocio activo para atender esta llamada. Discúlpate y no tomes el pedido.',
        ];
    }

    private function confirmationMessage(Order $order): string
    {
        $message = \sprintf('Pedido registrado con la referencia %s.', $order->getReference());

        if (null !== $order->getTotalAsFloat()) {
            $message .= \sprintf(' Son %s euros.', number_format($order->getTotalAsFloat(), 2, ',', ''));
        }

        return $message;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(['error' => 'NO_AUTORIZADO'], 401);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    /**
     * Retell manda el identificador de la llamada dentro de "call", y los argumentos
     * del modelo en "args". Se aceptan las dos formas por si cambia el contrato.
     *
     * @param array<string, mixed> $payload
     */
    private function extractCallId(array $payload): string
    {
        $candidates = [
            $payload['call']['call_id'] ?? null,
            $payload['call_id'] ?? null,
            $payload['args']['call_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (\is_string($candidate) && '' !== trim($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function extractArguments(array $payload): array
    {
        if (isset($payload['args']) && \is_array($payload['args'])) {
            return $payload['args'];
        }

        return $payload;
    }
}
