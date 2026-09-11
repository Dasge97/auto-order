# Auto-order — MVP de demostración comercial

Versión: 0.2 · 11 de septiembre de 2026

Esta especificación sustituye íntegramente el alcance anterior. El repositorio existe; la aplicación todavía no está implementada.

## 1. Objetivo

Enseñar a un posible cliente cómo un agente telefónico toma pedidos utilizando **la carta de su propio negocio** y los hace aparecer en nuestro panel.

El circuito es: cargar carta → extraer menú con IA → revisar → activar demo → llamar → confirmar pedido → verlo en el panel.

No construimos todavía un sistema de gestión del restaurante. Precios, tiempos, horarios, reparto y demás operativa no son requisitos para tomar un pedido de demostración. La instalación comercial se adaptará después a lo que tenga cada cliente.

Auto-order es independiente de autocaller. Reutiliza su experiencia técnica, no su dominio de leads, Odoo o llamadas salientes. La integración de voz es Retell AI, sin integración directa con Twilio.

El producto no está limitado a kebabs: carta, lista de productos o catálogo de un negocio alimentan el mismo flujo. Los nombres de platos y sectores son datos, no código.

## 2. Alcance mínimo

Incluido:

- Crear un negocio de demostración y cargar su carta mediante URL pública, PDF o imágenes.
- Usar un modelo para leerla y generar un menú estructurado propio de ese negocio.
- Mostrar la fuente y una vista previa editable para corregir errores y publicar el menú.
- Agente Retell que consulta ese menú, recoge artículos, cantidades y observaciones, y pide confirmación.
- Guardar un único pedido por llamada y mostrarlo automáticamente en un panel.
- Historial básico de llamadas y pedidos.
- Ticket HTML con vista previa; impresión física opcional para un equipo de demo.
- Login de administrador y aislamiento entre negocios.
- Pruebas de importación, conversación y persistencia.

Fuera de alcance:

- Motor de cotizaciones, reservas de stock, caducidad de presupuestos y validación comercial de precios.
- Configuración obligatoria de horarios, preparación, reparto, mínimos, pagos o disponibilidad.
- Flujos de aceptación automática/manual por restaurante y gestión de cocina o repartidores.
- TPV, ERP, Odoo, cobros, facturación, SMS/WhatsApp, portabilidad y transferencias.
- Portal de clientes, suscripciones, autorregistro, roles complejos o aplicaciones móviles nativas.
- Compatibilidad universal de impresoras o instalación automática por operadora.
- Rastreo periódico de cartas y sincronización automática de cambios.

No convertir estos elementos en requisitos indirectos mediante formularios, base de datos, herramientas del agente o tests.

## 3. Importación de la carta

### Flujo

1. El administrador crea el negocio con su nombre.
2. Añade un enlace público o sube un PDF/una o varias imágenes.
3. El servidor obtiene el contenido y el modelo extrae productos y datos legibles.
4. Se muestra el resultado junto a la fuente, con avisos localizados de extracción dudosa.
5. El administrador corrige lo necesario y pulsa “Publicar menú”.
6. Las llamadas nuevas utilizan ese menú.

La importación se realiza antes de presentar la demo. No se lee la web original durante cada llamada: un fallo de esa web no debe romper una conversación.

Un enlace que requiera login, esté bloqueado o no pueda leerse ofrece como alternativa subir PDF o imágenes. No sortear restricciones ni desarrollar un navegador universal. Si no se extrae ningún producto legible, se explica el fallo y se permite reintentar; no publicar un menú inventado.

### Información extraída

- Nombre del producto.
- Categoría y descripción si aparecen.
- Tamaños, variantes u opciones si aparecen.
- Precio literal y precio numérico/moneda solo cuando sean inequívocos.
- Referencia a página, imagen o fragmento de origen para revisar dudas.

Productos con nombre legible se pueden publicar sin precio, descripción ni opciones. No confundir “dato ausente” con “error que bloquea”.

