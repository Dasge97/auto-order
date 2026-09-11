# Auto-order

Demo de atención telefónica con IA. El agente coge el teléfono, toma un pedido con productos de la carta del propio negocio y lo deja apuntado en un panel.

El alcance completo está en [docs/auto-order-mvp.md](docs/auto-order-mvp.md).

## Stack

- PHP 8.4 con Symfony 8.1, Doctrine ORM y Doctrine Migrations.
- PostgreSQL 17.
- Twig y JavaScript sencillo, sin compilación de assets.
- Apache dentro de la imagen, sirviendo `public/`.

## Deployment

- mode: multi-container
- public_service: `app`
- internal_port: `80`
- host_port: `4141`
- healthcheck_path: `/login`
- dominio: `auto-order.code-hive.space`

Tres contenedores:

| Servicio | Qué hace |
| --- | --- |
| `app` | Panel, herramientas del agente y webhook de Retell |
| `worker` | Lee las cartas con el modelo, fuera de la petición web |
| `database` | PostgreSQL |

Hay un worker porque leer una carta tarda entre diez segundos y un minuto, y no puede bloquear la pantalla mientras tanto. El trabajo se encola con Symfony Messenger sobre la propia base de datos, sin Redis ni ningún servicio extra.

## Servicios externos

- **auth2api** (`auth2api.code-hive.space`): lee las cartas. Se le habla en el formato de chat de OpenAI.
- **Retell AI**: la llamada telefónica. Llama a tres herramientas HTTP de este proyecto y manda eventos de llamada al webhook.

## Datos que hay que conservar

Dos volúmenes con nombre fijo, que un redespliegue no toca:

- `auto_order_database`: negocios, cartas, llamadas y pedidos.
- `auto_order_uploads`: los PDF e imágenes de carta que se han subido.
