<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../api/config.php';
$db = conectar();

// Crear tablas si no existen
$db->exec("CREATE TABLE IF NOT EXISTS `pinterest_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku_ref` varchar(255) DEFAULT NULL,
  `imagen_url` text,
  `titulo` varchar(100) DEFAULT NULL,
  `descripcion` text,
  `tablero_categoria` varchar(255) DEFAULT NULL,
  `board_id_pinterest` varchar(255) DEFAULT NULL,
  `enlace` text,
  `pin_id_pinterest` varchar(255) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'pendiente',
  `fecha_programada` date DEFAULT NULL,
  `fecha_publicado` datetime DEFAULT NULL,
  `intentos` int(11) DEFAULT 0,
  `mensaje_error` text,
  `tipo_contenido` varchar(20) DEFAULT 'producto',
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS `pinterest_tableros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` varchar(255) NOT NULL,
  `nombre_tablero` varchar(255) DEFAULT NULL,
  `board_id` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` varchar(255) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Leer config Pinterest
$cfg = [];
$stmtC = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'pinterest_%'");
foreach ($stmtC->fetchAll() as $r) $cfg[$r['clave']] = $r['valor'];
$token_ok = !empty($cfg['pinterest_access_token']);

// Leer categorias
$categorias = $db->query("SELECT DISTINCT CATEGORIA FROM productos WHERE CATEGORIA IS NOT NULL AND CATEGORIA != '' ORDER BY CATEGORIA")->fetchAll(PDO::FETCH_COLUMN);

// Leer tableros configurados
$tableros_db = [];
$stmtT = $db->query("SELECT * FROM pinterest_tableros ORDER BY categoria");
foreach ($stmtT->fetchAll() as $t) $tableros_db[$t['categoria']] = $t;

// Estadisticas cola
$stats = $db->query("SELECT estado, COUNT(*) as c FROM pinterest_queue GROUP BY estado")->fetchAll();
$st = ['pendiente'=>0,'publicado'=>0,'error'=>0];
foreach ($stats as $s) $st[$s['estado']] = (int)$s['c'];
$total_con_foto = (int)$db->query("SELECT COUNT(*) FROM productos WHERE FOTO_PORTADA IS NOT NULL AND FOTO_PORTADA != ''")->fetchColumn();

$hoy = date('Y-m-d');
$pub_hoy = (int)$db->query("SELECT COUNT(*) FROM pinterest_queue WHERE DATE(fecha_publicado)='$hoy' AND estado='publicado'")->fetchColumn();
$pub_mes = (int)$db->query("SELECT COUNT(*) FROM pinterest_queue WHERE MONTH(fecha_publicado)=MONTH(NOW()) AND YEAR(fecha_publicado)=YEAR(NOW()) AND estado='publicado'")->fetchColumn();

$page_class = 'pinterest';
include('../includes/header.php');
?>

<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/management_style.css?v=1.1">
<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/clientes.css?v=1.1">
<style>
.tab-container{display:none}.tab-container.active{display:block}
.nav-tabs-wow{display:flex;gap:10px;margin-bottom:2rem;border-bottom:1px solid var(--border-glass);padding-bottom:10px;flex-wrap:wrap}
.tab-link-wow{padding:10px 20px;color:var(--text-gray);cursor:pointer;border-radius:8px;transition:all .3s;font-weight:bold}
.tab-link-wow.active{background:var(--accent-gold);color:#000}
.tab-link-wow:hover:not(.active){background:rgba(255,255,255,.05);color:var(--text-white)}
.stat-card{background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:12px;padding:1.2rem 1.5rem;flex:1;min-width:160px;text-align:center}
.stat-card .stat-num{font-size:2rem;font-weight:800;color:var(--accent-gold)}
.stat-card .stat-lbl{font-size:.8rem;color:var(--text-gray);margin-top:4px}
.pint-logo{display:inline-flex;align-items:center;gap:10px}
.badge-pend{background:rgba(245,158,11,.2);color:#f59e0b;border:1px solid #f59e0b;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:bold}
.badge-pub{background:rgba(16,185,129,.2);color:#10b981;border:1px solid #10b981;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:bold}
.badge-err{background:rgba(239,68,68,.2);color:#ef4444;border:1px solid #ef4444;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:bold}
.badge-cat{background:rgba(212,175,55,.15);color:var(--accent-gold);border:1px solid rgba(212,175,55,.4);padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:bold}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
.config-panel{background:rgba(255,255,255,.03);border:1px solid var(--border-glass);border-radius:12px;padding:1.5rem}
.config-panel h3{color:var(--accent-gold);margin-bottom:1.2rem;font-size:1rem;display:flex;align-items:center;gap:8px}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.badge-pub-loading{background:rgba(245,158,11,.2);color:#f59e0b;border:1px solid #f59e0b;padding:2px 8px;border-radius:20px;font-size:.75rem;animation:pulse 1s infinite}
.cal-table td{padding:6px 12px;text-align:center;border-radius:6px}
.cal-day-ok{background:rgba(212,175,55,.15);color:var(--accent-gold);font-weight:bold}
.cal-day-empty{color:rgba(255,255,255,.2)}
.filter-bar{display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;align-items:center}
.filter-bar select,.filter-bar input{background:rgba(0,0,0,.4);border:1px solid var(--border-glass);color:#fff;border-radius:8px;padding:.4rem .8rem}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}
</style>

<div class="panel-management">
<div class="panel-header-wow">
  <h1 class="pint-logo">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="#E60023"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>
    Pinterest Publisher
  </h1>
  <div style="display:flex;gap:10px;align-items:center">
    <?php if($token_ok): ?>
      <span class="badge-pub"><i class="fas fa-circle" style="font-size:.6rem"></i> Token activo</span>
    <?php else: ?>
      <span class="badge-err"><i class="fas fa-times-circle"></i> Sin configurar</span>
    <?php endif; ?>
    <button onclick="publicarCola()" class="btn-premium-wow" style="background:#E60023;display:flex;align-items:center;gap:8px">
      <i class="fas fa-play"></i> Publicar Cola de Hoy
    </button>
  </div>
</div>

<div id="resultado" style="display:none;padding:12px 16px;border-radius:8px;margin-bottom:1rem;font-weight:bold"></div>

<div class="nav-tabs-wow">
  <div class="tab-link-wow active" onclick="switchTab('tab-cfg',this)">⚙️ Configuración</div>
  <div class="tab-link-wow" onclick="switchTab('tab-import',this)">📥 Importar Productos</div>
  <div class="tab-link-wow" onclick="switchTab('tab-cola',this)">📋 Cola de Publicación</div>
  <div class="tab-link-wow" onclick="switchTab('tab-stats',this)">📊 Estadísticas</div>
</div>

<!-- TAB 1: CONFIGURACION -->
<div id="tab-cfg" class="tab-container active">
  <div class="grid-2">
    <div class="config-panel">
      <h3><i class="fas fa-key"></i> Credenciales API Pinterest</h3>
      <div class="nox-form-group">
        <label>App ID</label>
        <input type="text" id="pint_app_id" class="input-wow" style="width:100%" value="<?php echo htmlspecialchars($cfg['pinterest_app_id']??'') ?>">
      </div>
      <div class="nox-form-group">
        <label>App Secret</label>
        <input type="password" id="pint_app_secret" class="input-wow" style="width:100%" value="<?php echo htmlspecialchars($cfg['pinterest_app_secret']??'') ?>">
      </div>
      <div class="nox-form-group">
        <label>Access Token</label>
        <textarea id="pint_token" class="input-wow" rows="4" style="width:100%;font-family:monospace;font-size:.8rem"><?php echo htmlspecialchars($cfg['pinterest_access_token']??'') ?></textarea>
      </div>
      <div class="nox-form-group">
        <label>Pins por día (max 25)</label>
        <input type="number" id="pint_ppd" class="input-wow" style="width:100%" min="1" max="25" value="<?php echo (int)($cfg['pinterest_pins_por_dia']??10) ?>">
      </div>
      <div style="display:flex;gap:10px;margin-top:1rem">
        <button onclick="guardarConfig()" class="btn-premium-wow btn-gold" style="flex:2"><i class="fas fa-save"></i> Guardar configuración</button>
        <button onclick="verificarToken()" class="btn-premium-wow" style="flex:1"><i class="fas fa-search"></i> Verificar Token</button>
      </div>
      <div id="token-result" style="margin-top:1rem;font-size:.85rem"></div>
    </div>
    <div class="config-panel">
      <h3><i class="fas fa-th"></i> Mapeo de Tableros</h3>
      <p style="font-size:.8rem;color:var(--text-gray);margin-bottom:1rem">
        Para obtener el Board ID ve a <a href="https://developers.pinterest.com/tools/api-explorer/" target="_blank" style="color:var(--accent-gold)">Pinterest API Explorer</a> → GET /v5/boards con tu token.
      </p>
      <table class="table-wow" style="width:100%">
        <thead><tr><th>Categoría</th><th>Nombre tablero</th><th>Board ID</th></tr></thead>
        <tbody>
        <?php foreach($categorias as $cat):
          $td = $tableros_db[$cat] ?? [];
        ?>
          <tr>
            <td><span class="badge-cat"><?php echo htmlspecialchars($cat) ?></span></td>
            <td><input type="text" class="input-wow" style="width:100%;font-size:.8rem" data-cat="<?php echo htmlspecialchars($cat) ?>" data-field="nombre" value="<?php echo htmlspecialchars($td['nombre_tablero']??'') ?>" placeholder="Nombre tablero..."></td>
            <td><input type="text" class="input-wow" style="width:100%;font-size:.8rem" data-cat="<?php echo htmlspecialchars($cat) ?>" data-field="board_id" value="<?php echo htmlspecialchars($td['board_id']??'') ?>" placeholder="Board ID..."></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <button onclick="guardarTableros()" class="btn-premium-wow btn-gold" style="width:100%;margin-top:1rem;justify-content:center"><i class="fas fa-save"></i> Guardar tableros</button>
    </div>
  </div>
</div>

<!-- TAB 2: IMPORTAR -->
<div id="tab-import" class="tab-container">
  <div style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap">
    <div class="stat-card"><div class="stat-num"><?php echo $total_con_foto ?></div><div class="stat-lbl">Productos con foto</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#f59e0b"><?php echo $st['pendiente'] ?></div><div class="stat-lbl">En cola (pendientes)</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#10b981"><?php echo $st['publicado'] ?></div><div class="stat-lbl">Ya publicados</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#ef4444"><?php echo $st['error'] ?></div><div class="stat-lbl">Con error</div></div>
  </div>
  <div class="config-panel" style="max-width:600px">
    <h3><i class="fas fa-filter"></i> Filtros de importación</h3>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" id="imp_solo_base" checked style="accent-color:var(--accent-gold)"> Solo productos BASE</label>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" id="imp_mockup" style="accent-color:var(--accent-gold)"> Incluir mockups</label>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem">
      <div class="nox-form-group">
        <label>Categoría</label>
        <select id="imp_cat" class="input-wow" style="width:100%">
          <option value="">Todas las categorías</option>
          <?php foreach($categorias as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat) ?>"><?php echo htmlspecialchars($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="nox-form-group">
        <label>Estado producto</label>
        <select id="imp_estado" class="input-wow" style="width:100%">
          <option value="">Todos</option>
          <option value="activo">Activo</option>
          <option value="inactivo">Inactivo</option>
        </select>
      </div>
    </div>
    <button onclick="importarProductos()" class="btn-premium-wow btn-gold" style="width:100%;justify-content:center;font-size:1.1rem;padding:1rem">
      <i class="fas fa-file-import"></i> 📥 Importar a Cola
    </button>
    <div id="imp-resultado" style="margin-top:1rem"></div>
  </div>
</div>

<!-- TAB 3: COLA -->
<div id="tab-cola" class="tab-container">
  <div class="filter-bar">
    <select id="cola-estado" onchange="cargarCola(1)"><option value="">Todos los estados</option><option value="pendiente">Pendiente</option><option value="publicado">Publicado</option><option value="error">Error</option></select>
    <select id="cola-cat" onchange="cargarCola(1)">
      <option value="">Todos los tableros</option>
      <?php foreach($categorias as $cat): ?><option value="<?php echo htmlspecialchars($cat)?>"><?php echo htmlspecialchars($cat)?></option><?php endforeach; ?>
    </select>
    <input type="text" id="cola-busq" class="input-wow" placeholder="Buscar SKU o título..." oninput="debounceCargarCola()" style="flex:1;min-width:180px">
    <button onclick="limpiarErrores()" class="btn-premium-wow" style="background:#ef4444"><i class="fas fa-trash"></i> Limpiar errores</button>
    <button onclick="cargarCola(1)" class="btn-premium-wow"><i class="fas fa-sync"></i> Refrescar</button>
  </div>
  <div class="table-container-wow scroll-x-wow">
    <table class="table-wow" id="tabla-cola" style="min-width:900px">
      <thead><tr><th>Imagen</th><th>SKU</th><th>Título</th><th>Tablero</th><th>F.Programada</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="cola-tbody"><tr><td colspan="7" style="text-align:center;padding:2rem"><i class="fas fa-circle-notch" style="animation:spin 1s linear infinite;color:var(--accent-gold)"></i> Cargando...</td></tr></tbody>
    </table>
  </div>
  <div id="cola-pag" style="display:flex;gap:8px;justify-content:center;margin-top:1rem;flex-wrap:wrap"></div>
</div>

<!-- TAB 4: ESTADISTICAS -->
<div id="tab-stats" class="tab-container">
  <div style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap">
    <div class="stat-card"><div class="stat-num"><?php echo $st['pendiente']+$st['publicado']+$st['error'] ?></div><div class="stat-lbl">Total en cola</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#10b981"><?php echo $pub_hoy ?></div><div class="stat-lbl">Publicados hoy</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--accent-gold)"><?php echo $pub_mes ?></div><div class="stat-lbl">Publicados este mes</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#ef4444"><?php echo $st['error'] ?></div><div class="stat-lbl">Errores totales</div></div>
  </div>

  <div class="grid-2">
    <div class="config-panel">
      <h3><i class="fas fa-calendar"></i> Próximos 14 días</h3>
      <table class="cal-table" style="width:100%">
        <thead><tr><th>Fecha</th><th>Pins</th></tr></thead>
        <tbody>
        <?php
        for($d=0;$d<14;$d++){
          $f=date('Y-m-d',strtotime('+'.$d.' days'));
          $n=(int)$db->query("SELECT COUNT(*) FROM pinterest_queue WHERE fecha_programada='$f' AND estado='pendiente'")->fetchColumn();
        ?>
          <tr>
            <td class="<?php echo $n>0?'cal-day-ok':'cal-day-empty' ?>"><?php echo date('d/m',strtotime($f)) ?></td>
            <td class="<?php echo $n>0?'cal-day-ok':'cal-day-empty' ?>"><?php echo $n>0?$n.' pins':'-' ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <div class="config-panel">
      <h3><i class="fas fa-history"></i> Últimos 10 publicados</h3>
      <?php
      $ultimos=$db->query("SELECT * FROM pinterest_queue WHERE estado='publicado' ORDER BY fecha_publicado DESC LIMIT 10")->fetchAll();
      foreach($ultimos as $u): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-glass)">
        <img src="<?php echo htmlspecialchars($u['imagen_url']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--border-glass)" onerror="this.src='<?php echo $base_path ?>img/logo.png'">
        <div style="flex:1;min-width:0">
          <div style="font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($u['titulo']) ?></div>
          <div style="font-size:.75rem;color:var(--text-gray)"><?php echo date('d/m/Y H:i',strtotime($u['fecha_publicado'])) ?></div>
        </div>
        <?php if($u['pin_id_pinterest']): ?>
        <a href="https://www.pinterest.com/pin/<?php echo $u['pin_id_pinterest'] ?>/" target="_blank" style="color:#E60023"><i class="fas fa-external-link-alt"></i></a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div><!-- /panel-management -->

<!-- MODAL EDITAR PIN -->
<div id="modal-pin" class="modal-overlay-wow" style="display:none" onclick="if(event.target==this)this.style.display='none'">
  <div class="modal-content-wow" style="max-width:500px">
    <div class="modal-header-wow">
      <h2>Editar Pin</h2>
      <button onclick="document.getElementById('modal-pin').style.display='none'" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    <div style="padding:20px">
      <input type="hidden" id="ep-id">
      <div class="nox-form-group"><label>Título</label><input type="text" id="ep-titulo" class="input-wow" style="width:100%" maxlength="100"></div>
      <div class="nox-form-group"><label>Descripción</label><textarea id="ep-desc" class="input-wow" rows="4" style="width:100%" maxlength="800"></textarea></div>
      <div class="nox-form-group"><label>URL Imagen</label><input type="text" id="ep-img" class="input-wow" style="width:100%"></div>
      <div class="nox-form-group"><label>Fecha programada</label><input type="date" id="ep-fecha" class="input-wow" style="width:100%"></div>
      <div style="display:flex;gap:10px;margin-top:1rem">
        <button onclick="guardarPin()" class="btn-premium-wow btn-gold" style="flex:1;justify-content:center">💾 Guardar</button>
        <button onclick="document.getElementById('modal-pin').style.display='none'" class="btn-premium-wow" style="background:#4b5563">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE = "<?php echo $base_path ?>";
let colaPage = 1;
let debTimer = null;

function switchTab(id, el) {
  document.querySelectorAll('.tab-container').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.tab-link-wow').forEach(l => l.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  el.classList.add('active');
  if (id === 'tab-cola') cargarCola(1);
}

function mostrarResultado(msg, tipo) {
  const el = document.getElementById('resultado');
  const C = {ok:{bg:'rgba(16,185,129,.15)',b:'#10b981',c:'#10b981'},error:{bg:'rgba(239,68,68,.15)',b:'#ef4444',c:'#ef4444'},info:{bg:'rgba(212,175,55,.15)',b:'var(--accent-gold)',c:'var(--accent-gold)'}};
  const s = C[tipo]||C.info;
  el.style.cssText = `display:block;background:${s.bg};border:1px solid ${s.b};color:${s.c};padding:12px 16px;border-radius:8px;margin-bottom:1rem;font-weight:bold`;
  el.innerHTML = msg;
  setTimeout(()=>el.style.display='none', 7000);
}

function escH(s){if(!s)return '';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function resolverRutaPinterest(foto){
  if(!foto) return '';
  let url = foto;
  // Si estamos en localhost y la imagen viene de la web real, la traemos al local
  if(location.hostname === 'localhost' || location.hostname === '127.0.0.1'){
    url = url.replace('https://noxertez.com/', BASE);
  }
  if(url.startsWith('http')) return url; 
  const c=url.replace(/\\\\/g,'/');
  if(/^[a-zA-Z]:\//.test(c)){
    const i=c.toLowerCase().indexOf('/imagenes/');
    if(i!==-1) return BASE + c.substring(i+1); 
    return '';
  }
  if(c.startsWith('uploads/')) return BASE + c;
  return BASE + 'uploads/' + c;
}

// --- CONFIG ---
async function guardarConfig() {
  const data = {
    accion: 'guardar_config',
    app_id: document.getElementById('pint_app_id').value,
    app_secret: document.getElementById('pint_app_secret').value,
    access_token: document.getElementById('pint_token').value,
    pins_por_dia: document.getElementById('pint_ppd').value
  };
  try {
    const r = await fetch('../api/index.php?ruta=pinterest&accion=guardar_config', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const d = await r.json();
    if (d.ok) {
      mostrarResultado('✅ Configuración guardada correctamente', 'ok');
      setTimeout(() => location.reload(), 1500);
    } else {
      mostrarResultado('❌ ' + d.error, 'error');
    }
  } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
}

async function guardarTableros() {
  const filas = document.querySelectorAll('#tab-cfg tbody tr');
  const tableros = [];
  filas.forEach(tr => {
    const inputs = tr.querySelectorAll('input[data-cat]');
    if (inputs.length >= 2) {
      tableros.push({categoria: inputs[0].dataset.cat, nombre: inputs[0].value, board_id: inputs[1].value});
    }
  });
  try {
    const r = await fetch('../api/index.php?ruta=pinterest&accion=guardar_tableros', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tableros})});
    const d = await r.json();
    if (d.ok) {
      mostrarResultado('✅ Tableros guardados', 'ok');
      setTimeout(() => location.reload(), 1500);
    } else {
      mostrarResultado('❌ ' + d.error, 'error');
    }
  } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
}

async function verificarToken() {
  const token = document.getElementById('pint_token').value.trim();
  if (!token) { mostrarResultado('⚠️ Introduce el token primero', 'error'); return; }
  const el = document.getElementById('token-result');
  el.innerHTML = '<i class="fas fa-circle-notch" style="animation:spin 1s linear infinite"></i> Verificando...';
  try {
    const r = await fetch('https://api.pinterest.com/v5/user_account', {headers:{Authorization:'Bearer '+token}});
    const d = await r.json();
    if (r.ok && d.username) {
      el.innerHTML = `<span style="color:#10b981">✅ Token válido — Usuario: <strong>${escH(d.username)}</strong></span>`;
    } else {
      el.innerHTML = `<span style="color:#ef4444">❌ Token inválido: ${escH(JSON.stringify(d))}</span>`;
    }
  } catch(e) { el.innerHTML = `<span style="color:#ef4444">❌ Error de red: ${escH(e.message)}</span>`; }
}

// --- IMPORTAR ---
async function importarProductos() {
  const btn = event.target;
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-circle-notch" style="animation:spin 1s linear infinite"></i> Importando...';
  btn.disabled = true;
  const body = {
    solo_base: document.getElementById('imp_solo_base').checked,
    con_mockup: document.getElementById('imp_mockup').checked,
    categoria: document.getElementById('imp_cat').value,
    estado: document.getElementById('imp_estado').value
  };
  try {
    const r = await fetch('../api/pinterest_import.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const d = await r.json();
    const el = document.getElementById('imp-resultado');
    if (d.ok) {
      el.style.cssText = 'background:rgba(16,185,129,.15);border:1px solid #10b981;color:#10b981;padding:12px;border-radius:8px;font-weight:bold';
      el.innerHTML = `✅ Importados: <strong>${d.importados}</strong> | Omitidos (ya en cola): <strong>${d.omitidos}</strong> | Sin foto: <strong>${d.sin_foto}</strong>`;
      if (d.errores && d.errores.length) el.innerHTML += `<br><small>Errores: ${d.errores.join(', ')}</small>`;
      mostrarResultado(`✅ ${d.importados} productos importados a la cola`, 'ok');
    } else {
      el.style.cssText = 'background:rgba(239,68,68,.15);border:1px solid #ef4444;color:#ef4444;padding:12px;border-radius:8px;font-weight:bold';
      el.innerHTML = '❌ ' + (d.error || 'Error desconocido');
    }
  } catch(e) { mostrarResultado('❌ ' + e.message, 'error'); }
  btn.innerHTML = orig; btn.disabled = false;
}
</script>

<script>
// --- COLA ---
async function cargarCola(pag) {
  colaPage = pag || 1;
  const estado = document.getElementById('cola-estado').value;
  const cat = document.getElementById('cola-cat').value;
  const busq = document.getElementById('cola-busq').value;
  const tbody = document.getElementById('cola-tbody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem"><i class="fas fa-circle-notch" style="animation:spin 1s linear infinite;color:var(--accent-gold)"></i></td></tr>';
  try {
    const params = new URLSearchParams({pag: colaPage, estado, cat, busq});
    const r = await fetch('../api/index.php?ruta=pinterest&accion=lista_cola&' + params);
    const text = await r.text();
    let d;
    try {
      d = JSON.parse(text);
    } catch(e) {
      console.error("Respuesta no JSON:", text);
      tbody.innerHTML = `<tr><td colspan="7" style="color:#ef4444;text-align:center">Error del servidor (formato inválido). Revisa la consola o los logs.</td></tr>`;
      return;
    }
    if (!d.ok) { tbody.innerHTML = `<tr><td colspan="7" style="color:#ef4444;text-align:center">Error: ${escH(d.error)}</td></tr>`; return; }
    tbody.innerHTML = '';
    if (!d.items || !d.items.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;opacity:.5;padding:2rem">No hay pins en esta cola</td></tr>';
    } else {
      d.items.forEach(p => {
        const badgeClass = {pendiente:'badge-pend',publicado:'badge-pub',error:'badge-err'}[p.estado]||'badge-pend';
        const tr = document.createElement('tr');
        tr.id = 'pin-' + p.id;
        tr.innerHTML = `
          <td><img src="${escH(resolverRutaPinterest(p.imagen_url))}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--border-glass)" onerror="this.src='${BASE}img/logo.png'"></td>
          <td style="font-size:.8rem;font-family:monospace">${escH(p.sku_ref)}</td>
          <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escH(p.titulo)}">${escH(p.titulo)}</td>
          <td><span class="badge-cat">${escH(p.tablero_categoria||'—')}</span></td>
          <td style="font-size:.85rem">${escH(p.fecha_programada||'—')}</td>
          <td><span class="${badgeClass}">${escH(p.estado)}</span>${p.mensaje_error ? `<br><small style="color:#ef4444;font-size:.7rem;cursor:pointer" onclick="alert(''Error Pinterest:\\n\\n${escH(p.mensaje_error).replace(/'/g,"\\''")}'')">⚠️ ver error</small>` : ''}</td>
          <td style="display:flex;gap:5px;flex-wrap:nowrap">
            <button onclick="publicarPin(${p.id})" class="btn-premium-wow btn-gold" style="padding:.3rem .6rem;font-size:.75rem" title="Publicar ahora"><i class="fas fa-play"></i></button>
            <button onclick="editarPin(${p.id})" class="btn-premium-wow" style="padding:.3rem .6rem;font-size:.75rem;background:#4b5563" title="Editar"><i class="fas fa-edit"></i></button>
            <button onclick="eliminarPin(${p.id})" class="btn-premium-wow" style="padding:.3rem .6rem;font-size:.75rem;background:#ef4444" title="Eliminar"><i class="fas fa-trash"></i></button>
          </td>`;
        tbody.appendChild(tr);
      });
    }
    // Paginacion
    const pag = document.getElementById('cola-pag');
    pag.innerHTML = '';
    if (d.total_pages > 1) {
      for (let i = 1; i <= d.total_pages; i++) {
        const b = document.createElement('button');
        b.textContent = i;
        b.className = 'btn-premium-wow' + (i === colaPage ? ' btn-gold' : '');
        b.style.cssText = 'padding:.3rem .7rem;font-size:.85rem';
        b.onclick = () => cargarCola(i);
        pag.appendChild(b);
      }
    }
  } catch(e) { tbody.innerHTML = `<tr><td colspan="7" style="color:#ef4444;text-align:center">Error de red: ${escH(e.message)}</td></tr>`; }
}

function debounceCargarCola() { clearTimeout(debTimer); debTimer = setTimeout(()=>cargarCola(1), 400); }

async function publicarPin(id) {
  const row = document.getElementById('pin-'+id);
  if (row) { const badges = row.querySelectorAll('span'); if(badges[0]) badges[0].className = 'badge-pub-loading'; badges[0].textContent = 'publicando...'; }
  try {
    const r = await fetch('../api/pinterest_publish.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    const d = await r.json();
    if (d.ok && d.publicados > 0) {
      mostrarResultado('✅ Pin publicado correctamente', 'ok');
    } else {
      const err = (d.errores && d.errores[0]) ? d.errores[0].error : (d.error || 'Error desconocido');
      mostrarResultado('❌ Error al publicar: ' + err, 'error');
    }
    cargarCola(colaPage);
  } catch(e) { mostrarResultado('❌ ' + e.message, 'error'); cargarCola(colaPage); }
}

async function publicarCola() {
  if (!confirm('¿Publicar todos los pins pendientes de hoy hasta el límite diario?')) return;
  mostrarResultado('<i class="fas fa-circle-notch" style="animation:spin 1s linear infinite"></i> Publicando cola de hoy...', 'info');
  try {
    const r = await fetch('../api/pinterest_publish.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({})});
    const d = await r.json();
    if (d.ok) {
      let msg = `✅ Publicados: <strong>${d.publicados}</strong> pins hoy (${d.ya_publicados_hoy}/${d.limite_diario} del límite diario)`;
      if (d.errores && d.errores.length) msg += ` | Errores: ${d.errores.length}`;
      mostrarResultado(msg, 'ok');
    } else {
      mostrarResultado('❌ ' + (d.error || 'Error'), 'error');
    }
    cargarCola(colaPage);
  } catch(e) { mostrarResultado('❌ ' + e.message, 'error'); }
}

function editarPin(id) {
  fetch('../api/index.php?ruta=pinterest&accion=get_pin&id=' + id)
    .then(r => r.json()).then(d => {
      if (!d.ok) return;
      const p = d.pin;
      document.getElementById('ep-id').value = p.id;
      document.getElementById('ep-titulo').value = p.titulo || '';
      document.getElementById('ep-desc').value = p.descripcion || '';
      document.getElementById('ep-img').value = p.imagen_url || '';
      document.getElementById('ep-fecha').value = p.fecha_programada || '';
      document.getElementById('modal-pin').style.display = 'flex';
    });
}

async function guardarPin() {
  const id = document.getElementById('ep-id').value;
  const body = {id, titulo: document.getElementById('ep-titulo').value, descripcion: document.getElementById('ep-desc').value, imagen_url: document.getElementById('ep-img').value, fecha_programada: document.getElementById('ep-fecha').value};
  try {
    const r = await fetch('../api/index.php?ruta=pinterest&accion=editar_pin', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const d = await r.json();
    mostrarResultado(d.ok ? '✅ Pin actualizado' : '❌ ' + d.error, d.ok ? 'ok' : 'error');
    if (d.ok) { document.getElementById('modal-pin').style.display = 'none'; cargarCola(colaPage); }
  } catch(e) { mostrarResultado('❌ ' + e.message, 'error'); }
}

async function eliminarPin(id) {
  if (!confirm('¿Eliminar este pin de la cola?')) return;
  try {
    const r = await fetch('../api/index.php?ruta=pinterest&accion=eliminar_pin', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    const d = await r.json();
    mostrarResultado(d.ok ? '✅ Pin eliminado' : '❌ ' + d.error, d.ok ? 'ok' : 'error');
    if (d.ok) cargarCola(colaPage);
  } catch(e) { mostrarResultado('❌ ' + e.message, 'error'); }
}

async function limpiarErrores() {
  if (!confirm('¿Eliminar todos los pins con error?')) return;
  try {
    const r = await fetch('../api/index.php?ruta=pinterest&accion=limpiar_errores', {method:'POST'});
    const d = await r.json();
    mostrarResultado(d.ok ? `✅ ${d.eliminados} pins con error eliminados` : '❌ ' + d.error, d.ok ? 'ok' : 'error');
    if (d.ok) cargarCola(colaPage);
  } catch(e) { mostrarResultado('❌ ' + e.message, 'error'); }
}
</script>

<?php require_once('../includes/footer.php'); ?>
