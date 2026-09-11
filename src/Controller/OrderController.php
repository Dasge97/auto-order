<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orders,
    ) {
    }

    #[Route('/', name: 'app_orders', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('order/index.html.twig', [
            'orders' => $this->orders->findRecent(),
        ]);
    }

    /**
     * El panel pregunta por aquí cada dos segundos si han entrado pedidos nuevos.
     */
    #[Route('/pedidos/nuevos', name: 'app_orders_new', methods: ['GET'])]
    public function newest(Request $request): JsonResponse
    {
        $lastId = $request->query->getInt('desde') ?: null;
        $orders = $this->orders->findNewerThan($lastId);

        $rows = [];

        foreach ($orders as $order) {
            $rows[] = [
                'id' => $order->getId(),
                'referencia' => $order->getReference(),
                'negocio' => $order->getBusiness()->getName(),
                'hora' => $order->getCreatedAt()->format('H:i'),
                'productos' => $order->getItems()->count(),
                'simulado' => $order->getCall()->isSimulated(),
            ];
        }

        return new JsonResponse(['pedidos' => $rows, 'servidor' => time()]);
    }

    #[Route('/pedidos/{id}', name: 'app_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order): Response
    {
        if (!$order->isSeen()) {
            $order->markSeen();
            $this->em->flush();
        }

        return $this->render('order/show.html.twig', ['order' => $order]);
    }

    #[Route('/pedidos/{id}/ticket', name: 'app_order_ticket', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function ticket(Order $order): Response
    {
        return $this->render('order/ticket.html.twig', ['order' => $order]);
    }
}
