-- ============================================================
--  MÓDULO EMAIL — Mega Gestor Noxertez
--  Ejecutar en la base de datos: noxertez
-- ============================================================

CREATE TABLE IF NOT EXISTS `emails_enviados` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `alias_from`    VARCHAR(100)  NOT NULL COMMENT 'Alias emisor: info@noxertez.com, pedidos@..., etc.',
    `destinatario`  VARCHAR(255)  NOT NULL,
    `asunto`        VARCHAR(500)  NOT NULL,
    `cuerpo`        TEXT          NOT NULL,
    `fecha_envio`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `en_respuesta_a` VARCHAR(500) DEFAULT NULL COMMENT 'Asunto del email original al que se responde',
    `estado`        VARCHAR(20)   DEFAULT 'enviado',
    INDEX idx_alias_from  (`alias_from`),
    INDEX idx_fecha_envio (`fecha_envio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
