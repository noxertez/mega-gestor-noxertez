-- ============================================================
-- MÓDULO DIAGRAMA DE FLUJO DE PEDIDOS — Noxertez Artesanía
-- Ejecutar en la base de datos: noxertez
-- ============================================================

-- 0. Ampliar tabla pedidos con campos del flujo
-- ============================================================
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS fecha_entrega_prometida DATE NULL COMMENT 'Fecha de entrega acordada con el cliente',
    ADD COLUMN IF NOT EXISTS canal_origen ENUM('whatsapp','trendioff','directo','otro') NOT NULL DEFAULT 'whatsapp' COMMENT 'Canal por el que llegó el pedido',
    ADD COLUMN IF NOT EXISTS id_flujo_plantilla INT NULL COMMENT 'Plantilla de flujo asignada a este pedido';

-- Actualizar ENUM de estado a los 6 estados unificados
-- NOTA: Si la columna 'estado' ya existe con ENUM, se amplía.
ALTER TABLE pedidos
    MODIFY COLUMN estado ENUM(
        'por_empezar',
        'en_proceso',
        'montado',
        'tintado',
        'barnizado',
        'listo_para_entregar',
        'entregado',
        'cancelado'
    ) NOT NULL DEFAULT 'por_empezar' COMMENT 'Estado unificado del pedido (6 fases + entregado/cancelado)';

-- ============================================================
-- 1. Plantillas de flujo (una por tipo de producto)
-- ============================================================
CREATE TABLE IF NOT EXISTS flujo_plantillas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL COMMENT 'Ej: Artículo Estándar Madera, Portavelas',
    descripcion     TEXT NULL,
    tipo_producto   VARCHAR(100) NULL COMMENT 'SKU base o categoría del producto',
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Plantillas reutilizables de flujo de producción';

