=== Brotherhood News AI ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Lee feeds RSS, filtra por palabras clave, reescribe las noticias con la API de Claude (Anthropic) y las guarda como borrador para revisión editorial.

== Cómo funciona ==

1. Cada X minutos el plugin lee las fuentes RSS activas y toma solo el título y el resumen de cada noticia (sin scraping).
2. Las noticias pasan por el filtro de palabras clave (inclusión / exclusión, globales y por fuente).
3. Las noticias aprobadas entran en una cola y se envían a Claude, que redacta un titular y un texto nuevos sin inventar datos.
4. Se crea la entrada como "Borrador" o "Pendiente de revisión" (nunca se publica sola), con la atribución "Con información original de [Medio]".
5. Se avisa a los editores por email y/o Telegram. Si faltan nombres o cifras del original, el aviso y la entrada muestran una advertencia.

Incluye control de duplicados, límite diario, reintentos automáticos, panel con estadísticas y registro.

== Instalación ==

1. Sube la carpeta `brotherhood-news-ai` a `wp-content/plugins/` y activa el plugin.
2. Noticias IA ➔ Ajustes: añade la clave API de Anthropic, elige el modelo y revisa el prompt. Pulsa "Probar Claude".
3. Noticias IA ➔ Fuentes RSS: revisa las URL de los feeds, asigna la categoría de WordPress de cada fuente y actívalas.
4. Configura las alertas (email y/o Telegram) y pulsa "Probar Telegram".
5. Noticias IA ➔ Panel ➔ "Ejecutar ahora" para la primera prueba.

Opcional: para no guardar la clave en la base de datos, añade a `wp-config.php`:

    define( 'BHNA_ANTHROPIC_API_KEY', 'sk-ant-...' );

== Cron real en Hostinger (recomendado) ==

WP-Cron solo se ejecuta cuando alguien visita la web. Para que el sistema funcione 24/7:

1. En `wp-config.php` añade: `define( 'DISABLE_WP_CRON', true );`
2. En hPanel ➔ Avanzado ➔ Cron Jobs, crea una tarea cada 5 minutos con el comando:

    wget -q -O - "https://TU-DOMINIO.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1

== Telegram ==

1. Crea un bot con @BotFather y copia el token.
2. Añade el bot al grupo privado de editores.
3. Obtén el ID del grupo (empieza por -100…) y guárdalo en Ajustes.

== Amazon ==

1. Noticias IA ➔ Ajustes de Amazon: activa el módulo y escribe tu ID de afiliado de Amazon.com (…-20). El de Amazon.es (…-21) puede añadirse más adelante.
2. Noticias IA ➔ Productos Amazon ➔ Añadir producto: pega el ASIN o el enlace, elige la tienda, una descripción breve, las categorías y palabras clave relacionadas.
3. Cada artículo muestra automáticamente los productos relacionados con sus categorías o palabras clave. Desde la caja "Amazon y redes sociales" del artículo se pueden elegir productos a mano, cambiar la tienda o desactivar los productos.
4. Tienda: noticias de España ➔ Amazon.es; EE. UU. y Cuba ➔ Amazon.com. Mientras no haya ID de Amazon.es, se usa Amazon.com para no perder comisiones.
5. Ofertas: marca un producto como "Oferta" (con etiqueta y fecha de fin opcionales). Se muestran con el shortcode [bhna_ofertas] o con el widget "Ofertas de Amazon". En Ajustes de Amazon hay un botón para crear la página "Ofertas".

Sin la API de Amazon no se muestran precios ni imágenes oficiales (Amazon solo los permite mediante su API). Todos los enlaces llevan rel="sponsored" y el aviso de afiliación.
Los productos se leen de la base de datos y se guardan en caché: la web no consulta Amazon al cargar las páginas.

== Redes sociales ==

Al publicar un artículo (nunca los borradores) se comparte automáticamente unos segundos después:

* X (Twitter): crea una app en el portal de desarrolladores de X con permisos "Read and write" y copia API Key, API Key Secret, Access Token y Access Token Secret en Noticias IA ➔ Redes sociales. Pulsa "Verificar X". El plan gratuito de X tiene un límite mensual de publicaciones.
* Canal público de Telegram: añade el mismo bot como administrador del canal, escribe el ID del canal (@canal o -100…) y pulsa "Verificar canal de Telegram".
* Facebook y LinkedIn quedan preparados en el panel para conectarlos más adelante.

Si una publicación falla se reintenta dos veces (cada 15 minutos) y después se avisa al administrador.

== Notas ==

* Las URL de feeds incluidas por defecto deben verificarse en la instalación. Las fuentes sin URL quedan inactivas hasta que se completen.
* El coste mostrado en el panel es una estimación con las tarifas públicas de Anthropic.
* Al eliminar el plugin, los datos solo se borran si se activa la opción en Ajustes. Las entradas creadas nunca se borran.
