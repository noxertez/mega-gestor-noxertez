# 👑 Log Maestro - Ecosistema Digital Noxertez (Edición Detallada)

Este documento es la referencia técnica definitiva. Contiene no solo lo que el sistema hace, sino **cómo** lo hace y qué errores hemos superado.

---

## 🏛️ 1. Arquitectura y "Gotchas" del Entorno
- **Doble Entorno:** El sistema se desarrolla en `C:\mis app de noxertez 2\SahtoutCMS-main\`, pero el servidor **XAMPP** ejecuta los archivos desde `C:\xampp\htdocs\noxertez\`. 
    - *Lección:* Cualquier cambio en el código debe sincronizarse con la carpeta de XAMPP para que tenga efecto inmediato.
- **Sincronización de Imágenes:** Las fotos las sube un programa de escritorio a carpetas locales.
    - *Solución:* Usar siempre `resolverRutaPublica($foto)` en PHP. Esta función convierte rutas físicas (ej: `C:/fotos/...`) en rutas web relativas (`../uploads/...`). **Nunca** usar rutas absolutas de Windows en etiquetas `<img>`.

---

## 📦 2. Módulo de Inventario (Stock) - El Corazón Visual
- **Reutilización de Lógica:** El sistema de previsualización de imágenes de LinkedIn se basa en el de `stock.php`. 
- **Lógica de Miniaturas:** Se utiliza un atributo `data-foto` dentro de los `<option>` de los selectores. Al cambiar el producto, un script de JS lee ese atributo y actualiza un contenedor `div` con `object-fit: contain` (específicamente optimizado para que Firefox no deforme las fotos).
- **Mapa de Almacén (5.4):** Utiliza una cuadrícula interactiva. La clave aquí es la carga asíncrona de detalles para no ralentizar la visualización del mapa.

---

## 📱 3. Redes Sociales (LinkedIn & Pinterest)
- **LinkedIn API (Lección Crítica):** Las versiones de la API de LinkedIn (header `LinkedIn-Version`) caducan cada 12 meses. Si falla con error **426**, hay que actualizar la versión al mes/año actual (ej: `202604`).
- **Estructura del Post:** Para que LinkedIn acepte un post con imagen, el JSON debe seguir una jerarquía estricta:
    1. Subir imagen -> obtener `media_urn`.
    2. Crear post con `visibility` (objeto) y `distribution` (objeto `feedDistribution: MAIN_FEED`).
- **Pestañas (UI):** Se ha configurado para que el "Redactor" sea la pestaña principal por defecto, moviendo la "Configuración" al final, ya que solo se usa una vez.

---

## 🤖 4. IA y Automatización
- **Gemini Integration:** El sistema envía el SKU y la descripción del producto a Gemini para generar textos creativos. El "tono" se puede elegir (Profesional, Cercano, etc.).
- **n8n:** Gestiona procesos en segundo plano. Cuidado con los bucles infinitos en flujos de correo electrónico.

---

## 🔐 5. Seguridad Global
- **Protección de Páginas:** Para añadir una nueva página al sistema de seguridad, hay que incluirla en el array `$admin_pages` dentro de `includes/session.php`. 
- **Acceso:** Todas las páginas deben empezar con `define('ALLOWED_ACCESS', true);` y requerir `session.php`.

---

## 💡 NOTAS PARA EL DESARROLLADOR ENTRANTE
- **Fotos:** Si no ves una miniatura, revisa el `resolverRutaJS`. Es la pieza que más suele fallar si cambias carpetas.
- **Buscador:** El buscador avanzado de LinkedIn usa filtros en JS sobre el select de productos. Es mucho más rápido que hacer peticiones al servidor cada vez que escribes una letra.
- **GitHub:** Sincroniza solo la carpeta de documentos y código limpio. La base de datos y claves van por separado.

---
*Última revisión: 29 de Abril de 2026.*
