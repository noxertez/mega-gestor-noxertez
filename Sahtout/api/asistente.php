<?php
header('Content-Type: application/json');
$db = conectar();

$input = json_decode(file_get_contents('php://input'), true);
$texto = strtolower($input['texto'] ?? '');

if (empty($texto)) {
    echo json_encode(['respuesta' => 'No te he oído bien, ¿puedes repetir?']);
    exit;
}

// 1. Lógica para VENTAS
if (strpos($texto, 'ventas') !== false || strpos($texto, 'cuánto') !== false || strpos($texto, 'dinero') !== false) {
    $res = $db->query("SELECT SUM(importe) as t FROM ventas WHERE DATE(fecha) = CURDATE()")->fetch();
    $total = number_format($res['t'] ?? 0, 2);
    echo json_encode(['respuesta' => "Hoy has vendido un total de $total euros."]);
    exit;
}

// 2. Lógica para NOTAS / TAREAS (Inmediata para palabras clave)
$keywords_nota = ['nota', 'anota', 'escribe', 'revisar', 'recordar', 'apuntar', 'tarea', 'haz', 'comprar', 'llamar', 'limpiar', 'pedir'];
$es_nota = false;
foreach ($keywords_nota as $k) {
    if (strpos($texto, $k) !== false) {
        $es_nota = true;
        break;
    }
}

if ($es_nota) {
    // Limpiar comando de la frase
    $contenido = preg_replace('/(añadir|crear|haz una|anota|escribe|nota|un|de|que diga|sobre|revisar|recordar|apuntar|tarea|:)/u', '', $texto);
    $contenido = trim($contenido);
    
    if (!empty($contenido)) {
        $stmt = $db->prepare("INSERT INTO tareas (descripcion, prioridad, fecha_creacion, completada) VALUES (?, 'baja', NOW(), 0)");
        $stmt->execute([ucfirst($contenido)]);

        echo json_encode([
            'respuesta' => 'He anotado eso en tu bloc de notas.',
            'accion' => 'reload_tasks'
        ]);
        exit;
    }
}

// 3. FALLBACK INTELIGENTE: Si no coincide con comandos conocidos pero tiene longitud, guardarlo como nota anyway.
if (strlen($texto) > 4) {
    $stmt = $db->prepare("INSERT INTO tareas (descripcion, prioridad, fecha_creacion, completada) VALUES (?, 'baja', NOW(), 0)");
    $stmt->execute([ucfirst($texto)]);

    echo json_encode([
        'respuesta' => 'Entendido, lo he apuntado en tu bloc de notas.',
        'accion' => 'reload_tasks'
    ]);
    exit;
}

// 4. Respuesta por defecto
echo json_encode(['respuesta' => 'No estoy seguro de cómo ayudarte con eso. ¿Es un comando o una nota?']);
