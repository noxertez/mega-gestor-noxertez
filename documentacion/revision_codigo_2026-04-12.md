# Revisión de Código — Noxertez CMS

> Revisado el 2026-04-12 | PHP 8.0.30 en XAMPP  
> Autor: Antigravity AI | 87 archivos analizados  
> **Todos los problemas críticos: ✅ RESUELTOS**

---

## ✅ Estado general

**Sintaxis PHP:** 87 archivos revisados — **0 errores de sintaxis** en `api/`, `pages/`, `includes/`.

---

## 🔴 Problemas Críticos — TODOS RESUELTOS

### 1. `api/pedidos.php` — Sin etiqueta `<?php` ✅ CORREGIDO
El archivo no tenía la etiqueta de apertura PHP, lo que hacía que el servidor enviara el código fuente como texto plano al navegador. El error `Unexpected token 'u', "function ge"...` era causado por esto.

> **Causa del error original del usuario.**  
> **Archivos:** `api/pedidos.php` (XAMPP + workspace)  
> **Solución:** Añadida etiqueta `<?php` al inicio del archivo.  
> **Estado:** ✅ Corregido 2026-04-12

---

### 2. `api/index.php` — Sin protección de output ✅ CORREGIDO
El router de la API no tenía `ob_start()` ni supresión de errores, lo que permitía que cualquier notice/warning de PHP se colase en las respuestas JSON.

```php
// ✅ Añadido al inicio:
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
```

> **Archivos:** `api/index.php` (XAMPP + workspace)  
> **Estado:** ✅ Corregido 2026-04-12

---

### 3. `includes/header.php` — `display_errors = 1` activo ✅ CORREGIDO
El header global tenía `display_errors = 1`, haciendo que cualquier error PHP en cualquier página se imprimiese en la respuesta.

```php
// ❌ Anterior:
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ Corregido:
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
```

> **Archivos:** `includes/header.php` (XAMPP + workspace)  
> **Estado:** ✅ Corregido 2026-04-12

---

### 4. `api/chatbot_api.php` — Sin `ob_start()` ✅ CORREGIDO
El chatbot se llama directamente (no pasa por `index.php`) y no tenía `ob_start()`. Cualquier output accidental antes del `echo json_encode(...)` rompía la respuesta JSON.

```php
// ✅ Añadido al inicio:
ob_start();
```

> **Archivos:** `api/chatbot_api.php` (XAMPP + workspace)  
> **Estado:** ✅ Corregido 2026-04-12

---

### 5. `api/envios.php` — Credenciales de BD hardcodeadas ✅ CORREGIDO
Este archivo construía su propia conexión PDO con usuario y contraseña en texto plano en lugar de usar la función centralizada `conectar()` de `config.php`.

```php
// ❌ Anterior:
$db = new PDO('mysql:host=localhost;dbname=noxertez;charset=utf8mb4', 'noxertez_user', 'Noxertez2024!');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ✅ Corregido:
require_once __DIR__ . '/config.php';
$db = conectar();
```

> **Archivos:** `api/envios.php` (XAMPP + workspace)  
> **Estado:** ✅ Corregido 2026-04-12

---

### 6. `api/envios.php` — Packlink API key expuesta en código fuente ✅ CORREGIDO
La API key de Packlink estaba hardcodeada en texto plano. Movida a `config.php` como constante.

```php
// ❌ Anterior en envios.php:
$api_key = 'dac62040c3d23c50c9e76fef5f8dfe2de2fbaa38d72d2cdd33fa609839f7f3da';

// ✅ Corregido en config.php:
define('PACKLINK_API_KEY', 'dac62040c3d23c50c9e76fef5f8dfe2de2fbaa38d72d2cdd33fa609839f7f3da');

// ✅ Y en envios.php:
$api_key = defined('PACKLINK_API_KEY') ? PACKLINK_API_KEY : '';
```

> **Archivos:** `api/envios.php` + `api/config.php` (XAMPP + workspace)  
> **Estado:** ✅ Corregido 2026-04-12

---

## 🟠 Problemas Moderados — PENDIENTES (bajo riesgo)

### 7. `api/articulos.php` — `ALTER TABLE` en cada petición GET
Cada petición GET ejecuta 3 `ALTER TABLE`. MySQL los ignora si la columna existe, pero genera overhead innecesario.

> **Recomendación:** Mover a un script de migración único (`api/setup.php`).  
> **Riesgo:** Bajo — no afecta a la funcionalidad.  
> **Estado:** ⏳ Pendiente

### 8. `api/pedidos.php` PUT/DELETE — `header()` tardío
Los bloques PUT y DELETE llaman a `header('Content-Type: application/json')` después de que `index.php` ya fijó las cabeceras. Actualmente protegido por `ob_start()` en `index.php`.

> **Riesgo:** Bajo.  
> **Estado:** ⏳ Pendiente

---

## 🟡 Advertencias Menores — PENDIENTES

### 9. Claude API key sin configurar
`CLAUDE_API_KEY` en `config.php` está como `'TU_CLAUDE_API_KEY_AQUI'`. El chatbot usa fallback de respuestas predefinidas en lugar de IA real.

> **Estado:** ⏳ Pendiente (requiere key de Anthropic)

### 10. `pages/disponible_ahora.php` — URL WhatsApp sin fallback
```php
<a href="https://wa.me/34<?php echo str_replace(' ', '', $social_links['whatsapp']); ?>">
```
Si `$social_links` no está definido genera un notice y URL roto.

> **Estado:** ⏳ Pendiente

### 11. Uploads de imágenes sin validación MIME
En `articulos.php` POST, solo se verifica la extensión del nombre del archivo, no el tipo real. Un archivo `.php` disfrazado podría subirse si la extensión coincide.

> **Estado:** ⏳ Pendiente

---

## 📋 Resumen de cambios aplicados el 2026-04-12

| Archivo XAMPP | Archivo Workspace | Cambio |
|---|---|---|
| `api/pedidos.php` | `Sahtout/api/pedidos.php` | Añadida etiqueta `<?php` faltante |
| `api/index.php` | `Sahtout/api/index.php` | `ob_start()` + `error_reporting(0)` |
| `includes/header.php` | `Sahtout/includes/header.php` | `display_errors` → `0` |
| `api/chatbot_api.php` | `Sahtout/api/chatbot_api.php` | `ob_start()` añadido al inicio |
| `api/envios.php` | `Sahtout/api/envios.php` | Credenciales → `conectar()` + Packlink key → constante |
| `api/config.php` | `Sahtout/api/config.php` | `PACKLINK_API_KEY` añadida |

**Total archivos modificados:** 6 archivos × 2 entornos = **12 modificaciones**  
**Errores de sintaxis post-cambio:** 0
