<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Call;
use App\Repository\CallRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/llamadas')]
class CallController extends AbstractController
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly OrderRepository $orders,
    ) {
    }

    #[Route('', name: 'app_calls', methods: ['GET'])]
    public function index(): Response
    {
        $calls = $this->calls->findRecent();
        $ordersByCall = [];

        foreach ($calls as $call) {
            $ordersByCall[$call->getId()] = $this->orders->findByCall($call);
        }

        return $this->render('call/index.html.twig', [
            'calls' => $calls,
            'orders_by_call' => $ordersByCall,
        ]);
    }

    #[Route('/{id}', name: 'app_call_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Call $call): Response
    {
        return $this->render('call/show.html.twig', [
            'call' => $call,
            'order' => $this->orders->findByCall($call),
        ]);
    }
}
