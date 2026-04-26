# 03. Flujo de Vida del Artículo: De la Idea al Envío

El proceso en Mega Gestor Noxertez es circular y está diseñado para no perder rastro de ninguna pieza.

## Paso 1: Concepción (Futuros Proyectos)
Todo empieza en la pestaña de **Futuros Proyectos**. El usuario sube una imagen de referencia (de Pinterest, Instagram o un boceto).
*   La IA puede analizar la imagen para sugerir nombre y categorías.
*   El artículo queda en estado "PENDIENTE".

## Paso 2: Creación y Edición
Cuando se decide fabricar, el proyecto se convierte en un **Producto Real**.
*   Se genera un **SKU único** (ej: `DEC_NAT_0001` para una Decoración de Naturaleza).
*   Se asignan medidas, peso y descripción (apoyado por IA).

## Paso 3: Definición Técnica (Despiece)
En el módulo de **Stock**, se define la "receta" del artículo.
*   Se vinculan materiales (ej: "30cm de cinta roja", "1 base de madera").
*   La aplicación calcula automáticamente el costo de fabricación y la capacidad de producción basada en el almacén real.

## Paso 4: Venta y Producción
Tras una venta (Módulo de Ventas) o un encargo manual, el artículo entra en el **Tablero Kanban** (Módulo de Pedidos).
*   El artesano mueve la tarjeta físicamente por los estados (En proceso -> Horno -> Barniz).
*   Al llegar a "Listo para entrega", el sistema sabe que el stock físico ha aumentado y está disponible para enviar.

## Paso 5: Gestión de Envío
El artículo aparece en el módulo de **Envíos**.
*   Se recuperan las medidas guardadas en el Paso 2.
*   Se consultan tarifas en tiempo real con Packlink.
*   Se genera la etiqueta y se marca el pedido como "Entregado".
