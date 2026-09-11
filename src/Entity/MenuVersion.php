<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuVersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una versión de la carta de un negocio, con sus productos.
 *
 * Cada importación genera una versión en borrador. Publicarla la convierte en la
 * carta que usan las llamadas nuevas.
 */
#[ORM\Entity(repositoryClass: MenuVersionRepository::class)]
#[ORM\Table(name: 'menu_version')]
class MenuVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Business::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Business $business;

    #[ORM\Column(options: ['default' => false])]
    private bool $published = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** @var Collection<int, MenuItem> */
    #[ORM\OneToMany(mappedBy: 'menuVersion', targetEntity: MenuItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct(Business $business)
    {
        $this->business = $business;
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

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function publish(): void
    {
        $this->published = true;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /** @return Collection<int, MenuItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(MenuItem $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }
    }

    public function removeItem(MenuItem $item): void
    {
        $this->items->removeElement($item);
    }

    public function countItems(): int
    {
        return $this->items->count();
    }
}
