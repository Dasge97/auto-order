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
OPENAI_API_KEY=sk-...
RETELL_WEBHOOK_SECRET=la-clave-de-tu-cuenta-de-retell
RETELL_TOOL_TOKEN=una-cadena-larga-que-elijas-tú
```

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

Los tres contenedores son la aplicación, el worker y PostgreSQL. Se levantan con `docker compose up -d --build` desde la carpeta de despliegue.

Antes hay que crear ahí un `.env.local` con:

```
APP_SECRET=cadena-larga-aleatoria
APP_PORT=4141
POSTGRES_PASSWORD=contraseña-larga
ADMIN_USER=admin
ADMIN_PASSWORD_HASH='...'
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5
RETELL_WEBHOOK_SECRET=...
RETELL_TOOL_TOKEN=...
```

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
