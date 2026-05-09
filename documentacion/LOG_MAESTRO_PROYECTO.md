# 👑 Log Maestro - Ecosistema Digital Noxertez (Edición Detallada)

Este documento es la referencia técnica definitiva. Contiene no solo lo que el sistema hace, sino **cómo** lo hace y qué errores hemos superado.

---

# 🏛️ FASE: ATELIER (Abril 2026)

## 🏗️ 1. Arquitectura y "Gotchas" del Entorno
- **Doble Entorno:** El sistema se desarrolla en `C:\mis app de noxertez 2\SahtoutCMS-main\`, pero el servidor **XAMPP** ejecuta los archivos desde `C:\xampp\htdocs\noxertez\`. 
    - *Lección:* Cualquier cambio en el código debe sincronizarse con la carpeta de XAMPP para que tenga efecto inmediato.
- **Sincronización de Imágenes:** Las fotos las sube un programa de escritorio a carpetas locales.
    - *Solución:* Usar siempre `resolverRutaPublica($foto)` en PHP. Esta función convierte rutas físicas (ej: `C:/fotos/...`) en rutas web relativas (`../uploads/...`). **Nunca** usar rutas absolutas de Windows en etiquetas `<img>`.

## 📦 2. Módulo de Inventario (Stock) - El Corazón Visual
- **Reutilización de Lógica:** El sistema de previsualización de imágenes de LinkedIn se basa en el de `stock.php`. 
- **Lógica de Miniaturas:** Se utiliza un atributo `data-foto` dentro de los `<option>` de los selectores. Al cambiar el producto, un script de JS lee ese atributo y actualiza un contenedor `div` con `object-fit: contain`.
- **Mapa de Almacén (5.4):** Utiliza una cuadrícula interactiva. La clave aquí es la carga asíncrona de detalles para no ralentizar la visualización del mapa.

## 📱 3. Redes Sociales (LinkedIn & Pinterest)
- **LinkedIn API:** Las versiones de la API de LinkedIn caducan cada 12 meses. Si falla con error **426**, actualizar la versión (ej: `202604`).
- **Estructura del Post:** JSON jerárquico con `visibility` y `distribution` (`MAIN_FEED`).

## 🤖 4. IA y Automatización
- **Gemini Integration:** Generación de textos creativos por SKU.
- **n8n:** Gestión de procesos en segundo plano.

---

# 🦁 FASE: ANIMAL

## 🖼️ Módulo: Multi-SKU Mockup Management System
Se ha diseñado un sistema de gestión de activos (imágenes y vídeos) con arquitectura **N:M** para permitir que un solo mockup sea utilizado por múltiples artículos/SKUs sin duplicar archivos.

### 💾 Estructura de Datos
- **Tabla `mockups_varios`**: Almacén central de archivos y metadatos.
    - **Novedad**: Integración de columnas de estado social (`publicado_en_instagram`, `publicado_en_facebook`, `publicado_en_tiktok`, `publicado_en_pinterest`, `publicado_en_linkedin`).
    - **Estadísticas**: Implementación de `veces_usado` (contador) y `ultima_vez_usado` (datetime) para tracking de activos.
- **Tabla `mockups_vinculaciones`**: Tabla intermedia que gestiona la relación única entre `mockup_id` y `sku`.

### 🛠️ Innovaciones Técnicas
- **Gestión de Vídeo**: Soporte nativo para carga y previsualización de archivos `.mp4`, `.mov`, etc. Separación automática en directorios `/videos_varios/` y `/mockups_varios/`.
- **Motor de Subida**: Implementación de `XMLHttpRequest` con listener de progreso para subida de carpetas enteras con barra de estado en tiempo real.
- **Optimización de Carga**: El "Banco General" utiliza carga diferida (lazy loading) e inicia vacío para maximizar el rendimiento, activándose solo mediante filtros dinámicos.

### 🎨 UI/UX (Glassmorphism Emerald & Gold)
- **Modales Inteligentes**: Anclados al viewport superior (`fixed`) para evitar scrolls infinitos en listas largas.
- **Editor de Metadatos**: Diseño en dos columnas con etiquetas fijas externas para evitar la pérdida de contexto al editar.
- **Indicadores de Redes**: Los iconos sociales en las tarjetas se iluminan con su color corporativo real (Instagram Rosa, LinkedIn Azul, etc.) basándose en los flags de la base de datos.
- **Flujo de Vinculación**: El botón "Vincular" cambia dinámicamente a "Vinculado" (Verde) si el activo ya tiene SKUs asociados.

## 📈 Memoria de Sesiones Recientes
- **Sesión 1**: Migración de lógica 1:1 a N:M. Creación de la tabla de vinculaciones.
- **Sesión 2**: Refactorización del editor de metadatos y solución de errores de scroll en modales.
- **Sesión 3**: Restauración del sistema de redes sociales. Mapeo de columnas profesionales y creación automática de campos faltantes. Implementación de contadores de uso real.

---

# 🏡 FASE: la mafia de los palts (Mayo 2026)

## 🎙️ 1. Asistente de Voz y Ecosistema n8n
Tras un periodo de inestabilidad en la comunicación entre el navegador y los flujos de automatización, se ha rediseñado el puente de datos.

### 🔌 Solución de Conectividad (El Proxy PHP)
- **Problema:** Errores de red y bloqueos de CORS al intentar llamar a `localhost:5678` directamente desde el JS del navegador.
- **Solución:** Creación de `api/asistente_voz_n8n.php`. Este script actúa como un túnel seguro. El frontend habla con PHP, y PHP habla con n8n vía cURL interno. 
- **Robustez:** El proxy envía el texto simultáneamente en el cuerpo (JSON), en la URL (Query Params) y como formulario, asegurando que n8n lo reciba sin importar la configuración del nodo.

### 💉 Inyección de Código en Base de Datos n8n
- **Hito Técnico:** Se ha desarrollado una técnica de "Inyección SQL" para actualizar la lógica de n8n sin entrar al editor visual.
- **Script:** `inject_n8n.py`. Accede directamente a `database.sqlite` de n8n y modifica el `jsCode` del nodo "Interpretar comando de voz".
- **Mejora:** El nuevo código es "Universal": busca la variable `texto` en `body.texto`, `query.texto` y `params.texto`.

## 📱 2. Optimizaciones de Interfaz (UI/UX)
- **Mockups en Móvil:** Ajuste de Media Queries en `mockups.php` para evitar el desbordamiento de los modales de detalle en pantallas pequeñas.
- **Lógica de Estadísticas:** Inclusión de artículos con sufijo `p01` como "Artículos Base" en los rankings de cobertura de catálogo.

---

# 🚀 FASE: ATELIER 2.0 - ECOSISTEMA INTEGRADO (Mayo 2026 - Actualidad)

## 🌲 1. Inteligencia de Stock y Descuento Automático
Se ha implementado una lógica de descuento de inventario multicanal para evitar descuadres manuales.

- **Deducción de 4 Fuentes:** Al aprobar un pedido, el usuario elige el origen:
    - **Terminado:** Resta de `productos.STOCK`.
    - **Semi-final:** Resta de `productos.STOCK_FISICO`.
    - **Materiales (BOM):** Resta automática de la tabla `materiales` basada en la receta del producto.
    - **Made to Order:** Opción de fabricar bajo pedido sin stock previo.
- **Despiece (BOM):** Implementación de la tabla `despiece_articulos` para gestionar la relación entre productos y sus materias primas.

## 📧 2. Automatización de E-mails & Leads
Centralización de la comunicación entrante para asegurar "Respuesta Cero Perdida".

- **Filtros Inclusivos en n8n:** Rediseño de los flujos de correo (Pedidos, Influencers, Ayuda) para eliminar bloqueos por spam. Ahora se usa un etiquetado de `[¿SPAM?]` permitiendo que todos los leads reales lleguen al CMS.
- **Captura CRM:** Los correos a `influencer@noxertez.com` se procesan automáticamente para extraer nombres, redes sociales y seguidores, guardándolos como prospectos en la base de datos.

## 🤖 3. Estabilización de la IA del Catálogo
- **Taxonomía de Colores:** Estandarización de 12 colores clave para evitar duplicados en el catálogo.
- **IA Gemini Pro:** Refinamiento de los prompts para el análisis de productos y generación de descripciones comerciales optimizadas para SEO.

---
*Última actualización: 9 de Mayo de 2026 - Versión Atelier 2.0 Activa.*


atelier :
# 👑 ATELIER - Documentación Maestra del Ecosistema Noxertez

Este documento representa la memoria técnica y evolutiva del sistema **Atelier** (antes SahtoutCMS). Aquí se registran los procesos, arquitecturas y soluciones desarrolladas durante los últimos meses de colaboración con Antigravity.

---

## 🏛️ 1. FILOSOFÍA Y ARQUITECTURA CORE
**Atelier** no es solo un CMS; es un sistema nervioso centralizado que une diseño premium (Glassmorphism), automatización (n8n) e inteligencia artificial (Gemini).

### 🏗️ Entorno de Desarrollo
- **Dualidad Local:** Desarrollo en `SahtoutCMS-main` y ejecución en `XAMPP/htdocs/noxertez`.
- **Diseño Visual:** Sistema basado en **Glassmorphism**, utilizando paletas de colores curadas (Gold, Emerald, Deep Slate) y tipografías modernas (Inter/Cinzel).

---

## 🎙️ 2. EL CEREBRO: ASISTENTE DE VOZ & IA
El sistema ha evolucionado de una dependencia total de n8n a una arquitectura híbrida más robusta.

- **Evolución Directa:** Se migró el Asistente de Voz para que el navegador hable con un **Proxy PHP** (`api/asistente_voz_n8n.php`), evitando errores de CORS y caídas de conexión.
- **Intérprete de Comandos:** Capacidad para entender órdenes naturales como *"Anótame en el bloc que mañana hay que barnizar"* o *"Busca el stock del reloj caña"*.
- **Inyección SQL en n8n:** Desarrollo de scripts de Python (`apply_n8n_updates.py`) que parchean la lógica de los flujos directamente en la base de datos SQLite de n8n, permitiendo actualizaciones masivas sin usar la interfaz visual.

---

## 📦 3. EL CUERPO: GESTIÓN DE INVENTARIO (MÓDULO 5)
El inventario se ha transformado en un sistema inteligente de 3 niveles:

- **Los 3 Pilares del Stock:**
    1. **Stock Terminado:** Columna `STOCK` en la tabla `productos`. Artículos listos para envío inmediato.
    2. **Stock Semi (Pendiente de acabado):** Columna `STOCK_FISICO` en la tabla `productos`. Artículos que requieren barniz o color final.
    3. **Materiales (Materia Prima):** Tabla independiente `materiales` para el control de maderas, pinturas y herrajes.
- **Mapa de Almacén (5.4):** Interfaz visual interactiva que muestra la ubicación física de cada artículo en estanterías (A, B, C...).
- **Despiece Inteligente (BOM):** Creación de la tabla `despiece_articulos` que vincula cada producto con sus materiales. Permite calcular cuántas unidades se pueden fabricar con la materia prima actual.

---

## 📧 4. LOS NERVIOS: FLUJOS DE CORREO & CRM
Integración total de la comunicación externa con la base de datos de Noxertez.

- **Centralización en n8n:** Creación de flujos (06, 07 y 08) para capturar correos de:
    - **Pedidos:** Transformación automática de emails de clientes en borradores de pedido.
    - **Influencers:** Captura de datos de contacto y redes sociales directamente a la base de datos de leads.
    - **Ayuda/Info:** Sistema de notificaciones en tiempo real para soporte al cliente.
- **Filtros Inclusivos:** Se pasó de una lógica de bloqueo por spam a una de **Equilibrio Inteligente**, donde los correos sospechosos se etiquetan como `[¿SPAM?]` pero nunca se descartan, asegurando que no se pierda ninguna oportunidad de negocio.

---

## 🖼️ 5. LA VOZ: CATÁLOGO, MOCKUPS & MARKETING
Gestión de activos visuales y presencia en redes sociales.

- **Sistema Multi-SKU N:M:** Un solo archivo de imagen o vídeo (mockup) puede estar vinculado a múltiples productos, optimizando el espacio en disco y la gestión de contenido.
- **Sincronización Social:** Seguimiento de publicaciones en LinkedIn, Pinterest, Instagram y TikTok directamente desde el gestor de mockups.
- **Estandarización de SKUs:** Implementación de taxonomía de colores (12 colores estándar) y generación automática de referencias para mantener la integridad del catálogo.

---

## 📈 6. HITOS TÉCNICOS DESTACADOS
1. **Detección Automática de Ubicación:** Al escanear o ver un producto, el sistema te dice en qué estantería y balda se encuentra.
2. **Descuento de Stock en 4 Fuentes:** Al aprobar un pedido, el usuario elige si restar de Terminado, Semi, Materiales o marcar como "Fabricación bajo pedido".
3. **Notificaciones Centralizadas:** El Mega Gestor ahora centraliza avisos de stock bajo, nuevos emails y tareas pendientes del asistente de voz.

---

# 🛡️ SESIÓN: ESTABILIZACIÓN Y CONECTIVIDAD (9 de Mayo de 2026)

## 🌐 1. Resolución de Inactividad del Sitio
Tras un reporte de "Sitio no accesible", se realizó una auditoría técnica profunda detectando problemas en tres niveles:

### 🔄 A. Bucle Infinito en `.htaccess` (Solucionado)
- **Error:** La regla de reescritura genérica `RewriteRule ^(.+)$ pages/$1.php [L]` causaba una recursión infinita si el archivo de destino no existía (ej: al intentar acceder a `/noxertez` buscando el CMS). Esto saturaba los hilos de Apache y colgaba la respuesta del servidor.
- **Solución:** Se añadió una condición de salvaguarda: `RewriteCond %{REQUEST_URI} !\.php$`. Esto detiene la reescritura si la URL ya termina en `.php`, rompiendo el bucle.

### 💀 B. Error Fatal en `news.php` (Solucionado)
- **Error:** La conexión `$site_db` se cerraba manualmente antes de incluir el `footer.php`. Como el footer ahora consulta la base de datos para configurar el chatbot, la página lanzaba un `PHP Fatal error: mysqli object is already closed`.
- **Solución:** Se movió el cierre de la conexión (`$site_db->close()`) al final absoluto del archivo, después de cargar todos los componentes de la interfaz.

### 🛰️ C. El Factor ISP / Pepephone (Diagnóstico)
- **Problema:** A pesar de que el Túnel de Cloudflare y Apache respondían correctamente (confirmado mediante `curl` interno y logs de acceso de dispositivos externos como un iPhone), el usuario no podía acceder desde su red local.
- **Solución:** Activación de **Cloudflare 1.1.1.1 (WARP)**. Esto confirmó que el ISP (Pepephone) estaba interfiriendo con el enrutamiento o bloqueando puertos/DNS, un problema recurrente reportado por el usuario en situaciones de alta carga de red externa (ej: fútbol).

## 💡 Lecciones de la Sesión
- **La Pista del iPhone:** Saber que un dispositivo externo (iPhone) pudo entrar fue la clave definitiva para descartar que el servidor estuviera "caído" y centrar el diagnóstico en la red local del usuario.
- **Robustez del Footer:** En futuras actualizaciones, el footer debería ser agnóstico al estado de la conexión o reabrirla si es necesario, aunque la mejor práctica sigue siendo mantenerla abierta hasta el `render` final.

---
*Última actualización: 9 de Mayo de 2026 - Sitio Restablecido y Optimizado.*

