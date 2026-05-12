# PROMPT 1 — Módulo Email · Mega Gestor Noxertez

## Contexto del proyecto

Estoy construyendo **Mega Gestor Noxertez**, un sistema de gestión empresarial hecho a medida en PHP + MySQL que corre en XAMPP local (Windows). El sistema ya tiene módulos de pedidos, stock, almacén, publicación en redes sociales, etc. Ahora necesito añadir el **Módulo de Email** (módulo 6 o el número que corresponda en el menú).

El sistema tiene un diseño oscuro, moderno, con sidebar de navegación. El estilo visual debe ser coherente con el resto del gestor: fondo oscuro (`#0f0f1a` o similar), tarjetas con fondo `#1a1a2e`, acentos en azul/violeta, tipografía limpia.

---

## Stack técnico

- **Backend**: PHP 8.x con XAMPP
- **Base de datos**: MySQL, base de datos `noxertez`, usuario `noxertez_user`
- **Lectura de emails**: PHP directo a IMAP — `imap_open()` conectando a `imap.gmail.com:993` con SSL
- **Envío de emails**: PHPMailer via SMTP — `smtp.gmail.com:587` con TLS y App Password de Gmail
- **Credenciales IMAP/SMTP**: guardadas en el archivo de configuración central del sistema (`config.php` o similar)

---

## Aliases de correo

Tenemos 4 aliases de Gmail configurados en Cloudflare Email Routing, todos reenviados a `noxertez@gmail.com`:

| Alias | Uso |
|-------|-----|
| `info@noxertez.com` | Consultas generales |
| `pedidos@noxertez.com` | Gestión de pedidos |
| `influencers@noxertez.com` | Colaboraciones |
| `ayuda@noxertez.com` | Soporte al cliente |

Cada alias tiene su propio `Reply-To` y se envía desde él via PHPMailer usando la cuenta SMTP de Gmail con App Password.

---

## Tabla MySQL necesaria

Crear la tabla si no existe:

```sql
CREATE TABLE IF NOT EXISTS emails_enviados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alias_from VARCHAR(100) NOT NULL,
    destinatario VARCHAR(255) NOT NULL,
    asunto VARCHAR(500) NOT NULL,
    cuerpo TEXT NOT NULL,
    fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    en_respuesta_a VARCHAR(500) DEFAULT NULL,
    estado VARCHAR(20) DEFAULT 'enviado'
);
```

---

## Estructura de archivos a crear

```
/email/
  index.php          ← página principal del módulo
  imap_helper.php    ← funciones para conectar y leer IMAP
  send.php           ← endpoint POST para enviar via PHPMailer
  emails_ajax.php    ← endpoint AJAX para cargar emails y estadísticas
  style_email.css    ← estilos específicos del módulo (si no están en el CSS global)
```

---

## Funcionalidad requerida — página principal (`index.php`)

### 1. Pestañas horizontales
- 4 pestañas: **info | pedidos | influencers | ayuda**
- Al hacer clic en una pestaña se carga el contenido de ese alias
- La pestaña activa se resalta visualmente
- Cada pestaña muestra un badge con el número de emails no leídos

### 2. Panel de estadísticas (parte superior de cada pestaña)
Mostrar 3 tarjetas de métricas para el alias seleccionado:
- 📥 **Recibidos hoy**
- 📤 **Enviados hoy** (desde tabla `emails_enviados`)
- ⏳ **Sin responder** (emails recibidos sin respuesta registrada en `emails_enviados`)

### 3. Bandeja de entrada (IMAP)
- Leer los últimos 30 emails del buzón de `noxertez@gmail.com` filtrando por el alias de la pestaña activa (buscar en el campo `To:` o en el asunto/etiqueta)
- Mostrar lista de emails con: remitente, asunto, fecha, preview del cuerpo (primeros 80 caracteres)
- Al hacer clic en un email → vista de detalle con cuerpo completo
- Botón **"Responder"** que abre el formulario de redacción pre-rellenado con `Re: [asunto]` y el alias correcto en el FROM
- Distinguir visualmente leídos vs no leídos

> **Nota técnica IMAP**: Conectar con `imap_open('{imap.gmail.com:993/imap/ssl}INBOX', $user, $pass)`. Para filtrar por alias, usar `imap_search($conn, 'TO "pedidos@noxertez.com"')` con el alias de la pestaña activa.

### 4. Formulario de redacción / respuesta
- Campos: **Para** (destinatario), **Asunto**, **Cuerpo** (textarea con altura generosa)
- El campo **FROM** se fija automáticamente al alias de la pestaña activa (no editable, solo informativo)
- Botón **Enviar** → POST a `send.php`
- Al enviar correctamente → guardar en tabla `emails_enviados` y mostrar confirmación
- Soporte para responder (pre-rellena asunto con `Re:` y añade el hilo anterior en el cuerpo)

### 5. Emails enviados
- Listado de los últimos 20 emails enviados desde el alias activo (leer de `emails_enviados` en MySQL)
- Mostrar: destinatario, asunto, fecha, si era respuesta a alguien
- Vista compacta tipo tabla

---

## Archivo `send.php` — lógica de envío

```php
// Recibe POST: alias_from, destinatario, asunto, cuerpo, en_respuesta_a (opcional)
// 1. Valida que alias_from sea uno de los 4 válidos
// 2. Carga credenciales SMTP desde config
// 3. Envía con PHPMailer:
//    - Host: smtp.gmail.com, Port: 587, SMTPSecure: TLS
//    - Username: noxertez@gmail.com, Password: [APP_PASSWORD]
//    - From: alias_from (ej: pedidos@noxertez.com)
//    - Reply-To: alias_from
// 4. Si éxito → INSERT en emails_enviados + devuelve JSON {ok: true}
// 5. Si error → devuelve JSON {ok: false, error: mensaje}
```

---

## Archivo `imap_helper.php` — funciones clave

```php
// Funciones a implementar:
// - conectar_imap() → devuelve $conn o false
// - obtener_emails_alias($conn, $alias, $limit=30) → array de emails filtrados por TO
// - obtener_email_detalle($conn, $uid) → email completo (cabeceras + cuerpo)
// - contar_no_leidos($conn, $alias) → int
// - cerrar_imap($conn)
```

---

## UX y diseño visual

- **Tema oscuro** coherente con el resto del Mega Gestor
- Pestañas con iconos emoji o iconos FontAwesome: 📋 info · 📦 pedidos · 🤝 influencers · 🆘 ayuda
- Lista de emails con hover effect, separadores sutiles
- Email seleccionado se resalta con borde o fondo diferente
- Formulario de redacción con estilo tipo "panel flotante" o sección diferenciada
- Loading spinner mientras carga IMAP (puede tardar 1-2 segundos)
- Mensajes de éxito/error con estilo toast o banner
- Responsive mínimo: que funcione bien en pantalla de escritorio (1200px+)

---

## Notas adicionales

- Las credenciales (Gmail user, App Password) vienen del `config.php` central del sistema — NO hardcodear en los archivos del módulo
- PHPMailer ya está instalado en el proyecto (via Composer o manual en `/vendor/`)
- El módulo debe integrarse en el menú lateral del Mega Gestor como un ítem más
- Protección mínima: verificar que el usuario esté logueado (sesión activa) antes de mostrar nada
- Los errores de IMAP deben capturarse con `try/catch` y mostrar mensaje amigable en lugar de error PHP en pantalla
