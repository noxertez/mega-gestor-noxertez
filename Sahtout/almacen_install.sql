-- ============================================================
--  ALMACÉN INSTALL  --  Mega Gestor Noxertez
--  Ejecutar UNA SOLA VEZ para inicializar el módulo 5.4
-- ============================================================

-- 1. Añadir columnas de ubicación a la tabla existente 'materiales'
ALTER TABLE materiales
    ADD COLUMN IF NOT EXISTS ubicacion VARCHAR(20) NULL COMMENT 'Ej: A2-C3',
    ADD COLUMN IF NOT EXISTS estado_stock ENUM('B','S','T') NULL COMMENT 'B=Base/Bruto S=Sin tintar T=Terminado';

-- 2. Crear tabla de estanterías
CREATE TABLE IF NOT EXISTS almacen_estanterias (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL          COMMENT 'Ej: Estantería A',
    num_baldas  INT          NOT NULL DEFAULT 3,
    num_columnas INT         NOT NULL DEFAULT 4,
    orden       INT          NOT NULL DEFAULT 0,
    activa      TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Crear tabla de posiciones dentro de cada estantería
CREATE TABLE IF NOT EXISTS almacen_posiciones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    estanteria_id   INT          NOT NULL,
    balda           INT          NOT NULL COMMENT 'Fila (1 = superior)',
    columna         INT          NOT NULL COMMENT 'Columna (1 = izquierda)',
    etiqueta        VARCHAR(20)  NOT NULL COMMENT 'Ej: A2-C3',
    tipo_caja       ENUM('negra','verde','transparente','otra') NULL,
    notas           TEXT         NULL,
    UNIQUE KEY uq_pos (estanteria_id, balda, columna),
    FOREIGN KEY (estanteria_id) REFERENCES almacen_estanterias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
