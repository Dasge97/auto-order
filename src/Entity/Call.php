<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CallRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una llamada, real o simulada.
 *
 * Queda atada a la versión de carta que había publicada cuando empezó. Si publicas
 * otra carta a media llamada, la conversación sigue con la suya.
 */
#[ORM\Entity(repositoryClass: CallRepository::class)]
#[ORM\Table(name: 'call_record')]
#[ORM\UniqueConstraint(name: 'uniq_provider_call_id', columns: ['provider_call_id'])]
class Call
{
    public const STATUS_ONGOING = 'en curso';
    public const STATUS_ENDED = 'terminada';
    public const STATUS_ANALYZED = 'analizada';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Identificador de la llamada en Retell. Para una llamada simulada lo generamos
     * nosotros con el prefijo "sim_".
     */
    #[ORM\Column(length: 120)]
    private string $providerCallId;

    #[ORM\ManyToOne(targetEntity: Business::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Business $business;

    #[ORM\ManyToOne(targetEntity: MenuVersion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MenuVersion $menuVersion = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_ONGOING])]
    private string $status = self::STATUS_ONGOING;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $fromNumber = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $toNumber = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $simulated = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $transcript = null;

    public function __construct(string $providerCallId, Business $business, ?MenuVersion $menuVersion)
    {
        $this->providerCallId = $providerCallId;
        $this->business = $business;
        $this->menuVersion = $menuVersion;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderCallId(): string
    {
        return $this->providerCallId;
    }

    public function getBusiness(): Business
    {
        return $this->business;
    }

    public function getMenuVersion(): ?MenuVersion
    {
        return $this->menuVersion;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Los estados solo avanzan. Un webhook que llegue tarde no devuelve la llamada
     * a "en curso".
     */
    public function advanceStatus(string $status): void
    {
        $order = [self::STATUS_ONGOING => 0, self::STATUS_ENDED => 1, self::STATUS_ANALYZED => 2];

        if (($order[$status] ?? -1) > ($order[$this->status] ?? 0)) {
            $this->status = $status;
        }
    }

    public function getFromNumber(): ?string
    {
        return $this->fromNumber;
    }

    public function setFromNumber(?string $fromNumber): void
    {
        $this->fromNumber = $fromNumber;
    }

    public function getToNumber(): ?string
    {
        return $this->toNumber;
    }

    public function setToNumber(?string $toNumber): void
    {
        $this->toNumber = $toNumber;
    }

    public function isSimulated(): bool
    {
        return $this->simulated;
    }

    public function setSimulated(bool $simulated): void
    {
        $this->simulated = $simulated;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): void
    {
        $this->durationSeconds = $durationSeconds;
    }

    public function getTranscript(): ?string
    {
        return $this->transcript;
    }

    public function setTranscript(?string $transcript): void
    {
        $this->transcript = $transcript;
    }
}