“Desde 8 €” se conserva como texto, no se convierte en precio fijo. Si una imagen tiene un precio ilegible, se guarda como desconocido y se permite continuar. No deducir menús, ingredientes, suplementos o tiempos que la fuente no explica.

Las variantes pueden conservarse como opciones descriptivas o entradas separadas cuando facilite elegirlas. No crear un configurador obligatorio con reglas de mínimos/máximos por sector.

### Versiones simples

Cada importación genera un borrador. Publicar cambia el menú activo del negocio; una importación fallida no sustituye el anterior. Una llamada queda vinculada a la versión publicada con la que empezó. Los pedidos conservan una copia de los nombres y observaciones.

Un cliente real puede aportar su carta para la presentación. Las cartas ficticias son exclusivamente fixtures de pruebas, no la demostración comercial principal. No publicar sus archivos o información personal en el repositorio.

## 4. Información ausente: comportamiento obligatorio

| Situación | Qué hace el agente |
| --- | --- |
| Carta sin precios | Toma el pedido sin anunciar importe |
| Solo algunos precios disponibles | Puede informar de los conocidos; no presenta una suma parcial como total |
| Precio “desde” o ambiguo | Mantiene el matiz o indica que no tiene un precio cerrado |
| Sin tiempo de preparación | No promete plazo; continúa con el pedido |
| Sin información de reparto u horarios | Reconoce que no dispone de ella y registra lo solicitado sin garantizar el servicio |
| Modificación no descrita | La recoge como petición/observación, sin garantizar que el negocio pueda cumplirla |
| Producto ambiguo | Pregunta cuál de los productos se desea |
| Producto que no aparece | No inventa una entrada; ofrece los disponibles o anota una consulta |
| Pregunta sobre ingredientes/alérgenos desconocidos | No infiere seguridad ni composición; señala que debe consultarse con el negocio |

Para mantener simple el MVP, **no hay cálculo obligatorio de total**. Se pueden leer los precios individuales inequívocos de la carta si se pregunta por ellos. Las modificaciones no heredan un precio inventado.

No exigir nombre, teléfono, dirección, modalidad, código postal o plazo para guardar un pedido de demo. Si se facilitan, se conservan como datos opcionales. El pedido contiene productos identificados, cantidades y observaciones; no es una promesa de entrega ni una aceptación operativa del restaurante.

## 5. Conversación

1. Presentarse como asistente virtual del negocio.
2. Escuchar qué desea pedir y consultar el menú publicado.
3. Identificar artículos y cantidades; preguntar solo lo necesario para entender la petición.
4. Recoger modificaciones y datos que el interlocutor aporte.
5. Repetir el pedido completo en lenguaje natural, incluyendo las peticiones no garantizadas.
6. Si cambia algo, corregirlo y repetir el resumen actualizado.
7. Tras confirmación explícita, enviar el pedido al backend.
8. Solo cuando se haya guardado, comunicar “He registrado tu pedido” y su referencia.

No anunciar “aceptado por cocina”, “en reparto”, “estará en 20 minutos” ni mensajes similares sin soporte. La confirmación aquí significa que el cliente confirma el contenido y el sistema lo registra.

Si el backend falla, no fingir éxito. Si la respuesta es incierta, consultar el pedido de esa llamada antes de reintentar. Colgar sin confirmar no crea un pedido. El análisis posterior de Retell nunca crea pedidos.

MVP: un pedido por llamada. Antes de confirmarlo, el agente mantiene el borrador conversacional. Si se solicita un cambio después de registrarlo, señalar que ya fue registrado y dejar la revisión para el operador, sin crear un duplicado.

## 6. Panel y presentación

Tres áreas principales, sin administración operativa adicional:

