<?php
/**
 * caja.php — Landing page de QR de caja de almacén
 */
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

// Habilitar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ── Sesi\u00f3n ligera (sin forzar login) ────────────────────────
session_start();
require_once __DIR__ . '/includes/paths.php';   // $base_path, $site_url
require_once __DIR__ . '/api/config.php';        // conectar()

$celda_id = trim($_GET['id'] ?? '');
if (!$celda_id || !preg_match('/^[A-Za-z0-9\-_]{1,20}$/', $celda_id)) {
    http_response_code(400);
    die('Código de caja no válido.');
}

$celda_id = strtoupper($celda_id);

// ── ¿Hay sesión de admin activa? ────────────────────────────
$es_admin = false;
if (!empty($_SESSION['user_id']) && !empty($_SESSION['username'])) {
    // Verificación rápida de rol
    try {
        $db_check = conectar();
        $stmtR = $db_check->prepare("SELECT role FROM user_currencies WHERE account_id = ?");
        $stmtR->execute([$_SESSION['user_id']]);
        $row = $stmtR->fetch();
        if ($row && in_array($row['role'], ['admin', 'moderator'])) {
            $es_admin = true;
        }
    } catch (Exception $e) { /* sin BD, no admin */ }
}

// ── Con sesión admin → redirect al gestor ──────────────────
if ($es_admin) {
    $url = $site_url . 'pages/stock.php?tab=5.4&celda=' . urlencode($celda_id);
    header('Location: ' . $url);
    exit();
}

// ── Sin sesión → cargar contenido de la celda ──────────────
$contenido = ['materiales' => [], 'articulos' => [], 'posicion' => null];
try {
    $db = conectar();
    // Obtener posición
    $stmtPos = $db->prepare("SELECT * FROM almacen_posiciones WHERE etiqueta = ? LIMIT 1");
    $stmtPos->execute([$celda_id]);
    $pos = $stmtPos->fetch();
    $contenido['posicion'] = $pos;

    if ($pos) {
        // Materiales
        $stmtM = $db->prepare("SELECT REF_MAT, NOMBRE, STOCK_ACTUAL, UNIDAD, estado_stock, FOTO FROM materiales WHERE ubicacion = ?");
        $stmtM->execute([$celda_id]);
        $contenido['materiales'] = $stmtM->fetchAll();

        // Artículos (si existe UBICACION_MAP)
        try {
            $stmtA = $db->prepare("
                SELECT a.referencia, a.nombre, a.foto_portada,
                       CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK),'NO'),''),0) AS UNSIGNED) as stock_final,
                       p.STOCK_FISICO as stock_semi
                FROM articulos a JOIN productos p ON a.referencia = p.SKU_REF
                WHERE p.UBICACION_MAP = ?
            ");
            $stmtA->execute([$celda_id]);
            $contenido['articulos'] = $stmtA->fetchAll();
        } catch (Exception $e) { }

        // Nombre de estantería
        if ($pos['estanteria_id']) {
            $stmtE = $db->prepare("SELECT nombre FROM almacen_estanterias WHERE id = ?");
            $stmtE->execute([$pos['estanteria_id']]);
            $est = $stmtE->fetch();
            $contenido['estanteria'] = $est['nombre'] ?? '';
        }
    }
} catch (Exception $e) { }

