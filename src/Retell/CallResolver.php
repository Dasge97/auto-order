<?php

declare(strict_types=1);

namespace App\Retell;

use App\Entity\Call;
use App\Repository\BusinessRepository;
use App\Repository\CallRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Encuentra la llamada a la que pertenece una petición, y la crea si hace falta.
 *
 * Una herramienta del agente puede llegar antes que el webhook de inicio de llamada,
 * así que aquí se crea la llamada en cuanto se la nombra por primera vez. El negocio
 * sale de cuál está marcado como activo, nunca del teléfono de quien llama.
 */
class CallResolver
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CallRepository $calls,
        private readonly BusinessRepository $businesses,
    ) {
    }

    public function findOrCreate(string $providerCallId): ?Call
    {
        $providerCallId = trim($providerCallId);

        if ('' === $providerCallId) {
            return null;
        }

        $call = $this->calls->findByProviderCallId($providerCallId);

        if (null !== $call) {
            return $call;
        }

        $business = $this->businesses->findActiveForDemo();

        if (null === $business) {
            return null;
        }

        $call = new Call($providerCallId, $business, $business->getPublishedMenu());
        $this->em->persist($call);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Dos peticiones de la misma llamada a la vez: se queda la que ganó.
            $this->em->clear();

            return $this->calls->findByProviderCallId($providerCallId);
        }

        return $call;
    }

    public function find(string $providerCallId): ?Call
    {
        return $this->calls->findByProviderCallId(trim($providerCallId));
    }
}