- **Negocios y cartas:** crear negocio, subir/enlazar fuente, ver progreso, revisar extracción y publicar.
- **Pedidos:** lista actualizada automáticamente, detalle, referencia, artículos, cantidades, observaciones y datos opcionales.
- **Llamadas:** negocio, estado técnico y pedido relacionado si existe.

La vista de pedido incluye ticket HTML y marca de demostración. Un indicador simple nuevo/visto es suficiente; no se necesita un tablero de preparación, aceptación o entrega.

Panel cómodo en portátil y tablet. Polling cada dos segundos, recuperación después de desconexión y aviso visible si deja de actualizarse. Sonido opcional tras interacción del usuario; la demostración no depende de él.

Guion comercial:

1. Preparar y revisar la carta del posible cliente.
2. Abrir su negocio y el panel.
3. Llamar al número de prueba y pedir productos reconocibles de su carta.
4. Hacer una modificación y confirmar el resumen.
5. Mostrar cómo aparece el pedido; abrir o imprimir el ticket.
6. Explicar que su número habitual y sus sistemas se conectarán al preparar una instalación real.

No enviar esta demo a su TPV ni a su cocina real. La prueba telefónica real y el modo simulado deben distinguirse claramente.

## 7. Arquitectura sencilla

Symfony con controladores, entidades, repositorios y servicios convencionales; Doctrine, PostgreSQL, Twig y JavaScript ligero. Versiones y proveedor/modelo de extracción se eligen al implementar. La extracción debe admitir texto e imágenes, directamente o mediante conversión/OCR según el proveedor elegido.

Componentes:

- Importador: obtiene la fuente, ejecuta extracción y valida el formato de salida.
- Menú: guarda borrador/publicación y responde a consultas del agente.
- Retell: conversación, herramientas HTTP y eventos de llamada.
- Pedidos: validación mínima, guardado transaccional y consulta idempotente.
- Panel: lectura de pedidos e importaciones.
- Impresión opcional: adaptador local para el hardware elegido.

Una tarea persistida de importación con un worker simple evita mantener la petición web abierta durante el procesamiento. Sin microservicios ni infraestructura distribuida. Estado de importación: pendiente, procesando, listo para revisión o error. Reintentar no publica ni duplica productos automáticamente.

Reutilizar de autocaller patrones de autenticación, configuración e idempotencia cuando sean útiles. No copiar Lead, CallAttempt, límites de llamadas salientes o sincronización Odoo. No modificar autocaller.

## 8. Datos mínimos

| Entidad | Contenido |
| --- | --- |
| Business | Nombre, menú activo y asociación de agente/número de demo |
| MenuImport | Negocio, fuentes, estado, errores y resultado de extracción |
| MenuVersion | Negocio, borrador/publicada, fecha |
| MenuItem | Versión, nombre, categoría/descripcion opcionales, opciones descriptivas, precio opcional y referencia de origen |
| Call | Cuenta/provider_call_id único, negocio, versión de menú, estado técnico y teléfonos opcionales |
| Order | Negocio, llamada, referencia, fecha, contacto/modalidad/dirección opcionales, observaciones y marca visto |
| OrderItem | Pedido, item del menú, nombre copiado, cantidad y modificaciones |
| WebhookReceipt | Identificador de evento, procesamiento y hash para deduplicación |
| User | Administrador del panel, credenciales seguras |

Opciones descriptivas pueden almacenarse como JSON validado. Precios desconocidos son null, nunca cero por defecto. No hay OrderQuote, tarifas de entrega, política de aceptación ni horario obligatorio.

Modelo inicial de cantidad: número positivo y unidad textual opcional si está expresada en la carta/petición; no implementar cálculo comercial por peso. El modelo no debe convertir por su cuenta “medio kilo” en unidades ni inventar unidades de venta.

Una llamada tiene como máximo un pedido, protegido por restricción única en base de datos. Un pedido registrado no se modifica por nuevos webhooks ni cambios de carta.

## 9. Herramientas internas del agente

Contratos propuestos de Auto-order, no nombres de endpoints del proveedor:

