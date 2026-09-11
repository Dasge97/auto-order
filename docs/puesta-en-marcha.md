# Puesta en marcha

## En tu portátil

Necesitas PHP 8.4 o superior con las extensiones `pdo_pgsql`, `intl`, `gd`, `zip` y `mbstring`, Composer y Docker.

```bash
composer install

# Base de datos en Docker, en el puerto 55432 para no chocar con otro PostgreSQL.
docker run -d --name auto-order-db \
  -e POSTGRES_USER=autoorder -e POSTGRES_PASSWORD=autoorder -e POSTGRES_DB=autoorder \
  -p 55432:5432 postgres:17-alpine

php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

Pon tus claves en `.env.local`, que no se sube a git:

```
MENU_API_KEY=sk-...
RETELL_WEBHOOK_SECRET=el-secreto-del-webhook-de-retell
RETELL_TOOL_TOKEN=una-cadena-larga-que-elijas-tú
```

En local no se pueden leer cartas en PDF salvo que tengas `pdftoppm` instalado, que viene en el paquete poppler-utils. En el servidor sí está, porque lo instala la imagen de Docker. Sin él, sube imágenes o pega el texto.

Arranca el servidor y el worker en dos terminales:

```bash
php -d upload_max_filesize=25M -d post_max_size=120M -S 127.0.0.1:8000 -t public bin/dev-router.php
php bin/console messenger:consume async -vv
```

Entra en http://127.0.0.1:8000 con `admin` / `admin`.

El worker es el que lee las cartas. Sin él, una carga se queda en "En cola" para siempre.

Para trastear sin gastar lecturas de OpenAI, crea un negocio de prueba con carta ya publicada:

```bash
php bin/console app:cargar-ejemplo
```

## Pruebas

```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/phpunit
```

Las pruebas usan una base de datos aparte y rehacen su esquema solas. No llaman a OpenAI ni a Retell de verdad.

## En el servidor

El despliegue sigue el flujo de CodeHive: el código se edita en local, se sube a GitHub y en el servidor solo se hace pull, sync y redeploy.

Los tres contenedores son la aplicación, el worker y PostgreSQL. El comando exacto está en el README_DEPLOY.md del repositorio fuente del servidor, que no se sube a GitHub.

Antes hay que crear ahí un `.env.local` con:

```
APP_SECRET=cadena-larga-aleatoria
APP_PORT=4141
POSTGRES_PASSWORD=contraseña-larga
ADMIN_USER=admin
ADMIN_PASSWORD_HASH='...'
MENU_API_BASE_URL=https://auth2api.code-hive.space/v1
MENU_API_KEY=sk-...
MENU_MODEL=claude-sonnet-5
MENU_SCHEMA_NAME=
RETELL_API_KEY=...
RETELL_WEBHOOK_SECRET=...
RETELL_TOOL_TOKEN=...
```

La carta se lee a través de auth2api, el proxy de code-hive, que habla el formato de chat de OpenAI. `MENU_SCHEMA_NAME` se deja vacío porque ese proxy rechaza la petición si el esquema lleva nombre. Contra la API de OpenAI de verdad hay que ponerle un valor, por ejemplo `carta`.

El hash de la contraseña se genera con:

```bash
php -r 'echo password_hash("la-que-quieras", PASSWORD_BCRYPT, ["cost" => 13]), PHP_EOL;'
```

Traefik publica el puerto de `APP_PORT` en auto-order.code-hive.space. Las migraciones se aplican solas al arrancar el contenedor.

## Configurar Retell

Entra en el panel, pestaña **Retell**. Ahí aparece, con el dominio ya puesto:

- Las instrucciones para pegar en el agente.
- Las tres herramientas con su URL y su cabecera de autenticación.
- La dirección del webhook de llamadas.

Se configura una vez. Si cambias de negocio activo, vuelve a copiar las instrucciones, porque llevan dentro el nombre del negocio.
