<?php
define('ALLOWED_ACCESS', true);
require_once 'config.php';
$db = conectar();

$accion = $_REQUEST['accion'] ?? '';

if ($accion === 'listar') {
    $where = ["1=1"]; $params = [];
    if (!empty($_GET['tipo'])) { $where[] = "tipo = ?"; $params[] = $_GET['tipo']; }
    if (!empty($_GET['marca'])) {
        $m = $_GET['marca'];
        if($m === 'NOXERTEZ') $where[] = "marca_noxertez = 1";
        if($m === 'CANDLEHOLDER') $where[] = "marca_candleholder = 1";
        if($m === 'ZEN') $where[] = "marca_zen = 1";
    }
    
    // FILTRO POR RED SOCIAL (Nombres exactos de tu DB)
    if (!empty($_GET['social'])) {
        $s = $_GET['social']; 
        if ($s === 'ig') $where[] = "publicado_instagram IS NOT NULL";
        if ($s === 'li') $where[] = "publicado_linkedin IS NOT NULL";
        if ($s === 'pi') $where[] = "publicado_pinterest IS NOT NULL";
    }

    if (!empty($_GET['estancia'])) { $where[] = "estancia = ?"; $params[] = $_GET['estancia']; }
    if (!empty($_GET['estilo'])) { $where[] = "estilo = ?"; $params[] = $_GET['estilo']; }
    if (!empty($_GET['decoracion'])) { $where[] = "decoracion = ?"; $params[] = $_GET['decoracion']; }
    if (!empty($_GET['buscar'])) { $where[] = "(archivo LIKE ? OR estancia LIKE ?)"; $params[] = "%".$_GET['buscar']."%"; $params[] = "%".$_GET['buscar']."%"; }

    $sql = "SELECT m.*, (SELECT GROUP_CONCAT(sku) FROM mockups_vinculaciones WHERE mockup_id = m.id) as skus 
            FROM mockups_varios m WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($accion === 'uno') {
    $stmt = $db->prepare("SELECT * FROM mockups_varios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

if ($accion === 'editar') {
    // Al ser datetime, si el checkbox está marcado ponemos la fecha actual, si no NULL
    $ig = !empty($_POST['publicado_instagram']) ? date('Y-m-d H:i:s') : null;
    $li = !empty($_POST['publicado_linkedin']) ? date('Y-m-d H:i:s') : null;
    $pi = !empty($_POST['publicado_pinterest']) ? date('Y-m-d H:i:s') : null;

    $sql = "UPDATE mockups_varios SET 
            estancia=?, estilo=?, luz=?, decoracion=?, formato=?, color_dominante=?, temporada=?, calidad=?, notas=?,
            marca_noxertez=?, marca_candleholder=?, marca_zen=?,
            publicado_instagram=?, publicado_linkedin=?, publicado_pinterest=?
            WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $_POST['estancia'], $_POST['estilo'], $_POST['luz'], $_POST['decoracion'], $_POST['formato'], $_POST['color_dominante'], $_POST['temporada'], $_POST['calidad'], $_POST['notas'],
        $_POST['marca_noxertez'], $_POST['marca_candleholder'], $_POST['marca_zen'],
        $ig, $li, $pi,
        $_POST['id']
    ]);
    echo json_encode(['status'=>'ok']);
}

if ($accion === 'vincular_multiple') {
    $db->prepare("UPDATE mockups_varios SET veces_usado = veces_usado + 1, ultima_vez_usado = NOW() WHERE id = ?")->execute([$_POST['id']]);
    $stmt = $db->prepare("INSERT IGNORE INTO mockups_vinculaciones (mockup_id, sku) VALUES (?, ?)");
    $stmt->execute([$_POST['id'], $_POST['sku']]);
    echo json_encode(['status'=>'ok']);
}

if ($accion === 'desvincular_multiple') {
    $stmt = $db->prepare("DELETE FROM mockups_vinculaciones WHERE mockup_id = ? AND sku = ?");
    $stmt->execute([$_POST['id'], $_POST['sku']]);
    echo json_encode(['status'=>'ok']);
}

if ($accion === 'eliminar') {
    $stmt = $db->prepare("SELECT ruta FROM mockups_varios WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $ruta = $stmt->fetchColumn();
    if ($ruta && file_exists('../' . $ruta)) unlink('../' . $ruta);
    $db->prepare("DELETE FROM mockups_varios WHERE id = ?")->execute([$_POST['id']]);
    $db->prepare("DELETE FROM mockups_vinculaciones WHERE mockup_id = ?")->execute([$_POST['id']]);
    echo json_encode(['status'=>'ok']);
}
