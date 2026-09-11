<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WebhookReceipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookReceipt>
 */
class WebhookReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookReceipt::class);
    }

    /**
     * Devuelve true la primera vez que llega un evento y false si ya se procesó.
     *
     * Se apoya en la clave única de la base de datos, no en una consulta previa, para
     * que dos peticiones a la vez no pasen las dos.
     */
    public function markProcessedIfNew(string $eventKey): bool
    {
        $em = $this->getEntityManager();

        try {
            $em->getConnection()->insert('webhook_receipt', [
                'event_key' => $eventKey,
                'received_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
