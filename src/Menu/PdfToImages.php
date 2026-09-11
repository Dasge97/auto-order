<?php

declare(strict_types=1);

namespace App\Menu;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Convierte un PDF en imágenes, una por página.
 *
 * Las cartas llegan en PDF, pero los modelos leen imágenes en todas partes y
 * documentos PDF solo en algunos sitios y cada uno con su formato. Convirtiendo
 * aquí, el resto del código manda siempre imágenes y funciona con cualquier
 * proveedor.
 *
 * Usa pdftoppm, del paquete poppler-utils.
 */
class PdfToImages
{
    public const MAX_PAGES = 10;

    private const DPI = 150;

    public function __construct(private readonly string $workDir)
    {
    }

    public function isAvailable(): bool
    {
        $process = new Process(['pdftoppm', '-v']);
        $process->setTimeout(10);

        try {
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        // pdftoppm -v escribe la versión en la salida de error y devuelve 99.
        return str_contains($process->getErrorOutput().$process->getOutput(), 'pdftoppm');
    }

    /**
     * @return list<string> rutas de los PNG generados, una por página
     *
     * @throws MenuExtractionException
     */
    public function convert(string $pdfPath): array
    {
        if (!$this->isAvailable()) {
            throw new MenuExtractionException('Este servidor no puede convertir PDF. Sube la carta como imágenes o pega su texto.');
        }

        if (!is_dir($this->workDir) && !mkdir($this->workDir, 0775, true) && !is_dir($this->workDir)) {
            throw new MenuExtractionException('No se ha podido preparar la carpeta de trabajo para convertir el PDF.');
        }

        $prefix = $this->workDir.'/'.bin2hex(random_bytes(8));

        $process = new Process([
            'pdftoppm',
            '-png',
            '-r', (string) self::DPI,
            '-f', '1',
            '-l', (string) self::MAX_PAGES,
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout(180);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new MenuExtractionException('No se ha podido leer el PDF: '.trim($process->getErrorOutput()), 0, $e);
        }

        $pages = glob($prefix.'*.png') ?: [];
        sort($pages);

        if ([] === $pages) {
            throw new MenuExtractionException('El PDF no tiene páginas legibles. Prueba a subir imágenes de la carta.');
        }

        return array_values($pages);
    }

    /**
     * @param list<string> $paths
     */
    public function cleanUp(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
