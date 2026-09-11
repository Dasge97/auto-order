<?php

declare(strict_types=1);

namespace App\Storage;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Guarda los archivos de carta que subes.
 *
 * Van a var/uploads, que está fuera de la carpeta pública, así que nadie puede
 * pedirlos por URL ni ejecutarlos.
 */
class ImportFileStorage
{
    public const MAX_FILE_BYTES = 20 * 1024 * 1024;
    public const MAX_FILES = 12;

    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private readonly Filesystem $filesystem;

    public function __construct(private readonly string $uploadDir)
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @return string ruta relativa con la que se vuelve a encontrar el archivo
     *
     * @throws \InvalidArgumentException si el archivo no vale
     */
    public function store(UploadedFile $file, int $businessId): string
    {
        if ($file->getSize() > self::MAX_FILE_BYTES) {
            throw new \InvalidArgumentException(\sprintf('"%s" pesa más de %d MB.', $file->getClientOriginalName(), self::MAX_FILE_BYTES / 1024 / 1024));
        }

        $mimeType = (string) $file->getMimeType();

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException(\sprintf('"%s" no es un PDF ni una imagen.', $file->getClientOriginalName()));
        }

        $relativeDir = 'negocio-'.$businessId;
        $name = bin2hex(random_bytes(8)).'.'.self::ALLOWED_MIME_TYPES[$mimeType];

        $this->filesystem->mkdir($this->uploadDir.'/'.$relativeDir);
        $file->move($this->uploadDir.'/'.$relativeDir, $name);

        return $relativeDir.'/'.$name;
    }

    public function absolutePath(string $relativePath): string
    {
        if (str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException('Ruta de archivo no válida.');
        }

        return $this->uploadDir.'/'.$relativePath;
    }

    public function detectMimeType(string $absolutePath): string
    {
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($absolutePath);

        return \is_string($mimeType) && isset(self::ALLOWED_MIME_TYPES[$mimeType]) ? $mimeType : 'application/octet-stream';
    }

    public function isPdf(string $relativePath): bool
    {
        return str_ends_with($relativePath, '.pdf');
    }

    public function isPdfUpload(UploadedFile $file): bool
    {
        return 'application/pdf' === $file->getMimeType();
    }

    public function exists(string $relativePath): bool
    {
        return is_readable($this->absolutePath($relativePath));
    }
}