| Herramienta | Función |
| --- | --- |
| get_menu | Obtiene el menú publicado asociado a la llamada, con campos opcionales |
| create_order | Guarda artículos, cantidades, observaciones y datos aportados después de confirmar el resumen |
| get_current_order | Recupera el pedido de esa llamada, especialmente ante timeout |

El contexto autenticado determina negocio y llamada. No permitir que argumentos libres del modelo seleccionen otro negocio.

create_order verifica únicamente autenticidad, pertenencia de los artículos al menú de la llamada, cantidades válidas, presencia de artículos y confirmación declarada por el agente. La confirmación declarada se comprueba mediante pruebas conversacionales, no se interpreta como evidencia independiente.

Las observaciones no son instrucciones ejecutables ni opciones comerciales garantizadas. El precio, el plazo y los demás datos opcionales no intervienen en la admisión del pedido.

En una transacción se busca/crea el pedido con clave única por llamada. Repetir la petición devuelve el mismo pedido. Si los artículos no se identifican, se pide aclaración: la tolerancia a datos ausentes no autoriza inventar productos.

Errores útiles: MENU_NOT_READY, UNKNOWN_ITEM, INVALID_QUANTITY, CONFIRMATION_REQUIRED y error técnico. No implementar errores de mínimo de compra, caducidad de cotización, cobertura o precio ausente.

## 10. Retell y telefonía de demo

Configurar manualmente en Retell el agente y número disponibles para la demostración. El panel conserva la asociación esperada; guardarla localmente no configura por sí sola el proveedor.

Comprobar capacidad entrante y coste de la llamada antes de presentar. No prometer que Retell proporciona un número español ni que desviar un número existente siempre es gratuito/posible. La telefonía de cada cliente no forma parte de este desarrollo.

Recibir eventos de inicio, final y análisis para seguimiento, con verificación de autenticidad y procesamiento idempotente. Admitir eventos fuera de orden. Resolver el negocio con la asociación de cuenta/agente/número, no con el teléfono del llamante.

Si una herramienta llega antes del webhook de inicio, resolver su contexto autenticado sin depender de ese orden; si no se puede verificar, rechazar la escritura. Consultar el contrato vigente de Retell al implementar.

Referencias técnicas a verificar durante implementación:

