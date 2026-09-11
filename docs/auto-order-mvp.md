# Auto-order — MVP de demostración comercial

Versión: 0.3 · 11 de septiembre de 2026

Esta versión recoge las decisiones tomadas el 11 de septiembre de 2026 y sustituye a la anterior. El repositorio existe; la aplicación todavía no está implementada.

## 1. Para qué sirve

Para enseñar a un posible cliente que un agente telefónico coge el teléfono, toma un pedido con productos de su carta y lo deja apuntado en un panel.

No es un sistema de gestión de restaurante. No conecta con su TPV ni con su cocina. Todo eso se le cuenta como lo que vendría después, si contrata.

La carta no tiene que estar importada a la perfección. Basta con que los productos que el cliente oiga por teléfono se parezcan a los suyos.

## 2. Qué incluye

- Crear un negocio y cargar su carta subiendo un PDF, subiendo imágenes o pegando el texto.
- Leer la carta con un modelo de OpenAI con visión y sacar una lista de productos.
- Una tabla editable para corregir los productos a mano y publicar el menú.
- Marcar un negocio como activo: las llamadas que entren usan su carta.
- Agente de Retell que toma un pedido, pregunta nombre y si recoge o se lo llevan, y pide confirmación.
- Un pedido por llamada, guardado y visible en el panel sin recargar.
- Lista simple de llamadas, para saber qué ha pasado cuando algo falle.
- Ticket en pantalla, imprimible desde el navegador.
- Botón para simular una llamada escribiendo el pedido, sin telefonear.
- Login con usuario y contraseña fijos.

## 3. Qué no incluye

- Importar la carta desde una URL.
- Impresora de tickets conectada al servidor.
- Horarios, tiempos de preparación, zonas de reparto, mínimos de compra y pagos.
- Gestión de cocina, estados de preparación y repartidores.
- TPV, ERP, Odoo, facturación, SMS y WhatsApp.
- Portal para el cliente final, altas de usuarios y aplicación móvil.
- Idiomas distintos del castellano.

Nada de la lista puede colarse por la puerta de atrás en formularios, base de datos o herramientas del agente.

## 4. Cargar la carta

1. Creas el negocio con su nombre.
2. Subes un PDF, subes una o varias imágenes, o pegas el texto de la carta.
3. El modelo de OpenAI lee lo que has subido y devuelve una lista de productos.
4. Ves la lista en una tabla editable, junto al archivo original.
5. Corriges lo que quieras y pulsas "Publicar menú".
6. Las llamadas nuevas usan ese menú.

La carta se carga antes de la demo, nunca durante la llamada.

De cada producto se guarda el nombre, y además la categoría, la descripción y el precio si aparecen. El nombre es lo único obligatorio. Un producto sin precio se publica igual.

Un precio del tipo "desde 8 €" se guarda como texto, no como número. Un precio ilegible se deja vacío. El modelo no debe inventar productos, ingredientes ni precios que no estén en la carta.

Si no sale ningún producto legible, se muestra el fallo y puedes reintentar o pegar el texto a mano.

Publicar una importación nueva cambia el menú activo. Una importación fallida no borra el menú anterior. Una llamada se queda con la versión del menú que había cuando empezó.

## 5. Qué hace el agente cuando falta información

| Situación | Qué hace |
| --- | --- |
| Carta sin precios | Toma el pedido sin decir importes |
| Solo algunos productos con precio | Dice los que sabe; no da un total incompleto |
| Precio "desde" o dudoso | No lo trata como precio cerrado |
| No hay tiempo de preparación | No promete plazo |
| No hay información de reparto | Apunta el pedido sin garantizar el servicio |
| Piden una modificación que la carta no contempla | La apunta como observación, sin prometer que se pueda |
| Nombre de producto ambiguo | Pregunta cuál de ellos quiere |
| Producto que no está en la carta | No se lo inventa; ofrece los que hay |
| Preguntan por ingredientes o alérgenos | Dice que hay que consultarlo con el negocio |

## 6. Precios y total

Si todos los productos del pedido tienen un precio numérico claro, se calcula el total. El agente puede decirlo y aparece en el ticket.

Si a algún producto le falta el precio, no hay total. Ni el agente lo dice ni el ticket lo muestra. Nunca se muestra un total de cero ni una suma parcial presentada como total.

Una modificación pedida por teléfono no cambia el precio.

## 7. Cómo va la llamada

1. Se presenta como asistente del negocio.
2. Escucha el pedido y consulta el menú publicado.
3. Identifica productos y cantidades.
4. Apunta las modificaciones que le digan.
5. Pregunta el nombre y si lo recoge o se lo llevan. Si no se lo dan, sigue adelante.
6. Repite el pedido entero y espera confirmación.
7. Si le corrigen algo, lo cambia y vuelve a repetir.
8. Tras la confirmación, guarda el pedido.
9. Solo cuando está guardado dice que lo ha registrado, con su referencia.

