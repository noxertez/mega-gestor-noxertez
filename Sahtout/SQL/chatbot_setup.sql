-- ============================================================
-- CHATBOT NOXERTEZ - Setup de tablas
-- Ejecutar en la base de datos 'noxertez'
-- ============================================================

-- Tabla de configuración clave-valor del chatbot
CREATE TABLE IF NOT EXISTS `chatbot_config` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`       VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nombre de la configuración',
  `valor`       TEXT NOT NULL            COMMENT 'Valor de la configuración',
  `descripcion` VARCHAR(255) DEFAULT '' COMMENT 'Descripción para el panel de admin',
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores iniciales de configuración
INSERT INTO `chatbot_config` (`clave`, `valor`, `descripcion`) VALUES
('chatbot_activo',  '1',                                                                                  'Activar/desactivar el widget público (1=activo, 0=inactivo)'),
('tiempo_envio',    'Los pedidos estándar tardan entre 3 y 5 días hábiles en llegar a tu puerta.',        'Tiempo estimado de entrega de pedidos'),
('zonas_envio',     'Enviamos a toda España mediante Packlink. Para Canarias, Baleares y zonas especiales consultanos directamente.', 'Zonas de envío disponibles'),
('precio_envio',    'El envío tiene un coste de 4,99€ para pedidos estándar. ¡Los pedidos superiores a 50€ tienen envío gratuito!', 'Información sobre el precio del envío'),
('saludo_bienvenida','¡Hola! 👋 Soy el Asistente Noxertez. Puedo ayudarte con información sobre nuestros productos artesanales de madera, stock, precios y envíos. ¿En qué te puedo ayudar hoy?', 'Mensaje de bienvenida del chatbot'),
('horario_atencion', 'Respondemos a los pedidos de lunes a viernes de 9:00 a 20:00h. Los fines de semana también trabajamos en el taller, pero la atención al cliente puede demorarse un poco más.', 'Horario de atención al cliente'),
('whatsapp_numero',  '34693326269',                                                                         'Número de WhatsApp para derivar consultas'),
('bot_nombre',       'Asistente Noxertez',                                                                'Nombre del chatbot que aparece en el widget')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- Tabla de logs de preguntas públicas (sin datos personales)
CREATE TABLE IF NOT EXISTS `chatbot_logs` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pregunta`     TEXT NOT NULL             COMMENT 'Pregunta del usuario (anonimizada, sin datos personales)',
  `tipo_intent`  VARCHAR(50) DEFAULT 'otro' COMMENT 'Tipo detectado: stock, precio, envio, pedido_tiempo, privado, otro',
  `producto_ref` VARCHAR(100) DEFAULT NULL  COMMENT 'Referencia del producto mencionado si se detectó',
  `respondida`   TINYINT(1) DEFAULT 1       COMMENT '1 si el bot respondió, 0 si derivó a WhatsApp sin datos',
  `whatsapp_btn` TINYINT(1) DEFAULT 0       COMMENT '1 si se mostró botón de WhatsApp en la respuesta',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tipo` (`tipo_intent`),
  INDEX `idx_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
