# Auto-order

Plataforma de atención telefónica con IA que convierte llamadas en pedidos estructurados para pequeños negocios con catálogo.

## Estado

Repositorio inicial de documentación. La aplicación todavía no está implementada.

La especificación se conserva como documento de diseño original; sus referencias a la creación futura del repositorio corresponden al momento en que se redactó. El repositorio ya está creado y contiene dicha especificación.

## Documentación

Consulta la [especificación completa del MVP](docs/auto-order-mvp.md): alcance, reglas de negocio, arquitectura, modelo de datos, herramientas Retell, impresión, seguridad, pruebas y plan de implementación.

## Objetivo del MVP

Demostrar una llamada real atendida por Retell AI que termina en un pedido validado y persistido, visible en el panel del negocio. La impresión física es una extensión opcional para un equipo concreto.

- Núcleo genérico: catálogo, variantes, opciones, pedidos y entrega al local.
- Dos negocios ficticios: restauración y floristería.
- Arquitectura propuesta: Symfony, Doctrine, PostgreSQL y Twig.
- Proyecto independiente de autocaller, sin dependencia de Odoo ni integración directa con Twilio.
- Integraciones con el número existente, TPV e impresoras de cada cliente: posteriores y específicas de cada instalación.

## Siguiente paso

Implementar por fases siguiendo la sección 15 de la especificación. No se deben efectuar compras, llamadas reales ni cambios en servicios externos como parte de las pruebas automatizadas.