No dice que la cocina lo ha aceptado, ni que llegará en veinte minutos, ni nada parecido.

Si el guardado falla, no finge que ha ido bien. Si no sabe si se guardó, consulta el pedido de esa llamada antes de reintentar.

Colgar sin confirmar no crea pedido. El análisis que Retell envía al terminar la llamada nunca crea pedidos.

Un pedido por llamada. Si piden un cambio después de registrarlo, dice que ya está registrado y que lo revisará una persona.

## 8. Panel

Cuatro pantallas:

- **Negocios:** crear, subir carta, corregir la tabla de productos, publicar y marcar cuál está activo.
- **Pedidos:** lista que se actualiza sola, con detalle, referencia, productos, cantidades, observaciones, nombre, si recoge o se lo llevan, y total si lo hay.
- **Llamadas:** lista simple con negocio, estado y el pedido si lo hubo.
- **Simular llamada:** escribes un pedido y se guarda como si viniera del agente.

La lista de pedidos se refresca cada dos segundos. Si deja de refrescarse, se ve un aviso. Se ve bien en portátil y en tablet.

El pedido tiene un ticket en pantalla, marcado como demostración, que se imprime desde el navegador.

Los pedidos hechos con el simulador se distinguen a simple vista de los que vienen de una llamada real.

### Cómo se enseña

1. Cargas su carta antes de la reunión y corriges lo que haga falta.
2. Marcas su negocio como activo y abres el panel.
3. Llamas al número de prueba y pides cosas de su carta.
4. Pides una modificación y confirmas.
5. Enseñas el pedido en el panel y abres el ticket.
6. Le cuentas que el siguiente paso sería entrar en su sistema y su impresora.

## 9. Cómo está montado

Symfony con controladores, entidades, repositorios y servicios normales. Doctrine, PostgreSQL, Twig y JavaScript sencillo. Versión de PHP y de Symfony, las actuales al empezar.

Partes:

- **Importador:** recibe el archivo o el texto, llama a OpenAI y comprueba que la respuesta tiene el formato esperado.
- **Menú:** guarda borrador y publicación, y responde a las consultas del agente.
- **Retell:** conversación, herramientas HTTP y eventos de llamada.
- **Pedidos:** validación mínima, guardado en una transacción y consulta repetible.
- **Panel:** lectura de pedidos, llamadas e importaciones.

La importación se guarda como tarea y la procesa un worker sencillo, para no dejar colgada la petición web. Estados: pendiente, procesando, lista para revisar, error. Reintentar no publica solo ni duplica productos.

De autocaller se pueden copiar patrones de login y de configuración. No se copia Lead, CallAttempt, llamadas salientes ni nada de Odoo. Autocaller no se toca.

Se despliega en el servidor code-hive, en auto-order.code-hive.space. Retell necesita llegar al backend por HTTPS desde Internet.

## 10. Datos

| Entidad | Contenido |
| --- | --- |
| Business | Nombre, menú activo, si es el negocio activo para la demo |
| MenuImport | Negocio, archivos o texto de origen, estado, errores, resultado |
| MenuVersion | Negocio, borrador o publicada, fecha |
| MenuItem | Versión, nombre, categoría y descripción opcionales, opciones descriptivas, precio opcional |
| Call | Identificador de llamada de Retell único, negocio, versión de menú, estado, si es simulada |
| Order | Negocio, llamada, referencia, fecha, nombre, recoger o domicilio, observaciones, total opcional, marca de visto |
| OrderItem | Pedido, producto del menú, nombre copiado, cantidad, modificaciones, precio copiado opcional |
| WebhookReceipt | Identificador del evento, si se procesó |
| User | No hace falta tabla: usuario y contraseña van en la configuración del servidor |

Las opciones descriptivas se guardan como JSON. Un precio desconocido es null, nunca cero.

La cantidad es un número positivo. El modelo no convierte "medio kilo" en unidades por su cuenta.

Una llamada tiene como mucho un pedido, y lo garantiza una restricción única en la base de datos. Un pedido guardado no cambia porque lleguen más eventos ni porque se publique otra carta.

## 11. Herramientas del agente

| Herramienta | Qué hace |
| --- | --- |
| get_menu | Devuelve el menú publicado del negocio de esa llamada |
| create_order | Guarda productos, cantidades, observaciones, nombre y si recoge o se lo llevan |
| get_current_order | Devuelve el pedido de esa llamada, sobre todo si hubo un timeout |

El negocio y la llamada salen del contexto autenticado. El modelo no puede elegir otro negocio pasándolo como argumento.

