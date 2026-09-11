<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BusinessRepository;
use App\Retell\AgentPrompt;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Enseña lo que hay que pegar en Retell: instrucciones del agente, herramientas y
 * dirección del webhook, ya con el dominio de esta instalación.
 */
class RetellSetupController extends AbstractController
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        #[Autowire('%env(RETELL_TOOL_TOKEN)%')]
        private readonly string $toolToken,
        #[Autowire('%env(RETELL_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {
    }

    #[Route('/retell', name: 'app_retell_setup', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        $business = $this->businesses->findActiveForDemo();

        $prompt = str_replace('{{nombre_negocio}}', $business?->getName() ?? 'el negocio', AgentPrompt::PROMPT);

        return $this->render('retell/setup.html.twig', [
            'business' => $business,
            'prompt' => $prompt,
            'tools' => AgentPrompt::toolDefinitions($baseUrl, $this->toolToken),
            'webhook_url' => $baseUrl.'/webhook/retell',
            'token_configurado' => '' !== $this->toolToken && 'cambia-esto-en-produccion' !== $this->toolToken,
            'secreto_configurado' => '' !== $this->webhookSecret,
        ]);
    }
}
