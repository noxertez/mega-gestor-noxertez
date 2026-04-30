# PROMPT ANTIGRAVITY — Sistema de subida y gestión de Mockups Varios
# Mega Gestor Noxertez

---

## CONTEXTO DEL PROYECTO

Estoy desarrollando el Mega Gestor Noxertez, un sistema de gestión artesanal en PHP + MySQL corriendo en XAMPP local. Las páginas están en /Sahtout/pages/ y las APIs en /Sahtout/api/. La conexión a BD se hace con require_once '../api/config.php' y la función conectar() que devuelve un PDO.

Todas las páginas usan este patrón al inicio:
```php
define('ALLOWED_ACCESS', true);
require_once '../includes/header.php';
```

El header ya carga: sesión, FontAwesome 6, fuentes Cinzel/UnifrakturCook, y el CSS de la página según $page_class. El estilo visual del gestor es oscuro (#0a0a0a) con acento dorado (#d4af37).

---

## PASO 1 — CREAR LA TABLA EN MYSQL

Ejecuta este SQL para crear la tabla mockups_varios:

```sql
CREATE TABLE IF NOT EXISTS mockups_varios (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  archivo             VARCHAR(255) NOT NULL,
  ruta                VARCHAR(500) NOT NULL,
  tipo                ENUM('imagen','video') DEFAULT 'imagen',
  estancia            VARCHAR(100) DEFAULT NULL,
  luz                 VARCHAR(100) DEFAULT NULL,
  estilo              VARCHAR(100) DEFAULT NULL,
  marca               ENUM('NOXERTEZ','CANDLEHOLDER','ZEN') DEFAULT NULL,
  formato             ENUM('cuadrado','vertical','horizontal') DEFAULT NULL,
  color_dominante     VARCHAR(100) DEFAULT NULL,
  temporada           VARCHAR(100) DEFAULT NULL,
  calidad             ENUM('publicar','revisar','descartar') DEFAULT 'revisar',
  favorito            TINYINT DEFAULT 0,
  notas               TEXT DEFAULT NULL,
  veces_usado         INT DEFAULT 0,
  ultima_vez_usado    DATETIME DEFAULT NULL,
  publicado_linkedin  DATETIME DEFAULT NULL,
  publicado_pinterest DATETIME DEFAULT NULL,
  publicado_instagram DATETIME DEFAULT NULL,
  asignado_a_sku      VARCHAR(100) DEFAULT NULL,
  fecha_subida        DATETIME DEFAULT NOW()
);
```

---

## PASO 2 — CREAR API: /Sahtout/api/mockups_varios.php

Crea una API REST que maneje estas acciones vía GET y POST:

**GET ?accion=listar** — Devuelve todos los mockups con filtros opcionales:
- ?estancia=cocina
- ?marca=NOXERTEZ
- ?calidad=publicar
- ?tipo=video
- ?favorito=1
- ?buscar=texto (busca en archivo, estancia, estilo, luz, notas)
- ?sin_linkedin=1 (publicado_linkedin IS NULL)
- ?sin_pinterest=1

**GET ?accion=uno&id=X** — Devuelve un mockup por id

**POST accion=subir** — Recibe archivos múltiples ($_FILES['archivos']), los mueve a /Sahtout/uploads/mockups_varios/ y parsea el nombre de cada archivo para extraer automáticamente estancia, luz y estilo.

El nombre de archivo sigue el formato: estancia_luz_estilo_mockup-N.ext
Ejemplo: cocina_luz-natural-suave_bohemio_mockup-2.jpg
- Parte 0 → estancia
- Parte 1 → luz
- Parte 2 → estilo
- El tipo se detecta por extensión: jpg/jpeg/png/webp/gif = imagen, mp4/mov/webm = video
- El formato se detecta con getimagesize(): ancho > alto = horizontal, alto > ancho = vertical, iguales = cuadrado (solo para imágenes; vídeos dejar NULL)
- Inserta cada archivo en la tabla mockups_varios con los campos parseados
- Devuelve JSON con array de resultados: {archivo, ok, id, error?}

**POST accion=editar** — Actualiza campos de un mockup (id obligatorio). Campos editables: marca, estancia, luz, estilo, formato, color_dominante, temporada, calidad, favorito, notas, asignado_a_sku

**POST accion=eliminar** — Elimina registro de BD y el archivo físico. Recibe id.

**POST accion=marcar_red** — Recibe id y red (linkedin/pinterest/instagram). Actualiza el campo publicado_[red] = NOW() y veces_usado = veces_usado + 1, ultima_vez_usado = NOW()

Todos los POST devuelven JSON {ok: true/false, msg: '...'}

---

## PASO 3 — MODIFICAR /Sahtout/pages/mockups.php

La página mockups.php ya existe. Añade una nueva sección al principio de la página (antes de los tabs existentes) llamada "Subir mockups" con este funcionamiento:

### Panel de subida (siempre visible, colapsable):

```
[ SUBIR MOCKUPS AL BANCO GENERAL ]

Selecciona una carpeta completa de tu ordenador:
[ Elegir carpeta ]   →  input type="file" webkitdirectory multiple accept="image/*,video/*"

Vista previa antes de subir:
- Lista de archivos detectados con nombre y tipo (imagen/video)
- Campos parseados automáticamente desde el nombre (estancia, luz, estilo)
- Si el nombre no sigue el formato, mostrar advertencia amarilla pero permitir subir igual

[ SUBIR TODOS (X archivos) ]   [ Cancelar ]

Barra de progreso durante la subida (archivo por archivo, con contador X/Y)
Resumen final: X subidos correctamente, Y errores
```

### Filtros del banco general (Tab 2 que ya existe):

Añade estos filtros encima del grid de tarjetas:
- Selector: Todas las marcas / NOXERTEZ / CANDLEHOLDER / ZEN
- Selector: Todas las estancias (se rellena dinámicamente con los valores distintos de la BD)
- Selector: Todos los estilos (igual, dinámico)
- Selector: Calidad (Todas / publicar / revisar / descartar)
- Selector: Redes (Todos / Sin LinkedIn / Sin Pinterest / Solo favoritos)
- Buscador de texto libre
- Botón: Limpiar filtros

### Tarjetas del banco general:

Cada tarjeta muestra:
- Miniatura (imagen) o preview oscuro con icono play (vídeo)
- Badge de marca (dorado para NOXERTEZ, azul para CANDLEHOLDER, verde para ZEN)
- Badge de calidad (verde=publicar, amarillo=revisar, rojo=descartar)
- Corazón para favorito (toggle)
- Tags: estancia, luz, estilo
- Iconos pequeños si ya fue publicado en LinkedIn (in) o Pinterest (P)
- "Usado X veces"
- Botones: Ver (lightbox) | Editar | Asignar a artículo | Eliminar

### Modal de edición inline:

Al pulsar "Editar" en una tarjeta, abre un modal con formulario para editar todos los campos:
- marca (select: NOXERTEZ / CANDLEHOLDER / ZEN)
- estancia (input text)
- luz (input text)
- estilo (input text)
- formato (select: cuadrado / vertical / horizontal)
- color_dominante (input text)
- temporada (input text libre)
- calidad (select: publicar / revisar / descartar)
- favorito (checkbox)
- notas (textarea)
- Botón Guardar → POST a api/mockups_varios.php accion=editar → actualiza tarjeta sin recargar

---

## PASO 4 — AÑADIR ENTRADA AL MENÚ EN header.php

En /Sahtout/includes/header.php, dentro del dropdown de ADMINISTRACIÓN (div.nox-dropdown-menu), añade después del enlace de LinkedIn Publisher:

```php
<a href="<?php echo $base_path; ?>pages/mockups.php">
    <i class="fas fa-images"></i> Mockups
</a>
```

---

## NOTAS IMPORTANTES

- La carpeta de destino de los archivos es /Sahtout/uploads/mockups_varios/ — créala si no existe con mkdir($dir, 0777, true)
- El input type="file" con atributo webkitdirectory permite seleccionar una carpeta entera desde el navegador sin plugins. Es compatible con Chrome y Edge. Firefox también lo soporta.
- La subida se hace archivo por archivo con fetch() para poder mostrar progreso real
- No usar Bootstrap ni librerías externas — el header ya carga FontAwesome y las fuentes
- Mantener el estilo oscuro con acento dorado (#d4af37) coherente con el resto del gestor
- Todos los selects de filtros deben rellenarse dinámicamente llamando a la API (valores distintos en BD), no hardcodeados
- La detección de formato (cuadrado/vertical/horizontal) usa getimagesize() en PHP al momento de subir