- [Llamadas entrantes](https://docs.retellai.com/deploy/inbound-call)
- [Custom functions](https://docs.retellai.com/build/single-multi-prompt/custom-function)
- [Seguridad de webhooks](https://docs.retellai.com/features/secure-webhook)
- [Numeración](https://docs.retellai.com/deploy/purchase-number)

## 11. Impresión opcional

El resultado obligatorio es el pedido en pantalla y su ticket HTML. Campos desconocidos se omiten o se muestran como “no indicado”; no imprimir un total cero ni plazos ficticios.

Para impresión física, seleccionar un modelo y un equipo de demo. Un conector local recibe trabajos desde el servidor y los envía a esa impresora. No desarrollar aún compatibilidad general ni exponer puertos de la impresora a Internet.

Un fallo de impresión no elimina ni bloquea el pedido. No afirmar “impreso” solo porque el trabajo se envió. Si el resultado del envío es incierto, mostrarlo y evitar reimpresiones automáticas que produzcan duplicados; las copias manuales deben identificarse.

No condicionar el resto del MVP a comprar hardware. El ticket es una comanda de demostración, no factura ni sustituto del TPV.

## 12. Robustez que sí necesitamos

- No inventar productos ni datos ausentes.
- Poder revisar una extracción incorrecta antes de la presentación.
- No perder ni duplicar pedidos.
- No mezclar cartas, llamadas o pedidos entre negocios.
- No fingir éxito ante un error de backend.
- No romper una llamada por un campo comercial vacío.
- Mantener el menú publicado disponible aunque la web de origen deje de responder.

Seguridad mínima: login, HTTPS, CSRF del panel, autenticación de herramientas/webhooks, secretos fuera de Git y logs sin datos sensibles completos.

Importador de URLs: solo HTTP/HTTPS público, límites de tamaño/tiempo y redirecciones, bloqueo de destinos locales/privados y metadatos de infraestructura, también tras resolver DNS o redirigir. Sin acceso a sesiones autenticadas.

Archivos: validar tipo, tamaño y número de páginas/imágenes; almacenar fuera de rutas ejecutables. El contenido de la carta es dato no confiable: ignorar instrucciones dirigidas al modelo y validar su JSON antes de guardarlo.

No resolver una extracción bloqueada desactivando estos controles. Ofrecer carga de archivo. Estas protecciones técnicas no son reglas comerciales.

## 13. Pruebas de aceptación

| Caso | Resultado requerido |
| --- | --- |
| Importar carta por URL, PDF e imágenes | Menú revisable con productos identificados |
| Carta completa sin precios | Publicar y tomar pedido sin importe |
| Precios parciales/“desde” | No inventar cifras ni anunciar total incompleto |
| Sin tiempos, horarios o reparto | Pedido registrable; no prometer lo desconocido |
| Extracción dudosa | Aviso localizado y corrección simple |
| URL inaccesible o documento ilegible | Error claro y alternativa, sin menú inventado |
| Reimportación fallida | El menú publicado anterior sigue funcionando |
| Modificación no descrita | Observación visible sin garantía falsa |
| Nombre ambiguo de producto | El agente pregunta y recoge el correcto |
| Corrección antes de confirmar | Resumen y pedido reflejan la última versión |
| Cuelga sin confirmar | Ningún pedido creado desde postanálisis |
| Doble confirmación o timeout tras guardado | Un único pedido recuperable |
| Eventos duplicados/desordenados | Sin pedidos dobles ni estados regresivos |
| Datos opcionales ausentes | Panel y ticket funcionan, sin cero/plazo ficticio |
| Intento de acceder a otro negocio | Denegado |
| Cambio de menú durante llamada | Se conserva la versión de esa conversación |
| Backend caído | El agente no afirma haber guardado |
| Panel se reconecta | Recupera los pedidos persistidos |
| URL privada o instrucciones maliciosas en carta | Bloqueo/ignorado según corresponda |
| Impresora falla, si se habilita | Pedido conservado y fallo visible |

Fixtures sintéticos cubren varios formatos y sectores, sin convertirse en catálogo obligatorio de presentación.

Objetivo de demo: pedido visible en pocos segundos después de guardarse. Registrar tiempos reales; no afirmar pruebas pasadas ni SLA sin medir. Tests automatizados con proveedores simulados; llamada real controlada como aceptación separada.

## 14. Plan de implementación

1. Symfony, persistencia, login y separación entre negocios.
2. Importación URL/PDF/imágenes con modelo, vista previa y publicación.
3. Menú consultable y pedidos con validación mínima e idempotencia.
4. Panel actualizado y ticket HTML.
5. Agente y herramientas Retell; eventos de seguimiento.
6. Preparar la carta real de un posible cliente y ensayar la llamada completa.
7. Impresión física opcional después de elegir hardware.

No introducir un motor de gestión comercial para completar estas fases. Proveedor/modelo de extracción, número disponible y hosting se concretan al implementar; no requieren diseñar ahora precios, reparto o preparación.

## 15. Regla de alcance para el desarrollo

Si una decisión hace que la demo deje de funcionar porque la carta no tiene precios, ingredientes, tiempos o información operativa, contradice este documento.

“Bien montado” significa: entiende su carta, reconoce lo que desconoce, recoge el pedido correcto, lo confirma y lo muestra sin perderlo ni duplicarlo.

El siguiente proyecto, después de captar al cliente, será adaptar la instalación a su operativa. No adelantar ese trabajo al MVP.
