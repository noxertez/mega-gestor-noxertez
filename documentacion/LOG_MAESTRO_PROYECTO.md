# 👑 Log Maestro - Ecosistema Digital Noxertez (Edición Detallada)

Este documento es la referencia técnica definitiva. Contiene no solo lo que el sistema hace, sino **cómo** lo hace y qué errores hemos superado.

---

# 🏛️ FASE: ATELIER (Abril 2026)

## 🏗️ 1. Arquitectura y "Gotchas" del Entorno
- **Doble Entorno:** El sistema se desarrolla en `C:\mis app de noxertez 2\SahtoutCMS-main\`, pero el servidor **XAMPP** ejecuta los archivos desde `C:\xampp\htdocs\noxertez\`. 
    - *Lección:* Cualquier cambio en el código debe sincronizarse con la carpeta de XAMPP para que tenga efecto inmediato.
- **Sincronización de Imágenes:** Las fotos las sube un programa de escritorio a carpetas locales.
    - *Solución:* Usar siempre `resolverRutaPublica($foto)` en PHP. Esta función convierte rutas físicas (ej: `C:/fotos/...`) en rutas web relativas (`../uploads/...`). **Nunca** usar rutas absolutas de Windows en etiquetas `<img>`.

## 📦 2. Módulo de Inventario (Stock) - El Corazón Visual
- **Reutilización de Lógica:** El sistema de previsualización de imágenes de LinkedIn se basa en el de `stock.php`. 
- **Lógica de Miniaturas:** Se utiliza un atributo `data-foto` dentro de los `<option>` de los selectores. Al cambiar el producto, un script de JS lee ese atributo y actualiza un contenedor `div` con `object-fit: contain`.
- **Mapa de Almacén (5.4):** Utiliza una cuadrícula interactiva. La clave aquí es la carga asíncrona de detalles para no ralentizar la visualización del mapa.

## 📱 3. Redes Sociales (LinkedIn & Pinterest)
- **LinkedIn API:** Las versiones de la API de LinkedIn caducan cada 12 meses. Si falla con error **426**, actualizar la versión (ej: `202604`).
- **Estructura del Post:** JSON jerárquico con `visibility` y `distribution` (`MAIN_FEED`).

## 🤖 4. IA y Automatización
- **Gemini Integration:** Generación de textos creativos por SKU.
- **n8n:** Gestión de procesos en segundo plano.

---

# 🦁 FASE: ANIMAL (Actual)

## 🖼️ Módulo: Multi-SKU Mockup Management System
Se ha diseñado un sistema de gestión de activos (imágenes y vídeos) con arquitectura **N:M** para permitir que un solo mockup sea utilizado por múltiples artículos/SKUs sin duplicar archivos.

### 💾 Estructura de Datos
- **Tabla `mockups_varios`**: Almacén central de archivos y metadatos.
    - **Novedad**: Integración de columnas de estado social (`publicado_en_instagram`, `publicado_en_facebook`, `publicado_en_tiktok`, `publicado_en_pinterest`, `publicado_en_linkedin`).
    - **Estadísticas**: Implementación de `veces_usado` (contador) y `ultima_vez_usado` (datetime) para tracking de activos.
- **Tabla `mockups_vinculaciones`**: Tabla intermedia que gestiona la relación única entre `mockup_id` y `sku`.

### 🛠️ Innovaciones Técnicas
- **Gestión de Vídeo**: Soporte nativo para carga y previsualización de archivos `.mp4`, `.mov`, etc. Separación automática en directorios `/videos_varios/` y `/mockups_varios/`.
- **Motor de Subida**: Implementación de `XMLHttpRequest` con listener de progreso para subida de carpetas enteras con barra de estado en tiempo real.
- **Optimización de Carga**: El "Banco General" utiliza carga diferida (lazy loading) e inicia vacío para maximizar el rendimiento, activándose solo mediante filtros dinámicos.

### 🎨 UI/UX (Glassmorphism Emerald & Gold)
- **Modales Inteligentes**: Anclados al viewport superior (`fixed`) para evitar scrolls infinitos en listas largas.
- **Editor de Metadatos**: Diseño en dos columnas con etiquetas fijas externas para evitar la pérdida de contexto al editar.
- **Indicadores de Redes**: Los iconos sociales en las tarjetas se iluminan con su color corporativo real (Instagram Rosa, LinkedIn Azul, etc.) basándose en los flags de la base de datos.
- **Flujo de Vinculación**: El botón "Vincular" cambia dinámicamente a "Vinculado" (Verde) si el activo ya tiene SKUs asociados.

## 📈 Memoria de Sesiones Recientes
- **Sesión 1**: Migración de lógica 1:1 a N:M. Creación de la tabla de vinculaciones.
- **Sesión 2**: Refactorización del editor de metadatos y solución de errores de scroll en modales.
- **Sesión 3**: Restauración del sistema de redes sociales. Mapeo de columnas profesionales y creación automática de campos faltantes. Implementación de contadores de uso real.

---
*Última actualización: 30 de Abril de 2026 - Fase ANIMAL Iniciada.*
