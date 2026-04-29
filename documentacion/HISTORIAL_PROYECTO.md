# 📜 Historial de Desarrollo - CMS Noxertez

Este documento sirve de contexto técnico para el desarrollo del CMS, evitando el uso de credenciales sensibles.

---

## 🚀 Hitos Alcanzados (Abril 2026)
- **Módulo LinkedIn:** Finalizado y funcional. Incluye redactor dinámico, previsualización de imágenes de productos y gestión de cola de publicaciones.
- **Módulo Pinterest:** Estructura preparada (Importación + Publicación), a la espera de validación de API.
- **Seguridad:** Filtrado de sesiones activo en `includes/session.php`. Se requiere `user_id` para acceder a `linkedin.php`.
- **Limpieza de Sistema:** Eliminación de carpetas duplicadas para unificar el entorno de ejecución en la raíz de XAMPP.

---

## 📂 Arquitectura de Archivos (XAMPP)
- **Ruta Principal:** `C:\xampp\htdocs\noxertez\`
- **Interfaz de Usuario:** `.../pages/` (Contiene `linkedin.php`, `pinterest.php`, `stock.php`).
- **Endpoints API:** `.../api/` (Contiene lógica de publicación y OAuth).
- **Configuración DB:** `api/config.php` (Centraliza la conexión a la base de datos).

---

## 🛠️ Detalles Técnicos de Integración

### LinkedIn API
- **Versión Utilizada:** `202604` (Formato YYYYMM).
- **Estructura del Post:** Se utiliza el endpoint `/rest/posts`.
- **Requisitos de JSON:** Es obligatorio incluir el objeto `visibility` y el objeto `distribution` (con `feedDistribution` como `MAIN_FEED`).
- **Gestión de Imágenes:** Las imágenes deben subirse primero mediante `/rest/images?action=initializeUpload` para obtener una `media_urn`.

### Resolución de Rutas
- El sistema utiliza `resolverRutaPublica()` (PHP) y `resolverRutaJS()` (JS) para mapear rutas físicas del servidor (donde la aplicación de escritorio guarda las fotos) a URLs web accesibles.

---

## 🔐 Seguridad y Privacidad
- **Credenciales:** Los Client ID, Secrets y Tokens **NUNCA** deben escribirse en este documento ni en archivos de documentación sincronizados.
- **Almacenamiento:** Las credenciales se gestionan exclusivamente en la tabla `configuracion` de la base de datos local.

---
*Documento generado para soporte de IA y control de versiones.*
