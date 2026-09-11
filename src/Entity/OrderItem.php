<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Una línea del pedido.
 *
 * Guarda una copia del nombre y del precio. Así el pedido sigue leyéndose igual
 * aunque después se publique otra carta o se borre el producto.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: MenuItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MenuItem $menuItem = null;

    #[ORM\Column(length: 200)]
    private string $nameCopy;

    #[ORM\Column]
    private int $quantity;

    /**
     * Lo que ha pedido el cliente sobre este producto, por ejemplo "sin cebolla".
     * Es una petición apuntada, no una opción garantizada.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $modifications = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    public function __construct(Order $order, string $nameCopy, int $quantity, ?MenuItem $menuItem = null)
    {
        $this->order = $order;
        $this->nameCopy = $nameCopy;
        $this->quantity = $quantity;
        $this->menuItem = $menuItem;
        $this->unitPrice = $menuItem?->getPrice();
        $order->addItem($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getMenuItem(): ?MenuItem
    {
        return $this->menuItem;
    }

    public function getNameCopy(): string
    {
        return $this->nameCopy;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getModifications(): ?string
    {
        return $this->modifications;
    }

    public function setModifications(?string $modifications): void
    {
        $modifications = null === $modifications ? null : trim($modifications);
        $this->modifications = '' === $modifications ? null : $modifications;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function getUnitPriceAsFloat(): ?float
    {
        return null === $this->unitPrice ? null : (float) $this->unitPrice;
    }

    public function getLineTotalAsFloat(): ?float
    {
        $unitPrice = $this->getUnitPriceAsFloat();

        return null === $unitPrice ? null : $unitPrice * $this->quantity;
    }
}
