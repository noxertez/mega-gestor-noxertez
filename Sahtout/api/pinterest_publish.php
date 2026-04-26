<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once 'config.php';
header('Content-Type: application/json');
$db = conectar();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pin_id_especifico = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);

try {
    // Leer configuración
    $stmtCfg = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('pinterest_access_token','pinterest_pins_por_dia')");
    $config = [];
    foreach ($stmtCfg->fetchAll() as $r) $config[$r['clave']] = $r['valor'];

    $token = $config['pinterest_access_token'] ?? '';
    $pins_por_dia = (int)($config['pinterest_pins_por_dia'] ?? 10);

    if (!$token) {
        jsonSalida(['ok' => false, 'error' => 'Sin token de Pinterest configurado']);
    }

    // Cuántos publicados hoy
    $hoy = date('Y-m-d');
    $pubHoy = (int)$db->query("SELECT COUNT(*) FROM pinterest_queue WHERE DATE(fecha_publicado)='$hoy' AND estado='publicado'")->fetchColumn();
    $limite = $pins_por_dia - $pubHoy;
    if ($limite <= 0 && !$pin_id_especifico) {
        jsonSalida(['ok' => true, 'publicados' => 0, 'mensaje' => 'Límite diario alcanzado (' . $pins_por_dia . ' pins/día)', 'errores' => []]);
    }

    // Obtener tableros configurados
    $tableros = [];
    $stmtT = $db->query("SELECT categoria, board_id FROM pinterest_tableros WHERE activo=1");
    foreach ($stmtT->fetchAll() as $t) $tableros[strtolower($t['categoria'])] = $t['board_id'];

    // Seleccionar pins a publicar
    if ($pin_id_especifico) {
        $stmt = $db->prepare("SELECT * FROM pinterest_queue WHERE id=?");
        $stmt->execute([$pin_id_especifico]);
        $pins = $stmt->fetchAll();
    } else {
        $stmt = $db->prepare("SELECT * FROM pinterest_queue WHERE estado='pendiente' AND (fecha_programada <= ? OR fecha_programada IS NULL) ORDER BY fecha_programada ASC, id ASC LIMIT ?");
        $stmt->execute([$hoy, $limite]);
        $pins = $stmt->fetchAll();
    }

    $publicados = 0;
    $errores = [];

    foreach ($pins as $pin) {
        $cat_key = strtolower($pin['tablero_categoria'] ?? '');
        $board_id = $pin['board_id_pinterest'] ?? $tableros[$cat_key] ?? null;

        if (!$board_id) {
            $db->prepare("UPDATE pinterest_queue SET estado='error', intentos=intentos+1, mensaje_error=? WHERE id=?")
               ->execute(['Sin Board ID configurado para la categoría: ' . $pin['tablero_categoria'], $pin['id']]);
            $errores[] = ['id' => $pin['id'], 'error' => 'Sin Board ID para categoría ' . $pin['tablero_categoria']];
            continue;
        }

        // Llamar Pinterest API v5
        $body = json_encode([
            'board_id'    => $board_id,
            'title'       => $pin['titulo'],
            'description' => $pin['descripcion'],
            'link'        => $pin['enlace'],
            'media_source'=> ['source_type' => 'image_url', 'url' => $pin['imagen_url']]
        ]);

        $ch = curl_init('https://api.pinterest.com/v5/pins');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 201) {
            $resp = json_decode($response, true);
            $pin_pinterest_id = $resp['id'] ?? null;
            $db->prepare("UPDATE pinterest_queue SET estado='publicado', pin_id_pinterest=?, fecha_publicado=NOW(), board_id_pinterest=? WHERE id=?")
               ->execute([$pin_pinterest_id, $board_id, $pin['id']]);
            $publicados++;
        } else {
            $nuevos_intentos = (int)$pin['intentos'] + 1;
            $nuevo_estado = $nuevos_intentos >= 3 ? 'error' : 'pendiente';
            $msg_error = $curlError ?: ('HTTP ' . $httpCode . ': ' . substr($response, 0, 500));
            $db->prepare("UPDATE pinterest_queue SET estado=?, intentos=?, mensaje_error=? WHERE id=?")
               ->execute([$nuevo_estado, $nuevos_intentos, $msg_error, $pin['id']]);
            $errores[] = ['id' => $pin['id'], 'sku' => $pin['sku_ref'], 'error' => $msg_error];
        }

        // Respetar rate limit
        if (count($pins) > 1) sleep(3);
    }

    jsonSalida(['ok' => true, 'publicados' => $publicados, 'errores' => $errores, 'limite_diario' => $pins_por_dia, 'ya_publicados_hoy' => $pubHoy + $publicados]);

} catch (Exception $e) {
    jsonSalida(['ok' => false, 'error' => $e->getMessage()]);
}
?>
