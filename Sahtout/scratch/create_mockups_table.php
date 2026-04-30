<?php
require_once 'Sahtout/api/config.php';
$db = conectar();

$sql = "CREATE TABLE IF NOT EXISTS mockups_varios (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  archivo             VARCHAR(255) NOT NULL,
  ruta                VARCHAR(500) NOT NULL,
  tipo                ENUM('imagen','video') DEFAULT 'imagen',
  estancia            VARCHAR(100) DEFAULT NULL,
  luz                 VARCHAR(100) DEFAULT NULL,
  estilo              VARCHAR(100) DEFAULT NULL,
  marca_noxertez      TINYINT(1) DEFAULT 0,
  marca_candleholder  TINYINT(1) DEFAULT 0,
  marca_zen           TINYINT(1) DEFAULT 0,
  formato             ENUM('cuadrado','vertical','horizontal') DEFAULT NULL,
  color_dominante     VARCHAR(100) DEFAULT NULL,
  temporada           VARCHAR(100) DEFAULT NULL,
  calidad             ENUM('publicar','revisar','descartar') DEFAULT 'revisar',
  favorito            TINYINT DEFAULT 0,
  notas               TEXT DEFAULT NULL,
  veces_usado         INT DEFAULT 0,
  ultima_vez_usado    DATETIME DEFAULT NULL,
  publicado_linkedin  DATETIME DEFAULT NULL,
  publicado_pinterest DATETIME DEFAULT NULL,
  publicado_instagram DATETIME DEFAULT NULL,
  asignado_a_sku      VARCHAR(100) DEFAULT NULL,
  fecha_subida        DATETIME DEFAULT NOW()
)";

try {
    $db->exec($sql);
    echo "Tabla mockups_varios creada o ya existente.\n";
} catch (PDOException $e) {
    echo "Error creando tabla: " . $e->getMessage() . "\n";
}
?>
