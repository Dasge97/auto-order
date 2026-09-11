<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un pedido tomado por teléfono.
 *
 * Una llamada tiene como mucho un pedido, y lo garantiza la restricción única sobre
 * la columna de la llamada. Es lo que evita que una confirmación repetida o un
 * timeout creen dos pedidos.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'customer_order')]
#[ORM\UniqueConstraint(name: 'uniq_order_call', columns: ['call_id'])]
class Order
{
    public const FULFILLMENT_PICKUP = 'recoger';
    public const FULFILLMENT_DELIVERY = 'domicilio';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Business::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Business $business;

    #[ORM\OneToOne(targetEntity: Call::class)]
    #[ORM\JoinColumn(name: 'call_id', nullable: false, onDelete: 'CASCADE')]
    private Call $call;

    /**
     * Referencia corta que el agente le dice al cliente por teléfono.
     */
    #[ORM\Column(length: 12)]
    private string $reference;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $fulfillment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * Suma del pedido. Solo se rellena si todos los productos tienen precio; si a
     * alguno le falta, se queda en null y no se enseña ningún total.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $total = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $seen = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    public function __construct(Business $business, Call $call, string $reference)
    {
        $this->business = $business;
        $this->call = $call;
        $this->reference = $reference;
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBusiness(): Business
    {
        return $this->business;
    }

    public function getCall(): Call
    {
        return $this->call;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): void
    {
        $this->customerName = $customerName;
    }

    public function getFulfillment(): ?string
    {
        return $this->fulfillment;
    }

    public function setFulfillment(?string $fulfillment): void
    {
        $this->fulfillment = \in_array($fulfillment, [self::FULFILLMENT_PICKUP, self::FULFILLMENT_DELIVERY], true)
            ? $fulfillment
            : null;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function getTotalAsFloat(): ?float
    {
        return null === $this->total ? null : (float) $this->total;
    }

    public function isSeen(): bool
    {
        return $this->seen;
    }

    public function markSeen(): void
    {
        $this->seen = true;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }
    }

    /**
     * Recalcula el total. Si a un solo producto le falta el precio, no hay total.
     */
    public function recalculateTotal(): void
    {
        $sum = 0.0;

        foreach ($this->items as $item) {
            $unitPrice = $item->getUnitPriceAsFloat();

            if (null === $unitPrice) {
                $this->total = null;

                return;
            }

            $sum += $unitPrice * $item->getQuantity();
        }

        $this->total = $this->items->isEmpty() ? null : number_format($sum, 2, '.', '');
    }
}
