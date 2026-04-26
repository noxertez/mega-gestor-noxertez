# 05. Base de Datos y Estructura de Archivos

## El Corazón: catalogo.db
Toda la información reside en un archivo de base de datos **SQLite** llamado `catalogo.db`. Esta base de datos es relacional y contiene tablas clave:
*   `productos`: Información de SKUs, descripciones, precios y stock.
*   `materiales`: Inventario de materias primas con fotos y puntos de pedido (alertas de stock bajo).
*   `despiece_articulos`: La tabla relacional que vincula productos con los materiales necesarios.
*   `pedidos`: Historial y estado actual del tablero Kanban.
*   `clientes`: Base de datos de contactos con direcciones y teléfonos.

## Organización de Archivos Físicos
La aplicación sigue una estructura organizada por carpetas para las imágenes, evitando el desorden:
*   `C:\Users\usuario\Desktop\noxertez\aaa creaciones`: Carpeta raíz de imágenes de productos.
    *   `[SKU_DEL_PRODUCTO]`: Cada producto tiene su propia subcarpeta con su foto de portada y galería.
*   `materiales`: Almacena las fotos de las materias primas.
*   `proyectos`: Almacena las fotos de referencia de los futuros proyectos.
*   `fichas`: Genera automáticamente "Fichas de Producto" (imágenes combinadas con datos) listas para compartir por WhatsApp.

## Configuración: config_app.json
Contiene las preferencias del usuario, como la IA preferida para el análisis, los modelos seleccionados y las llaves API cifradas o protegidas en el entorno local.