$ESTADO_LABEL = ['T' => 'Terminado', 'S' => 'Sin tintar', 'B' => 'Base/Bruto'];
$ESTADO_COLOR = ['T' => '#00ff88', 'S' => '#ffcc00', 'B' => '#4488ff'];
$ESTADO_BG    = ['T' => 'rgba(0,255,136,0.12)', 'S' => 'rgba(255,204,0,0.12)', 'B' => 'rgba(68,136,255,0.12)'];

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function resolverRuta($foto, $base_path) {
    if (!$foto) return $base_path . 'img/logo.png';
    $clean = str_replace('\\', '/', $foto);
    // Caso ruta absoluta Windows (ej: C:/...)
    if (preg_match('/^[a-zA-Z]:\//', $clean)) {
        $idx = stripos($clean, 'uploads/');
        if ($idx !== false) return $base_path . substr($clean, $idx);
        return $base_path . 'uploads/articulos/materiales/' . basename($clean);
    }
    if (strpos($clean, 'uploads/') === 0) return $base_path . $clean;
    return $base_path . 'uploads/' . $clean;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Caja <?php echo h($celda_id); ?> — Noxertez</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #0a0c14;
    color: #e2e8f0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
    padding: 0 0 80px;
  }

  /* ── Cabecera ── */
  .cab {
    background: linear-gradient(180deg, #110a03 0%, #0a0c14 100%);
    border-bottom: 2px solid #5C3A1E;
    padding: 18px 20px 14px;
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .cab-logo { font-size: 1.6rem; }
  .cab-titulo { flex: 1; }
  .cab-titulo h1 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #d4af37;
    letter-spacing: 0.04em;
  }
  .cab-titulo small {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.35);
    display: block;
    margin-top: 2px;
  }
  .badge-caja {
    background: #5C3A1E;
    color: #d4af37;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: bold;
    letter-spacing: 0.08em;
    border: 1px solid #7a4f2a;
    flex-shrink: 0;
  }

  /* ── Contenido ── */
  .contenido { padding: 20px 16px; }
  .seccion-titulo {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255,255,255,0.35);
    margin: 0 0 10px;
  }

  .item-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .item-dot {
    width: 12px; height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .item-info { flex: 1; min-width: 0; }
  .item-nombre {
    font-size: 1.05rem;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .item-sub {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.45);
    margin-top: 3px;
  }
  .item-badge {
    font-size: 0.7rem;
    font-weight: bold;
    padding: 4px 10px;
    border-radius: 20px;
    flex-shrink: 0;
    white-space: nowrap;
  }
  .item-qty {
    font-size: 1.15rem;
    font-weight: 800;
    color: #d4af37;
    flex-shrink: 0;
    text-align: right;
  }

  .vacia {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255,255,255,0.2);
    font-size: 0.95rem;
  }
  .vacia .icon { font-size: 3rem; margin-bottom: 10px; display: block; }

  /* ── Botón login ── */
  .btn-login {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: linear-gradient(135deg, #d4af37 0%, #b8942e 100%);
    color: #000;
    text-align: center;
    padding: 18px;
    font-size: 1rem;
    font-weight: 800;
    text-decoration: none;
    letter-spacing: 0.05em;
    display: block;
    border-top: 2px solid #7a5200;
  }
  .btn-login:active { opacity: 0.85; }

  .sep { border: 0; border-top: 1px solid rgba(255,255,255,0.07); margin: 20px 0; }
  .notas-box {
    background: rgba(255,255,255,0.03);
    border-radius: 8px;
    padding: 12px;
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    margin-bottom: 14px;
    border: 1px solid rgba(255,255,255,0.06);
  }
</style>
</head>
<body>

<!-- Cabecera -->
<div class="cab">
  <div class="cab-logo">📦</div>
  <div class="cab-titulo">
    <h1>Almacén Noxertez</h1>
    <?php if (!empty($contenido['estanteria'])): ?>
      <small><?php echo h($contenido['estanteria']); ?></small>
    <?php endif; ?>
  </div>
  <div class="badge-caja"><?php echo h($celda_id); ?></div>
</div>

<!-- Contenido -->
<div class="contenido">

  <?php if (!$contenido['posicion']): ?>
    <div class="vacia">
      <span class="icon">🔍</span>
      Celda <strong><?php echo h($celda_id); ?></strong> no encontrada en el sistema.
    </div>

  <?php else: ?>

    <?php if ($contenido['posicion']['notas']): ?>
      <div class="notas-box">📝 <?php echo h($contenido['posicion']['notas']); ?></div>
    <?php endif; ?>

    <?php if (!empty($contenido['materiales'])): ?>
      <p class="seccion-titulo">Materiales (<?php echo count($contenido['materiales']); ?>)</p>
      <?php foreach ($contenido['materiales'] as $m):
        $est = $m['estado_stock'] ?? '';
        $col = $ESTADO_COLOR[$est] ?? '#555';
        $bg  = $ESTADO_BG[$est]   ?? 'rgba(255,255,255,0.04)';
        $lbl = $ESTADO_LABEL[$est] ?? '—';
      ?>
      <div class="item-card" style="background:<?php echo $bg; ?>; border-color:<?php echo $col; ?>33; gap:10px;">
        <img src="<?php echo resolverRuta($m['FOTO'], $base_path); ?>" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid rgba(255,255,255,0.1);" onerror="this.src='<?php echo $base_path; ?>img/logo.png'">
        <div class="item-info">
          <div class="item-nombre" style="font-size:0.95rem;"><?php echo h($m['NOMBRE']); ?></div>
          <div class="item-sub"><?php echo h($m['REF_MAT']); ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="item-badge" style="background:<?php echo $bg; ?>;color:<?php echo $col; ?>;border:1px solid <?php echo $col; ?>44; font-size:0.6rem; padding:2px 6px;">
            <?php echo $lbl; ?>
          </div>
          <div class="item-qty" style="margin-top:4px; font-size:1rem;">
            <?php echo number_format((float)($m['STOCK_ACTUAL'] ?? 0), 0); ?>
            <span style="font-size:0.65rem;font-weight:normal;color:rgba(255,255,255,.4);"><?php echo h($m['UNIDAD'] ?? ''); ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <hr class="sep">
    <?php endif; ?>

    <?php if (!empty($contenido['articulos'])): ?>
      <p class="seccion-titulo">Artículos terminados / semi (<?php echo count($contenido['articulos']); ?>)</p>
      <?php foreach ($contenido['articulos'] as $a): ?>
      <div class="item-card" style="gap:10px;">
        <img src="<?php echo resolverRuta($a['foto_portada'], $base_path); ?>" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid rgba(255,255,255,0.1);" onerror="this.src='<?php echo $base_path; ?>img/logo.png'">
        <div class="item-info">
          <div class="item-nombre" style="font-size:0.95rem;"><?php echo h($a['nombre']); ?></div>
          <div class="item-sub"><?php echo h($a['referencia']); ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <?php if ($a['stock_final'] > 0): ?>
            <div class="item-badge" style="background:rgba(0,255,136,.12);color:#00ff88;border:1px solid #00ff8844;margin-bottom:4px; font-size:0.6rem; padding:2px 6px;">
              T: <?php echo (int)$a['stock_final']; ?>
            </div>
          <?php endif; ?>
          <?php if ($a['stock_semi'] > 0): ?>
            <div class="item-badge" style="background:rgba(255,204,0,.12);color:#ffcc00;border:1px solid #ffcc0044; font-size:0.6rem; padding:2px 6px;">
              S: <?php echo (int)$a['stock_semi']; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

    <?php endif; ?>

    <?php if (empty($contenido['materiales']) && empty($contenido['articulos'])): ?>
      <div class="vacia">
        <span class="icon">🗃️</span>
        Esta celda está vacía.
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div><!-- /contenido -->

<!-- Botón login fijo -->
<a href="<?php echo h($base_path); ?>login" class="btn-login">
  🔐 Acceder al gestor para editar
</a>

</body>
</html>
