# 04. Inteligencia Artificial y Automatización

Mega Gestor Noxertez no es solo una base de datos, es un asistente inteligente.

## Inteligencia Artificial (IA)
La aplicación utiliza los modelos **Gemini 1.5 Flash** (Google) y **Llama 3.1** (vía Groq) para:
1.  **Visión de Producto**: Al subir una foto, la IA rellena automáticamente campos como Nombre, Categoría, Marca y Descripción, ahorrando minutos de escritura manual.
2.  **Generación de Despiece**: La IA analiza lo que "ve" en la imagen y sugiere qué materiales se han usado para su fabricación.
3.  **Fallback Inteligente**: Si Google Gemini falla por cuota, el sistema cambia automáticamente a Groq sin que el usuario lo note.

## Automatización con n8n
La aplicación incluye una integración profunda con **n8n**, una herramienta de automatización de flujos de trabajo basada en nodos.
*   **Servidor Local**: La app puede arrancar su propio servidor de n8n.
*   **Webhooks**: Cuando ocurre un evento importante (ej: venta realizada), la app puede enviar un "webhook" a n8n para que este realice tareas externas:
    *   Enviar un email de agradecimiento.
    *   Notificar por un bot de Telegram.
    *   Sincronizar el pedido con una hoja de cálculo externa.
    *   Actualizar estados de stock en tiendas online (Shopify, Etsy).
