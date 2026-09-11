# Auto-order

Demo de atención telefónica con IA para captar clientes. El agente coge el teléfono, toma un pedido con productos de la carta del propio negocio y lo deja apuntado en un panel.

## Flujo

Subir la carta en PDF, en imágenes o pegando el texto → extraerla con IA → corregirla y publicarla → recibir la llamada con Retell → confirmar el pedido → verlo en el panel.

No es solo para kebabs. Sirve para cualquier negocio con una carta o una lista de productos.

## Lo esencial

- La carta del cliente es el punto de partida, no hay que montar un catálogo a mano.
- Para la demo basta con que los productos se parezcan a los suyos.
- Si falta un dato, el agente no se lo inventa ni bloquea el pedido.
- Confirmación explícita, y ni un pedido perdido ni duplicado.
- Hay total cuando todos los productos del pedido tienen precio; si no, no hay total.
- Ticket en pantalla, imprimible desde el navegador.
- Sin TPV, sin cocina, sin reparto y sin horarios.

## Documentación

La [especificación del MVP](docs/auto-order-mvp.md) recoge el alcance, las decisiones y el orden de trabajo.

## Estado

Solo documentación. La aplicación todavía no está implementada.

## Base técnica

Symfony, Doctrine, PostgreSQL y Twig. Extracción de la carta con OpenAI con visión. Voz con Retell AI. Se despliega en auto-order.code-hive.space.

Proyecto independiente de autocaller. Sin Odoo y sin integración directa con Twilio.

La telefonía habitual del cliente, su impresora y su sistema se estudian al preparar su instalación, después de la demostración. No son parte de este MVP.