create_order comprueba que la petición es auténtica, que los productos son del menú de esa llamada, que las cantidades valen, que hay al menos un producto y que el agente declara haber confirmado.

Guarda dentro de una transacción, con clave única por llamada. Repetir la petición devuelve el mismo pedido.

Si no reconoce los productos, pide aclaración en vez de inventarlos.

Errores: MENU_NOT_READY, UNKNOWN_ITEM, INVALID_QUANTITY, CONFIRMATION_REQUIRED y error técnico.

## 12. Retell

El agente y el número ya existen en la cuenta de Retell y se configuran a mano allí. El panel guarda la asociación que espera, pero guardarla no configura Retell.

El negocio se resuelve por cuál está marcado como activo, nunca por el teléfono de quien llama.

Se reciben los eventos de inicio, fin y análisis de la llamada. Se comprueba que son auténticos. Un evento repetido no hace nada dos veces. Pueden llegar desordenados.

Si una herramienta llega antes del evento de inicio, se resuelve igual. Si no se puede verificar quién llama, se rechaza la escritura.

Enlaces a comprobar al implementar:

- [Llamadas entrantes](https://docs.retellai.com/deploy/inbound-call)
- [Custom functions](https://docs.retellai.com/build/single-multi-prompt/custom-function)
- [Seguridad de webhooks](https://docs.retellai.com/features/secure-webhook)

## 13. Lo que sí tiene que aguantar

- No inventar productos ni datos que no estén en la carta.
- Poder corregir la carta a mano antes de la demo.
- No perder ni duplicar pedidos.
- No mezclar cartas ni pedidos entre negocios.
- No decir que ha guardado un pedido si ha fallado.
- No cortar una llamada porque falte un precio o un dato.
- Seguir funcionando con el menú publicado aunque OpenAI no responda.

Seguridad: login, HTTPS, CSRF en el panel, autenticación de las herramientas y los webhooks, secretos fuera de git, y logs sin datos personales completos.

Archivos subidos: se comprueba tipo, tamaño y número de páginas o imágenes, y se guardan fuera de rutas ejecutables.

El contenido de la carta no es de fiar. Si trae texto que parece una instrucción para el modelo, se ignora. La respuesta del modelo se valida antes de guardarla.

## 14. Pruebas

| Caso | Qué tiene que pasar |
| --- | --- |
| Cargar carta por PDF, por imágenes y pegando texto | Sale una lista de productos revisable |
| Carta sin ningún precio | Se publica y se toma el pedido sin importes |
| Carta con precios a medias | No se inventan cifras ni se da un total incompleto |
| Documento ilegible | Error claro, sin menú inventado |
| Importación fallida | El menú publicado anterior sigue sirviendo |
| Modificación no prevista | Se ve como observación, sin prometer nada |
| Nombre de producto ambiguo | El agente pregunta y coge el correcto |
| Corrección antes de confirmar | El resumen y el pedido reflejan lo último |
| Cuelga sin confirmar | No se crea pedido |
| Doble confirmación o timeout tras guardar | Un solo pedido, recuperable |
| Eventos repetidos o desordenados | Ni pedidos dobles ni estados que retroceden |
| Faltan nombre o modalidad | El panel y el ticket funcionan igual |
| Todos los productos con precio | El total cuadra |
| Algún producto sin precio | No aparece total en ninguna parte |
| Se publica otra carta durante una llamada | La llamada sigue con su versión |
| Backend caído | El agente no dice que ha guardado |
| El panel se reconecta | Vuelven a verse los pedidos guardados |
| Carta con texto que intenta dar instrucciones al modelo | Se ignora |

Las pruebas automáticas usan OpenAI y Retell simulados. Una llamada real de verdad se prueba aparte, a mano.

Cartas de prueba inventadas, solo para los tests. En una demo se usa la carta real del posible cliente, y sus archivos no se suben al repositorio.

## 15. Orden de trabajo

1. Symfony, base de datos y login.
2. Negocios, subida de PDF/imágenes/texto, extracción con OpenAI, tabla editable y publicar.
3. Menú consultable y guardado de pedidos con validación mínima.
4. Panel de pedidos, ticket y simulador de llamada.
5. Agente y herramientas de Retell, más los eventos.
6. Lista de llamadas.
7. Ensayo con la carta real de un posible cliente.

## 16. Regla para decidir dudas

Si algo hace que la demo deje de funcionar porque la carta no trae precios, ingredientes, tiempos o información del negocio, está mal.

Que esté bien hecho significa: entiende su carta más o menos, reconoce lo que no sabe, apunta bien el pedido, lo confirma y no lo pierde.

Adaptar la instalación a la operativa real del cliente es el proyecto siguiente, no este.
