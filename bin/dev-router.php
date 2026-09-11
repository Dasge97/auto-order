<?php

/**
 * Router para el servidor integrado de PHP en desarrollo.
 *
 * Sin él, el servidor manda todas las peticiones al front controller y no llega a
 * servir la hoja de estilos ni las imágenes de public/.
 *
 * Uso:
 *   php -d upload_max_filesize=25M -d post_max_size=100M -S 127.0.0.1:8000 -t public bin/dev-router.php
 */

declare(strict_types=1);

$publicDir = \dirname(__DIR__).'/public';
$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], \PHP_URL_PATH);

if ('/' !== $path && is_file($publicDir.$path)) {
    return false;
}

require $publicDir.'/index.php';
