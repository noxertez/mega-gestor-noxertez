# 02. Arquitectura y Desglose de Módulos

La aplicación está dividida en más de 20 módulos especializados. Aquí se detalla la función de los más importantes:

## Núcleo y Base
*   **app.py**: El punto de entrada principal. Gestiona la ventana de Tkinter, el menú de pestañas y la inicialización de todos los servicios.
*   **modulo1_nucleo.py**: El "Cerebro". Contiene la lógica de conexión con las APIs de IA (Gemini/Groq), el sistema de rotación de llaves API y el contador de llamadas.
*   **modulo2_interfaz.py**: Define la estética de la aplicación (colores, fuentes, estilos de botones) para asegurar una experiencia de usuario premium.
*   **modulo3_gestion.py**: El gestor de la base de datos (`catalogo.db`). Maneja el CRUD (Crear, Leer, Actualizar, Borrar) de productos, materiales y clientes.

## Gestión de Producto y Stock
*   **modulo12_stock.py**: Gestiona el inventario de materiales y las "recetas". Permite saber qué materiales se necesitan para fabricar un artículo y calcula cuántos artículos puedes fabricar con el stock actual (**Capacidad de Fabricación**).
*   **modulo13_futuros_proyectos.py**: Un espacio creativo donde se guardan ideas o imágenes de referencia antes de convertirlas en productos reales.

## Ventas y Operaciones
*   **modulo16_ventas.py**: Registro de transacciones. Permite asociar ventas a clientes y restar stock automáticamente.
*   **modulo19_pedidos.py**: Tablero Kanban de producción con 5 estados:
    1.  Por empezar
    2.  En proceso
    3.  Secado / Horno
    4.  Acabado / Barniz
    5.  Listo para entrega (pasa automáticamente al módulo de envíos).
*   **modulo20_envios.py**: Integración con Packlink. Permite elegir el mejor transportista basándose en el peso y medidas del paquete calculadas en el módulo de stock.

## Comunicaciones y Automatización
*   **modulo11_whatsapp.py**: Envío de notificaciones y mensajes a clientes.
*   **modulo_n8n.py**: Lógica para arrancar y gestionar el servidor local de n8n, permitiendo crear flujos de automatización complejos (como avisar al cliente cuando su pedido entra en "En proceso").
