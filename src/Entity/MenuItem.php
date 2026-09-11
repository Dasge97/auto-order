<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un producto de la carta.
 *
 * Lo único obligatorio es el nombre. El precio numérico solo se rellena cuando la
 * carta lo dice sin lugar a dudas; un precio del tipo "desde 8 €" se queda en
 * priceText y price sigue siendo null.
 */
#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'menu_item')]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MenuVersion::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MenuVersion $menuVersion;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Opciones o variantes tal y como aparecen en la carta, sin reglas de obligatoriedad.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $options = [];

    /**
     * Precio en euros cuando es inequívoco. Null si falta o es dudoso, nunca cero.
     */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $price = null;

    /**
     * El precio tal cual está escrito en la carta, por ejemplo "desde 8 €".
     */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $priceText = null;

    /**
     * De dónde salió: página del PDF, nombre de la imagen o "texto pegado".
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sourceRef = null;

    /**
     * El modelo marca lo que ha leído con dudas para que se revise antes de publicar.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $needsReview = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __construct(MenuVersion $menuVersion, string $name)
    {
        $this->menuVersion = $menuVersion;
        $this->name = $name;
        $menuVersion->addItem($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenuVersion(): MenuVersion
    {
        return $this->menuVersion;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $this->blankToNull($category);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $this->blankToNull($description);
    }

    /** @return list<string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** @param list<string> $options */
    public function setOptions(array $options): void
    {
        $this->options = array_values(array_filter(
            array_map(static fn (string $o): string => trim($o), $options),
            static fn (string $o): bool => '' !== $o,
        ));
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getPriceAsFloat(): ?float
    {
        return null === $this->price ? null : (float) $this->price;
    }

    public function setPrice(?string $price): void
    {
        $this->price = $this->blankToNull($price);
    }

    public function getPriceText(): ?string
    {
        return $this->priceText;
    }

    public function setPriceText(?string $priceText): void
    {
        $this->priceText = $this->blankToNull($priceText);
    }

    public function getSourceRef(): ?string
    {
        return $this->sourceRef;
    }

    public function setSourceRef(?string $sourceRef): void
    {
        $this->sourceRef = $this->blankToNull($sourceRef);
    }

    public function needsReview(): bool
    {
        return $this->needsReview;
    }

    public function setNeedsReview(bool $needsReview): void
    {
        $this->needsReview = $needsReview;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * Lo que se le enseña al agente por teléfono.
     *
     * @return array<string, mixed>
     */
    public function toAgentArray(): array
    {
        $data = ['id' => $this->id, 'nombre' => $this->name];

        if (null !== $this->category) {
            $data['categoria'] = $this->category;
        }
        if (null !== $this->description) {
            $data['descripcion'] = $this->description;
        }
        if ([] !== $this->options) {
            $data['opciones'] = $this->options;
        }
        if (null !== $this->price) {
            $data['precio_eur'] = (float) $this->price;
        } elseif (null !== $this->priceText) {
            $data['precio_texto'] = $this->priceText;
        }

        return $data;
    }

    private function blankToNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
