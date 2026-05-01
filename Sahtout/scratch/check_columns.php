<?php
/**
 * check_columns.php — Script de verificación y migración de columnas
 * Tarea 2: productos.alerta_stock_enviada
 * Tarea 3: pedidos.seguimiento_enviado
 * Ejecutar UNA SOLA VEZ desde el navegador o CLI.
 */

define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../api/config.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Verificación de columnas ===\n\n";

$pdo = conectar();

// ──────────────────────────────────────────────────
// TAREA 2: productos.alerta_stock_enviada (INT, default 0)
// ──────────────────────────────────────────────────
$stmt = $pdo->query(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'productos'
       AND COLUMN_NAME  = 'alerta_stock_enviada'"
);
if ($stmt->fetch()) {
    echo "[OK] productos.alerta_stock_enviada → ya existe. Sin cambios.\n";
} else {
    $pdo->exec("ALTER TABLE productos ADD COLUMN alerta_stock_enviada INT DEFAULT 0");
    echo "[AÑADIDA] productos.alerta_stock_enviada INT DEFAULT 0 → creada correctamente.\n";
}

// ──────────────────────────────────────────────────
// TAREA 3: pedidos.seguimiento_enviado (TINYINT(1), default 0)
// ──────────────────────────────────────────────────
$stmt = $pdo->query(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'pedidos'
       AND COLUMN_NAME  = 'seguimiento_enviado'"
);
if ($stmt->fetch()) {
    echo "[OK] pedidos.seguimiento_enviado → ya existe. Sin cambios.\n";
} else {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN seguimiento_enviado TINYINT(1) DEFAULT 0");
    echo "[AÑADIDA] pedidos.seguimiento_enviado TINYINT(1) DEFAULT 0 → creada correctamente.\n";
}

echo "\n=== Migración completada ===\n";
