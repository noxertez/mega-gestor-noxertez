# 🚀 GUÍA DE AUTOMATIZACIONES NOXERTEZ (Para Humanos)

¡Bienvenido! Este documento explica de forma súper sencilla cómo 
funciona la "magia" de tu sistema. Olvida los términos técnicos, 
aquí te explico cómo usarlo en el día a día.

---

## 🛠️ El "Cerebro" (n8n) y el "Almacén" (MySQL)

- **El Cerebro (n8n)**: Es el que lee tus correos, entiende tus mensajes 
de WhatsApp y escucha tu voz. Siempre tiene que estar encendido (puedes usar 
el `Lanzador_n8n.bat`).
- **El Almacén (MySQL)**: Es donde se guarda todo (clientes, pedidos, stock). 
**¡IMPORTANTE!** Ya no usamos SQLite (ese sistema viejo y lento). Ahora todo 
es **MySQL**, que es mucho más rápido y seguro.

---

## 📧 1. Cómo pedir por EMAIL (Paso a Paso)

Imagina que un cliente quiere pedir algo pero no quiere entrar en la web. 
Puede mandarte un email.

### ¿Qué tiene que hacer el cliente?
1. Escribir a: **noxertez@gmail.com**
2. **El Asunto**: Debe incluir la palabra **"Pedido"** o **"Encargo"**.
3. **El Contenido**: Debe poner su nombre, qué quiere y cuántos. (Ejemplo: 
*"Hola, soy Juan, quiero un Pedido de 2 martillos"*).

### ¿Qué hace el sistema automáticamente?
1. **Busca al Cliente**: n8n mira si ese email ya existe en tu base de datos 
de **MySQL**.

2. **Crea al Cliente (si es nuevo)**: Si nunca te había escrito, el sistema 
lo registra solo. ¡No tienes que hacer nada!
3. **Crea el Pedido**: Mete el pedido directamente en tu lista de 
**"Pedidos"** y lo pone en el **Kanban** como "Por empezar".

---

## 💬 2. Cómo usar WHATSAPP (Ahorra tiempo)

Si un cliente te escribe un testamento por WhatsApp con su pedido,
 no lo copies a mano.

### ¿Cómo lo haces tú?
1. **Copia** el texto del mensaje de WhatsApp.
2. **Pégalo** en la sección correspondiente del CMS o la PC App 
(donde dice "Procesar mensaje").
3. Dale a **"Procesar"**.

### ¿Cómo funciona por dentro?
- El sistema envía ese texto a la Inteligencia Artificial de n8n.
- La IA lee el texto, identifica quién es el cliente y qué piezas quiere.
- Guarda el pedido en **MySQL** automáticamente.

---

## 🎙️ 3. Asistente de Voz (Nuevo en la Web)

Ahora tienes una pestaña llamada **"Voz"** en tu CMS.

1. Pulsa el **micrófono rojo**.
2. Di algo natural: *"Anota que tengo que comprar pintura azul"* o
 *"Crea un pedido para María de un llavero"*.
3. El sistema te responderá hablando 🔊 y guardará la nota o el pedido por ti.

---

## ⚠️ Recordatorios Importantes
- **Todo va a MySQL**: Si alguien te habla de SQLite, ignóralo,
 es cosa del pasado.
- **Internet**: Para que los emails y WhatsApps entren solos, el 
túnel de Cloudflare debe estar funcionando.
- **Lanzadores**: Usa los archivos `.bat` que tienes en esta carpeta
    (`SahtoutCMS-main`) para arrancar todo fácilmente.

---
*Documentación generada para Noxertez - Versión 2.0 (Todo MySQL)*
