<?php
require_once 'config.php';
$db = conectar();

try {
    // 1. Columnas en pedidos
    $db->exec("ALTER TABLE pedidos 
        ADD COLUMN tracking_code VARCHAR(20) UNIQUE AFTER tracking_id,
        ADD COLUMN fecha_estimada_entrega DATE AFTER tracking_code,
        ADD COLUMN tracking_envio VARCHAR(100) AFTER fecha_estimada_entrega,
        ADD COLUMN transportista VARCHAR(50) AFTER tracking_envio,
        ADD COLUMN tracking_activo TINYINT DEFAULT 0 AFTER transportista");
    echo "Columnas añadidas a pedidos correctamente.\n";
} catch (Exception $e) {
    echo "Info: " . $e->getMessage() . " (Probablemente ya existen)\n";
}

try {
    // 2. Tabla de rate limit
    $db->exec("CREATE TABLE IF NOT EXISTS tracking_rate_limits (
        ip VARCHAR(45) PRIMARY KEY,
        consultas INT DEFAULT 0,
        ultima_consulta TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Tabla tracking_rate_limits creada correctamente.\n";
} catch (Exception $e) {
    echo "Error creando tabla: " . $e->getMessage() . "\n";
}
?>
