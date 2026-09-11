<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Business;
use App\Entity\MenuImport;
use App\Entity\MenuItem;
use App\Entity\MenuVersion;
use App\Message\ProcessMenuImport;
use App\Repository\BusinessRepository;
use App\Repository\MenuImportRepository;
use App\Storage\ImportFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/negocios')]
class BusinessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BusinessRepository $businesses,
        private readonly MenuImportRepository $imports,
    ) {
    }

    #[Route('', name: 'app_businesses', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('business/index.html.twig', [
            'businesses' => $this->businesses->findAllOrdered(),
        ]);
    }

    #[Route('/nuevo', name: 'app_business_new', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('business_new', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));

        if ('' === $name) {
            $this->addFlash('error', 'El negocio necesita un nombre.');

            return $this->redirectToRoute('app_businesses');
        }

        $business = new Business(mb_substr($name, 0, 180));
        $this->em->persist($business);
        $this->em->flush();

        // El primer negocio pasa a atender las llamadas, para no dejar la demo sin nadie.
        if (null === $this->businesses->findActiveForDemo()) {
            $this->businesses->makeActiveForDemo($business);
        }

        return $this->redirectToRoute('app_business_show', ['id' => $business->getId()]);
    }

    #[Route('/{id}', name: 'app_business_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Business $business): Response
    {
        return $this->render('business/show.html.twig', [
            'business' => $business,
            'imports' => $this->imports->findForBusiness($business),
        ]);
    }

    #[Route('/{id}/activar', name: 'app_business_activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(Request $request, Business $business): Response
    {
        if (!$this->isCsrfTokenValid('business_activate'.$business->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->businesses->makeActiveForDemo($business);
        $this->addFlash('success', \sprintf('Las llamadas entrantes las atiende ahora "%s".', $business->getName()));

        return $this->redirectToRoute('app_business_show', ['id' => $business->getId()]);
    }

    #[Route('/{id}/cartas', name: 'app_business_import', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function import(Request $request, Business $business, ImportFileStorage $storage, MessageBusInterface $bus): Response
    {
        if (!$this->isCsrfTokenValid('menu_import'.$business->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter($request->files->all()['files'] ?? []));
        $text = trim((string) $request->request->get('text'));

        if ([] === $files && '' === $text) {
            $this->addFlash('error', 'Sube un PDF, sube imágenes o pega el texto de la carta.');

            return $this->redirectToRoute('app_business_show', ['id' => $business->getId()]);
        }

        if (\count($files) > ImportFileStorage::MAX_FILES) {
            $this->addFlash('error', \sprintf('Como mucho %d archivos a la vez.', ImportFileStorage::MAX_FILES));

            return $this->redirectToRoute('app_business_show', ['id' => $business->getId()]);
        }

        $sourceType = [] !== $files
            ? ($storage->isPdfUpload($files[0]) ? MenuImport::SOURCE_PDF : MenuImport::SOURCE_IMAGES)
            : MenuImport::SOURCE_TEXT;

        $import = new MenuImport($business, $sourceType);

        try {
            $stored = [];

            foreach ($files as $file) {
                $stored[] = $storage->store($file, (int) $business->getId());
            }

            $import->setSourceFiles($stored);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_business_show', ['id' => $business->getId()]);
        }

        if ('' !== $text) {
            $import->setSourceText(mb_substr($text, 0, 120000));
        }

        $this->em->persist($import);
        $this->em->flush();

        $bus->dispatch(new ProcessMenuImport((int) $import->getId()));

        $this->addFlash('success', 'Carta recibida. La lectura tarda un poco; esta página se actualiza sola.');

        return $this->redirectToRoute('app_business_show', ['id' => $business->getId()]);
    }

    /**
     * El panel pregunta por aquí cada pocos segundos mientras se lee una carta.
     */
    #[Route('/{id}/cartas/estado', name: 'app_business_import_status', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function importStatus(Business $business): JsonResponse
    {
        $states = [];

        foreach ($this->imports->findForBusiness($business) as $import) {
            $states[] = [
                'id' => $import->getId(),
                'estado' => $import->getStatus(),
                'error' => $import->getError(),
                'productos' => $import->getMenuVersion()?->countItems(),
            ];
        }

        return new JsonResponse(['importaciones' => $states]);
    }

    #[Route('/cartas/{id}', name: 'app_menu_review', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function review(MenuVersion $menuVersion): Response
    {
        return $this->render('business/review.html.twig', [
            'business' => $menuVersion->getBusiness(),
            'version' => $menuVersion,
        ]);
    }

    /**
     * Guarda la tabla editable. Publicar deja esta versión como la carta que usan las
     * llamadas nuevas.
     */
    #[Route('/cartas/{id}/guardar', name: 'app_menu_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Request $request, MenuVersion $menuVersion): Response
    {
        if (!$this->isCsrfTokenValid('menu_save'.$menuVersion->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $rows = $request->request->all()['items'] ?? [];
        $keptIds = [];
        $position = 0;

        foreach ($rows as $rowId => $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if (str_starts_with((string) $rowId, 'nuevo')) {
                if ('' === $name) {
                    continue;
                }

                $item = new MenuItem($menuVersion, mb_substr($name, 0, 200));
                $this->em->persist($item);
            } else {
                $item = $this->findItemInVersion($menuVersion, (int) $rowId);

                if (null === $item) {
                    continue;
                }

                if ('' === $name) {
                    $this->em->remove($item);
                    continue;
                }

                $item->setName(mb_substr($name, 0, 200));
                $keptIds[] = $item->getId();
            }

            $item->setCategory(mb_substr(trim((string) ($row['category'] ?? '')), 0, 120));
            $item->setDescription(trim((string) ($row['description'] ?? '')));
            $item->setPrice($this->normalizePrice((string) ($row['price'] ?? '')));
            $item->setPriceText(mb_substr(trim((string) ($row['price_text'] ?? '')), 0, 80));
            $item->setOptions(array_map('trim', explode(';', (string) ($row['options'] ?? ''))));
            $item->setNeedsReview(false);
            $item->setPosition($position++);
        }

        $publish = $request->request->has('publish');
        $this->em->flush();

        if ($publish) {
            if (0 === $menuVersion->countItems()) {
                $this->addFlash('error', 'No se puede publicar una carta sin productos.');

                return $this->redirectToRoute('app_menu_review', ['id' => $menuVersion->getId()]);
            }

            $menuVersion->publish();
            $menuVersion->getBusiness()->setPublishedMenu($menuVersion);
            $this->em->flush();

            $this->addFlash('success', \sprintf('Carta publicada con %d productos.', $menuVersion->countItems()));

            return $this->redirectToRoute('app_business_show', ['id' => $menuVersion->getBusiness()->getId()]);
        }

        $this->addFlash('success', 'Cambios guardados.');

        return $this->redirectToRoute('app_menu_review', ['id' => $menuVersion->getId()]);
    }

    private function findItemInVersion(MenuVersion $menuVersion, int $itemId): ?MenuItem
    {
        foreach ($menuVersion->getItems() as $item) {
            if ($item->getId() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Acepta "8,50" y "8.50". Lo que no sea un número positivo se queda sin precio, no
     * en cero, para que el total no salga mal.
     */
    private function normalizePrice(string $raw): ?string
    {
        $raw = trim(str_replace([' ', '€', ','], ['', '', '.'], $raw));

        if ('' === $raw || !is_numeric($raw) || (float) $raw <= 0) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }
}
