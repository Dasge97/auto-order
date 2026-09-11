<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Call;
use App\Order\OrderDraft;
use App\Order\OrderException;
use App\Order\OrderService;
use App\Repository\BusinessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Crea un pedido sin telefonear, pasando por el mismo código que usa el agente.
 *
 * Sirve para probar el panel y el ticket sin gastar llamadas. El pedido queda marcado
 * como simulado para que no se confunda con uno real durante una demostración.
 */
#[Route('/simular')]
class SimulatorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BusinessRepository $businesses,
        private readonly OrderService $orderService,
    ) {
    }

    #[Route('', name: 'app_simulator', methods: ['GET'])]
    public function form(): Response
    {
        return $this->render('simulator/form.html.twig', [
            'business' => $this->businesses->findActiveForDemo(),
        ]);
    }

    #[Route('', name: 'app_simulator_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('simulator', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $business = $this->businesses->findActiveForDemo();

        if (null === $business || !$business->hasPublishedMenu()) {
            $this->addFlash('error', 'El negocio activo no tiene carta publicada.');

            return $this->redirectToRoute('app_simulator');
        }

        $call = new Call('sim_'.bin2hex(random_bytes(8)), $business, $business->getPublishedMenu());
        $call->setSimulated(true);
        $call->advanceStatus(Call::STATUS_ENDED);
        $call->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($call);
        $this->em->flush();

        $items = [];

        foreach ($request->request->all()['items'] ?? [] as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($itemId > 0 && $quantity > 0) {
                $items[] = [
                    'item_id' => $itemId,
                    'cantidad' => $quantity,
                    'modificaciones' => (string) ($row['modifications'] ?? ''),
                ];
            }
        }

        if ([] === $items) {
            $this->em->remove($call);
            $this->em->flush();
            $this->addFlash('error', 'Añade al menos un producto.');

            return $this->redirectToRoute('app_simulator');
        }

        $draft = OrderDraft::fromArray([
            'items' => $items,
            'confirmado' => true,
            'nombre_cliente' => (string) $request->request->get('customer_name'),
            'modalidad' => (string) $request->request->get('fulfillment'),
            'observaciones' => (string) $request->request->get('notes'),
        ]);

        try {
            $order = $this->orderService->createOrder($call, $draft);
        } catch (OrderException $e) {
            $this->em->remove($call);
            $this->em->flush();
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_simulator');
        }

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }
}
