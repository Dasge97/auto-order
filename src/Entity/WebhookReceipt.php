<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WebhookReceiptRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Deja constancia de cada evento de Retell ya procesado.
 *
 * La clave única es lo que hace que un evento repetido no se procese dos veces:
 * insertarlo falla y el controlador responde que ya estaba hecho.
 */
#[ORM\Entity(repositoryClass: WebhookReceiptRepository::class)]
#[ORM\Table(name: 'webhook_receipt')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_event', columns: ['event_key'])]
class WebhookReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $eventKey;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    public function __construct(string $eventKey)
    {
        $this->eventKey = $eventKey;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventKey(): string
    {
        return $this->eventKey;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
