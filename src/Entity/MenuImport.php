<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuImportRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una carga de carta: los archivos o el texto que has subido y cómo fue la lectura.
 *
 * Se guarda antes de llamar a OpenAI, y un worker la procesa. Así la pantalla no se
 * queda colgada mientras el modelo lee el PDF.
 */
#[ORM\Entity(repositoryClass: MenuImportRepository::class)]
#[ORM\Table(name: 'menu_import')]
class MenuImport
{
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_PROCESSING = 'procesando';
    public const STATUS_READY = 'listo';
    public const STATUS_ERROR = 'error';

    public const SOURCE_PDF = 'pdf';
    public const SOURCE_IMAGES = 'imagenes';
    public const SOURCE_TEXT = 'texto';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Business::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Business $business;

    #[ORM\Column(length: 20)]
    private string $sourceType;

    /**
     * Rutas relativas de los archivos guardados, fuera de la carpeta pública.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $sourceFiles = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sourceText = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    /**
     * Versión en borrador que ha salido de esta lectura.
     */
    #[ORM\ManyToOne(targetEntity: MenuVersion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MenuVersion $menuVersion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(Business $business, string $sourceType)
    {
        $this->business = $business;
        $this->sourceType = $sourceType;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBusiness(): Business
    {
        return $this->business;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    /** @return list<string> */
    public function getSourceFiles(): array
    {
        return $this->sourceFiles;
    }

    /** @param list<string> $sourceFiles */
    public function setSourceFiles(array $sourceFiles): void
    {
        $this->sourceFiles = array_values($sourceFiles);
    }

    public function getSourceText(): ?string
    {
        return $this->sourceText;
    }

    public function setSourceText(?string $sourceText): void
    {
        $this->sourceText = $sourceText;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
    }

    public function markReady(MenuVersion $menuVersion): void
    {
        $this->status = self::STATUS_READY;
        $this->menuVersion = $menuVersion;
        $this->error = null;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function markError(string $error): void
    {
        $this->status = self::STATUS_ERROR;
        $this->error = $error;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getMenuVersion(): ?MenuVersion
    {
        return $this->menuVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function isFinished(): bool
    {
        return self::STATUS_READY === $this->status || self::STATUS_ERROR === $this->status;
    }

    public function describeSource(): string
    {
        return match ($this->sourceType) {
            self::SOURCE_PDF => 'PDF',
            self::SOURCE_IMAGES => \count($this->sourceFiles).' imagen(es)',
            default => 'Texto pegado',
        };
    }
}
