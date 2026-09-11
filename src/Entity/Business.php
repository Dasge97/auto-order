<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BusinessRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un negocio de demostración: su nombre y la carta que tiene publicada.
 */
#[ORM\Entity(repositoryClass: BusinessRepository::class)]
#[ORM\Table(name: 'business')]
class Business
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    /**
     * Menú que usan las llamadas nuevas. Null mientras no se haya publicado ninguno.
     */
    #[ORM\ManyToOne(targetEntity: MenuVersion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MenuVersion $publishedMenu = null;

    /**
     * Negocio que atiende las llamadas entrantes ahora mismo. Solo uno a la vez.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $activeForDemo = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPublishedMenu(): ?MenuVersion
    {
        return $this->publishedMenu;
    }

    public function setPublishedMenu(?MenuVersion $publishedMenu): void
    {
        $this->publishedMenu = $publishedMenu;
    }

    public function isActiveForDemo(): bool
    {
        return $this->activeForDemo;
    }

    public function setActiveForDemo(bool $activeForDemo): void
    {
        $this->activeForDemo = $activeForDemo;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function hasPublishedMenu(): bool
    {
        return null !== $this->publishedMenu;
    }
}
