# Auto-order

Demo de atención telefónica con IA para captar clientes: el agente lee **la carta del propio negocio**, toma el pedido y lo muestra en un panel.

## Flujo

Cargar URL, PDF o imágenes → extraer menú con IA → revisar y publicar → recibir llamada con Retell → confirmar pedido → mostrarlo en el panel.

No es exclusivamente para kebabs. La misma base sirve para negocios con cartas o listas de productos distintas.

## Lo esencial

- La carta del cliente es el punto de partida; no hay que montar un catálogo manual desde cero.
- Precios, tiempos, horarios y reparto son información opcional.
- Si falta un dato, el agente no lo inventa ni bloquea el pedido por ello.
- Productos identificados, cantidades y observaciones bastan para registrar la demo.
- Confirmación explícita y persistencia sin pedidos perdidos o duplicados.
- Panel y ticket HTML; impresión física opcional.
- Sin cotizaciones, mínimos de compra, gestión de cocina ni integraciones con TPV.

## Documentación

La [especificación vigente del MVP](docs/auto-order-mvp.md) define importación de carta, conversación, datos mínimos, herramientas, pruebas y fases de implementación. Sustituye el alcance anterior.

## Estado y base técnica

Solo documentación; aplicación todavía no implementada.

Symfony, Doctrine, PostgreSQL, Twig y Retell AI. Proyecto independiente de autocaller, sin Odoo ni integración directa con Twilio. Modelo/proveedor de extracción a elegir al implementar.

La telefonía habitual, impresora y sistema de cada cliente se estudiarán al preparar su instalación, después de la demostración comercial. No son requisitos de este MVP.