-- ============================================================
-- 2. Nodos de cada plantilla
-- ============================================================
CREATE TABLE IF NOT EXISTS flujo_nodos_plantilla (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    id_plantilla          INT NOT NULL,
    orden                 TINYINT NOT NULL DEFAULT 0 COMMENT 'Posición del nodo en el flujo (0=primero)',
    nombre                VARCHAR(100) NOT NULL,
    icono                 VARCHAR(50) NULL DEFAULT 'fa-circle' COMMENT 'Clase FontAwesome sin fa-',
    color                 VARCHAR(20) NULL DEFAULT '#3b82f6' COMMENT 'Color hex del nodo',
    tiempo_estimado_min   INT NULL DEFAULT 0 COMMENT 'Tiempo estimado en minutos para completar este nodo',
    tipo                  ENUM('nodo','incidencia') NOT NULL DEFAULT 'nodo',
    estado_pedido_mapeo   ENUM('por_empezar','en_proceso','montado','tintado','barnizado','listo_para_entregar','entregado','cancelado') NULL COMMENT 'Estado de pedido que activa este nodo al avanzar en Kanban',
    CONSTRAINT fk_fnp_plantilla FOREIGN KEY (id_plantilla) REFERENCES flujo_plantillas(id) ON DELETE CASCADE,
    INDEX idx_plantilla_orden (id_plantilla, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Definición de nodos para cada plantilla de flujo';

-- ============================================================
-- 3. Instancia real del flujo por pedido
-- ============================================================
CREATE TABLE IF NOT EXISTS pedido_nodos (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido            INT NOT NULL,
    id_nodo_plantilla    INT NOT NULL,
    estado               ENUM('pendiente','en_curso','completado','bloqueado') NOT NULL DEFAULT 'pendiente',
    fecha_inicio         DATETIME NULL,
    fecha_fin            DATETIME NULL,
    notas                TEXT NULL COMMENT 'Notas libres del artesano para esta fase',
    tiempo_real_minutos  INT NULL DEFAULT 0 COMMENT 'Tiempo real invertido en este nodo',
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pn_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pn_nodo FOREIGN KEY (id_nodo_plantilla) REFERENCES flujo_nodos_plantilla(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pedido_nodo (id_pedido, id_nodo_plantilla),
    INDEX idx_estado_nodo (estado),
    INDEX idx_pedido (id_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Instancia real del flujo de producción para cada pedido';

-- ============================================================
-- 4. Registro de incidencias por nodo
-- ============================================================
CREATE TABLE IF NOT EXISTS pedido_nodo_incidencias (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido_nodo  INT NOT NULL,
    tipo            ENUM('rotura','reclamacion','retraso','material','otro') NOT NULL DEFAULT 'otro',
    descripcion     TEXT NOT NULL,
    resuelto        TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resuelto_at     DATETIME NULL,
    CONSTRAINT fk_pni_nodo FOREIGN KEY (id_pedido_nodo) REFERENCES pedido_nodos(id) ON DELETE CASCADE,
    INDEX idx_resuelto (resuelto),
    INDEX idx_pedido_nodo (id_pedido_nodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Incidencias registradas en nodos concretos de producción';

-- ============================================================
-- 5. Datos de ejemplo — Plantilla «Artículo Estándar Madera»
-- ============================================================
INSERT IGNORE INTO flujo_plantillas (id, nombre, descripcion, tipo_producto, activo) VALUES
(1, 'Artículo Estándar Madera', 'Flujo de producción estándar para artículos de madera', 'madera', 1),
(2, 'Portavelas CHOS', 'Flujo específico para portavelas con parafina', 'portavelas', 1);

INSERT IGNORE INTO flujo_nodos_plantilla (id, id_plantilla, orden, nombre, icono, color, tiempo_estimado_min, tipo, estado_pedido_mapeo) VALUES
-- Plantilla 1: Artículo Estándar Madera
(1,  1, 0, 'Pedido Entrante',   'inbox',            '#6366f1', 5,   'nodo',       'por_empezar'),
(2,  1, 1, 'Materiales',        'boxes-stacked',    '#f59e0b', 15,  'nodo',       'por_empezar'),
(3,  1, 2, 'En Proceso',        'hammer',           '#8b5cf6', 60,  'nodo',       'en_proceso'),
(4,  1, 3, 'Montado',           'puzzle-piece',     '#06b6d4', 30,  'nodo',       'montado'),
(5,  1, 4, 'Tintado',           'paint-roller',     '#ec4899', 45,  'nodo',       'tintado'),
(6,  1, 5, 'Barnizado',         'shield-halved',    '#84cc16', 40,  'nodo',       'barnizado'),
(7,  1, 6, 'Control Calidad',   'magnifying-glass', '#22d3ee', 10,  'nodo',       'barnizado'),
(8,  1, 7, 'Embalaje',          'box',              '#fb923c', 15,  'nodo',       'listo_para_entregar'),
(9,  1, 8, 'Envío',             'truck',            '#3b82f6', 5,   'nodo',       'listo_para_entregar'),
(10, 1, 9, 'Entregado',         'circle-check',     '#10b981', 0,   'nodo',       'entregado'),
(11, 1, 10,'Incidencia',        'triangle-exclamation', '#ef4444', 0, 'incidencia', NULL),
-- Plantilla 2: Portavelas
(12, 2, 0, 'Pedido Entrante',   'inbox',            '#6366f1', 5,   'nodo',       'por_empezar'),
(13, 2, 1, 'Materiales Cera',   'boxes-stacked',    '#f59e0b', 20,  'nodo',       'por_empezar'),
(14, 2, 2, 'Fundición',         'fire',             '#ef4444', 60,  'nodo',       'en_proceso'),
(15, 2, 3, 'Montado Mecha',     'plug',             '#06b6d4', 20,  'nodo',       'montado'),
(16, 2, 4, 'Tintado/Fragancia', 'flask',            '#ec4899', 30,  'nodo',       'tintado'),
(17, 2, 5, 'Enfriado',          'snowflake',        '#84cc16', 90,  'nodo',       'barnizado'),
(18, 2, 6, 'Embalaje',          'box',              '#fb923c', 15,  'nodo',       'listo_para_entregar'),
(19, 2, 7, 'Envío',             'truck',            '#3b82f6', 5,   'nodo',       'listo_para_entregar'),
(20, 2, 8, 'Entregado',         'circle-check',     '#10b981', 0,   'nodo',       'entregado');
