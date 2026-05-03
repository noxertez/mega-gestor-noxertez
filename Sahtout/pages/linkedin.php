<!-- DEBUG_NOXERTEZ_VERSION_7 -->
<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';

// FORZAR LOGIN si no hay sesión
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $base_path . 'login?error=invalid_session');
    exit();
}

require_once '../api/config.php';
$db = conectar();

// Crear tabla linkedin_queue si no existe
$db->exec("CREATE TABLE IF NOT EXISTS `linkedin_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) DEFAULT 'manual',
  `sku_ref` varchar(255) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `texto` text,
  `imagen_url` text,
  `enlace` text,
  `estado` varchar(20) DEFAULT 'borrador',
  `fecha_programada` datetime DEFAULT NULL,
  `fecha_publicado` datetime DEFAULT NULL,
  `linkedin_post_id` varchar(255) DEFAULT NULL,
  `intentos` int(11) DEFAULT 0,
  `mensaje_error` text,
  `generado_por_ia` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Asegurar que existan las claves en configuracion
$claves_init = [
    'linkedin_client_id' => '',
    'linkedin_client_secret' => '',
    'linkedin_access_token' => '',
    'linkedin_refresh_token' => '',
    'linkedin_token_expires' => '',
    'linkedin_person_urn' => '',
    'linkedin_posts_por_semana' => '3',
    'linkedin_default_tono' => 'Cercano y Artesanal',
    'linkedin_default_enfoque' => 'storytelling'
];

foreach ($claves_init as $c => $v) {
    $db->exec("INSERT IGNORE INTO configuracion (clave, valor) VALUES ('$c', '$v')");
}

// Leer configuracion
$cfg = [];
$stmtC = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'linkedin_%'");
foreach ($stmtC->fetchAll() as $r) $cfg[$r['clave']] = $r['valor'];

$token_valido = false;
$dias_restantes = 0;
if (!empty($cfg['linkedin_access_token']) && !empty($cfg['linkedin_token_expires'])) {
    $expires = (int)$cfg['linkedin_token_expires'];
    if ($expires > time()) {
        $token_valido = true;
        $dias_restantes = floor(($expires - time()) / 86400);
    }
}

// Cargar productos y categorías
$productos = $db->query("SELECT SKU_REF, NOMBRE, FOTO_PORTADA, PRECIO, CATEGORIA, DESCRIPCION, COLOR FROM productos WHERE ESTADO != 'inactivo' ORDER BY NOMBRE ASC LIMIT 1000")->fetchAll();
$categorias = $db->query("SELECT DISTINCT CATEGORIA FROM productos WHERE CATEGORIA IS NOT NULL AND CATEGORIA != '' ORDER BY CATEGORIA ASC")->fetchAll(PDO::FETCH_COLUMN);

// Cargar estancias y decoraciones de mockups
$estancias = $db->query("SELECT DISTINCT estancia FROM mockups_varios WHERE estancia != '' ORDER BY estancia ASC")->fetchAll(PDO::FETCH_COLUMN);
$decoraciones = $db->query("SELECT DISTINCT decoracion FROM mockups_varios WHERE decoracion != '' ORDER BY decoracion ASC")->fetchAll(PDO::FETCH_COLUMN);

$page_class = 'linkedin-module';
include('../includes/header.php');

function resolverRutaPublica($foto) {
    if (!$foto || $foto === 'img/logo.png') return '../img/logo.png';
    $clean = str_replace('\\', '/', $foto);
    $idx = strpos(strtolower($clean), '/imagenes/');
    if ($idx !== false) {
        return '../uploads/articulos' . substr($clean, $idx);
    }
    if (strpos($clean, ':/') !== false) {
        $parts = explode('/', $clean);
        return '../uploads/articulos/imagenes/' . end($parts);
    }
    return (strpos($clean, 'uploads/') === 0) ? '../' . $clean : '../uploads/' . $clean;
}
?>

<style>
/* DISEÑO PREMIUM TOTAL - VERSIÓN 7 (LinkedIn Blue Theme) */
:root {
    --linkedin-blue: #0A66C2;
    --linkedin-dark: #001a33;
    --accent-gold: #d4af37;
    --accent-green: #10b981;
    --border-glass: rgba(255, 255, 255, 0.1);
    --text-gray: #aaa;
    --text-white: #fff;
    --bg-dark: #001a33;
    --log-bg: rgba(0, 0, 0, 0.5);
}

/* COMPACTAR PARA MONITORES PEQUEÑOS */
@media (max-height: 800px) {
    .panel-management { padding: 15px; }
    .panel-header-wow { margin-bottom: 1rem; }
    .nav-tabs-wow { margin-bottom: 1.5rem; }
}

body { background-color: var(--bg-dark) !important; color: var(--text-white); font-family: 'Segoe UI', Roboto, sans-serif; }

.panel-management { padding: 30px; min-height: 100vh; max-width: 1400px; margin: 0 auto; }
.panel-header-wow { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 1rem; }
.panel-header-wow h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-white); margin: 0; }

.btn-premium-wow { padding: 12px 24px; border-radius: 10px; border: none; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s; color: #000 !important; background: var(--accent-gold); text-decoration: none; font-size: 0.95rem; }
.btn-premium-wow:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3); }

.nav-tabs-wow { display: flex; gap: 12px; margin-bottom: 2.5rem; background: rgba(255,255,255,0.03); padding: 8px; border-radius: 14px; border: 1px solid var(--border-glass); width: fit-content; }
.tab-link-wow { padding: 10px 22px; color: var(--text-gray); cursor: pointer; border-radius: 10px; transition: all 0.3s; font-weight: 700; }
.tab-link-wow.active { background: var(--accent-gold); color: #000; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2); }

.config-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem; }
.config-card { background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-glass); border-radius: 20px; padding: 2rem; height: fit-content; }
.config-card h3 { margin-top: 0; margin-bottom: 1.5rem; color: var(--accent-gold); border-bottom: 1px solid rgba(212, 175, 55, 0.2); padding-bottom: 10px; }

.nox-form-group { margin-bottom: 1.8rem; }
.nox-form-group label { display: block; margin-bottom: 10px; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
.input-wow { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--border-glass); color: #fff; padding: 14px; border-radius: 10px; outline: none; box-sizing: border-box; }
.input-wow:focus { border-color: var(--accent-gold); background: rgba(0,0,0,0.5); }

/* Nuevo Selector con Vista Previa Mejorada */
.selector-with-preview { display: flex; gap: 15px; align-items: center; background: rgba(255,255,255,0.02); padding: 12px; border-radius: 12px; border: 1px solid var(--border-glass); }
#sku-quick-preview { width: 70px; height: 70px; min-width: 70px; border: 2px solid var(--accent-gold); border-radius: 10px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 15px rgba(212,175,55,0.2); }
#sku-preview-img { width: 100%; height: 100%; object-fit: cover; }

.img-preview-container { background: #000; border-radius: 15px; margin-top: 15px; border: 1px solid var(--border-glass); min-height: 200px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.img-preview { max-width: 100%; max-height: 400px; object-fit: contain; display: none; }

.badge-status { padding: 5px 14px; border-radius: 30px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-green { background: rgba(16, 185, 129, 0.15); color: var(--accent-green); border: 1px solid var(--accent-green); }
.badge-red { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid #ef4444; }
.badge-orange { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b; }

.tab-container { display: none; animation: fadeIn 0.4s ease-out; }
.tab-container.active { display: block; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.char-counter { text-align: right; font-size: 0.75rem; color: var(--text-gray); margin-top: 8px; font-weight: 600; }
.mode-toggle { display: flex; gap: 10px; margin-bottom: 2rem; }
.mode-btn { padding: 10px 20px; border-radius: 10px; border: 1px solid var(--border-glass); background: rgba(255, 255, 255, 0.04); color: var(--text-gray); cursor: pointer; font-weight: 800; font-size: 0.85rem; transition: all 0.3s; }
.mode-btn.active { background: var(--accent-gold); color: #000; border-color: var(--accent-gold); }

/* Estilos Tabla */
.table-wow { width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: -8px; }
.table-wow th { padding: 15px; text-align: left; color: var(--text-gray); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
.table-wow tbody tr { background: rgba(255,255,255,0.02); transition: all 0.3s; }
.table-wow tbody tr:hover { background: rgba(255,255,255,0.05); transform: scale(1.005); }
.table-wow td { padding: 15px; border-top: 1px solid var(--border-glass); border-bottom: 1px solid var(--border-glass); }
.table-wow td:first-child { border-left: 1px solid var(--border-glass); border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
.table-wow td:last-child { border-right: 1px solid var(--border-glass); border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

.badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
.type-manual { background: #374151; color: #fff; }
.type-producto { background: var(--accent-gold); color: #000; }
.type-marca { background: var(--linkedin-blue); color: white; }
.type-behind_scenes { background: #10b981; color: white; }
.type-promocion { background: #f59e0b; color: white; }

#spinner-ia { display: none; align-items: center; gap: 10px; color: var(--accent-gold); margin-top: 15px; font-weight: bold; background: rgba(212,175,55,0.1); padding: 12px; border-radius: 10px; justify-content: center; }

/* Modal */
.modal-overlay-wow { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.modal-content-wow { background: #111; border: 1px solid var(--accent-gold); border-radius: 20px; width: 90%; max-width: 600px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); max-height: 90vh; display: flex; flex-direction: column; }
.modal-body-wow { padding: 1.5rem; overflow-y: auto; flex: 1; }
.modal-header-wow { padding: 20px; border-bottom: 1px solid var(--border-glass); display: flex; justify-content: space-between; align-items: center; }
.modal-header-wow h2 { margin: 0; color: var(--accent-gold); }

.stat-card { background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-glass); border-radius: 12px; padding: 1.2rem 1.5rem; flex: 1; min-width: 160px; text-align: center; }
.stat-card .stat-num { font-size: 2rem; font-weight: 800; color: var(--accent-gold); }
.stat-card .stat-lbl { font-size: .8rem; color: var(--text-gray); margin-top: 4px; }

/* Estilos para el preview de mockups automáticos */
.auto-mockup-item { position: relative; width: 100%; height: 60px; }
.auto-mockup-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
.auto-mockup-remove { position: absolute; top: -5px; right: -5px; background: #ef4444; color: #fff; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); transition: all 0.2s; z-index: 5; }
.auto-mockup-remove:hover { transform: scale(1.2); background: #f87171; }

/* Log de Actividad Persistente */
.nox-activity-log {
    background: var(--log-bg);
    border: 1px solid var(--border-glass);
    border-radius: 12px;
    padding: 10px;
    font-family: 'Consolas', monospace;
    font-size: 0.75rem;
    height: 120px;
    overflow-y: auto;
    margin-bottom: 20px;
    color: #00ff00; /* Verde matrix para visibilidad a distancia */
    box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
}
.log-entry { margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 2px; }
.log-time { color: var(--accent-gold); margin-right: 8px; }

@media (max-width: 768px) { .config-grid { grid-template-columns: 1fr; } }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <h1 style="display:flex; align-items:center; gap:12px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A66C2">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037 -1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046 c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286z M5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
            </svg>
            LinkedIn Publisher
        </h1>
        <button onclick="publicarPendientes()" class="btn-premium-wow" style="background:var(--linkedin-blue);">
            <i class="fas fa-paper-plane"></i> Publicar pendientes de hoy
        </button>
    </div>

    <div id="resultado" style="display:none;"></div>

    <!-- LOG DE ACTIVIDAD VISIBLE A DISTANCIA -->
    <div class="nox-activity-log" id="main-activity-log">
        <div class="log-entry"><span class="log-time">[<?php echo date('H:i:s'); ?>]</span> Sistema listo. Esperando acciones...</div>
    </div>

    <div class="nav-tabs-wow">
        <div class="tab-link-wow active" onclick="switchTab('tab-redactor', this)">✍️ Redactor</div>
        <div class="tab-link-wow" onclick="switchTab('tab-cola', this)">📋 Cola de Posts</div>
        <div class="tab-link-wow" onclick="switchTab('tab-stats', this)">📊 Estadísticas</div>
        <div class="tab-link-wow" onclick="switchTab('tab-config', this)">⚙️ Configuración</div>
    </div>

    <!-- PESTAÑA 1: CONFIGURACIÓN -->
    <div id="tab-config" class="tab-container">
        <div class="config-grid">
            <div class="config-card">
                <h3>Credenciales OAuth</h3>
                <div class="nox-form-group">
                    <label>Client ID de LinkedIn</label>
                    <input type="text" id="li_client_id" class="input-wow" style="width:100%" value="<?php echo htmlspecialchars($cfg['linkedin_client_id']); ?>">
                </div>
                <div class="nox-form-group">
                    <label>Client Secret de LinkedIn</label>
                    <input type="password" id="li_client_secret" class="input-wow" style="width:100%" value="<?php echo htmlspecialchars($cfg['linkedin_client_secret']); ?>">
                </div>
                <div class="nox-form-group">
                    <label>Posts por semana (Recomendado: 3-5)</label>
                    <input type="number" id="li_pps" class="input-wow" style="width:100%" value="<?php echo (int)$cfg['linkedin_posts_por_semana']; ?>">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:1.8rem;">
                    <div class="nox-form-group">
                        <label>Tono predeterminado</label>
                        <select id="li_def_tono" class="input-wow">
                            <option value="Cercano y Artesanal" <?php echo ($cfg['linkedin_default_tono'] ?? '') == 'Cercano y Artesanal' ? 'selected' : ''; ?>>Cercano y Artesanal</option>
                            <option value="Poético y Minimalista" <?php echo ($cfg['linkedin_default_tono'] ?? '') == 'Poético y Minimalista' ? 'selected' : ''; ?>>Poético y Minimalista</option>
                            <option value="Sofisticado y Premium" <?php echo ($cfg['linkedin_default_tono'] ?? '') == 'Sofisticado y Premium' ? 'selected' : ''; ?>>Sofisticado y Premium</option>
                            <option value="Técnico y Didáctico" <?php echo ($cfg['linkedin_default_tono'] ?? '') == 'Técnico y Didáctico' ? 'selected' : ''; ?>>Técnico y Didáctico</option>
                            <option value="Inspirador / Lifestyle" <?php echo ($cfg['linkedin_default_tono'] ?? '') == 'Inspirador / Lifestyle' ? 'selected' : ''; ?>>Inspirador / Lifestyle</option>
                        </select>
                    </div>
                    <div class="nox-form-group">
                        <label>Enfoque predeterminado</label>
                        <select id="li_def_enfoque" class="input-wow">
                            <option value="storytelling" <?php echo ($cfg['linkedin_default_enfoque'] ?? '') == 'storytelling' ? 'selected' : ''; ?>>Narrativa de Marca</option>
                            <option value="lanzamiento" <?php echo ($cfg['linkedin_default_enfoque'] ?? '') == 'lanzamiento' ? 'selected' : ''; ?>>Lanzamiento / Nuevo</option>
                            <option value="interiorismo" <?php echo ($cfg['linkedin_default_enfoque'] ?? '') == 'interiorismo' ? 'selected' : ''; ?>>Interiorismo</option>
                            <option value="detalles" <?php echo ($cfg['linkedin_default_enfoque'] ?? '') == 'detalles' ? 'selected' : ''; ?>>Detalles de Autor</option>
                            <option value="material" <?php echo ($cfg['linkedin_default_enfoque'] ?? '') == 'material' ? 'selected' : ''; ?>>Nobleza Material</option>
                            <option value="refugio" <?php echo ($cfg['linkedin_default_enfoque'] ?? '') == 'refugio' ? 'selected' : ''; ?>>Refugio y Calma</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <label style="display:block; margin-bottom:8px; color:var(--text-gray);">Estado del Token:</label>
                    <?php if (!$token_valido): ?>
                        <span class="badge-status badge-red">❌ Sin token</span>
                    <?php elseif ($dias_restantes < 10): ?>
                        <span class="badge-status badge-orange">⚠️ Expira pronto (<?php echo $dias_restantes; ?> días)</span>
                    <?php else: ?>
                        <span class="badge-status badge-green">✅ Token activo (expira en <?php echo $dias_restantes; ?> días)</span>
                    <?php endif; ?>
                    
                    <?php if (!empty($cfg['linkedin_token_expires'])): ?>
                        <div style="font-size:0.75rem; color:var(--text-gray); margin-bottom:1rem;">
                            Vence el: <?php echo date('d/m/Y H:i', (int)$cfg['linkedin_token_expires']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button onclick="guardarCredenciales()" class="btn-premium-wow btn-gold">💾 Guardar credenciales</button>
                    <button onclick="autorizarLinkedIn()" class="btn-linkedin">
                        <i class="fab fa-linkedin"></i> Autorizar con LinkedIn
                    </button>
                    <?php if (!empty($cfg['linkedin_refresh_token'])): ?>
                        <button onclick="renovarToken()" class="btn-premium-wow" style="background:#4b5563">🔄 Renovar token</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="config-card">
                <h3>Info de cuenta</h3>
                <div style="text-align:center; padding:1rem; border-bottom:1px solid var(--border-glass); margin-bottom:1.5rem;">
                    <button onclick="verificarCuenta()" class="btn-premium-wow" style="margin-bottom:1rem;">👤 Verificar cuenta</button>
                    <div id="perfil-info" style="color:var(--text-gray); font-size:0.9rem;">
                        <?php if (!empty($cfg['linkedin_person_urn'])): ?>
                            <p><strong>URN detectada:</strong> <br><code><?php echo htmlspecialchars($cfg['linkedin_person_urn']); ?></code></p>
                        <?php else: ?>
                            <p>No se ha verificado la cuenta todavía.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="font-size:0.85rem; color:var(--text-gray);">
                    <h4 style="color:var(--text-white); margin-bottom:8px;">Instrucciones de configuración:</h4>
                    <ol style="padding-left:1.2rem; line-height:1.6;">
                        <li>Ir a <a href="https://linkedin.com/developers" target="_blank" style="color:var(--accent-gold)">linkedin.com/developers</a></li>
                        <li>Crear app y añadir productos "Share on LinkedIn" y "Sign In with LinkedIn using OpenID Connect".</li>
                        <li>En la pestaña <strong>Auth</strong>: copiar Client ID y Client Secret.</li>
                        <li>Registrar este Redirect URI: <br><code style="word-break:break-all;">https://noxertez.com/Sahtout/api/linkedin_oauth.php</code></li>
                        <li>Guardar aquí y pulsar "Autorizar con LinkedIn".</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 2: REDACTOR -->
    <div id="tab-redactor" class="tab-container active">
        <div class="mode-toggle">
            <button class="mode-btn active" onclick="setModoRedactor('manual', this)">✍️ Manual</button>
            <button class="mode-btn" onclick="setModoRedactor('ia', this)">🤖 Con IA</button>
            <button class="mode-btn" onclick="setModoRedactor('automatico', this)">⚡ Automático</button>
        </div>

        <div class="config-grid">
            <!-- Columna Formulario -->
            <div class="config-card">
                <div id="form-manual">
                    <div class="nox-form-group">
                        <label>Tipo de post</label>
                        <select id="li_tipo" class="input-wow" style="width:100%" onchange="checkTipoProducto()">
                            <option value="manual">Otro / General</option>
                            <option value="producto">Producto</option>
                            <option value="marca">Marca / Historia</option>
                            <option value="behind_scenes">Behind the scenes</option>
                            <option value="promocion">Promoción</option>
                        </select>
                    </div>

                    <div class="nox-form-group" id="group-producto" style="display:none;">
                        <label>Filtrar por Categoría</label>
                        <select id="li_filtro_cat" class="input-wow" style="width:100%; margin-bottom:10px;" onchange="filtrarProductosSelector()">
                            <option value="">-- Todas las categorías --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Buscar Producto</label>
                        <input type="text" id="li_filtro_txt" class="input-wow" style="width:100%; margin-bottom:10px;" placeholder="Escribe para buscar..." oninput="filtrarProductosSelector()">

                        <label>Seleccionar Producto</label>
                        <div class="selector-with-preview">
                            <select id="li_sku" class="input-wow" style="flex:1; min-width:0;" onchange="cargarDatosProducto(this.value)">
                                <option value="">-- Elige un producto --</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['SKU_REF']); ?>" 
                                            data-nombre="<?php echo htmlspecialchars($p['NOMBRE']); ?>"
                                            data-categoria="<?php echo htmlspecialchars($p['CATEGORIA']); ?>"
                                            data-foto="<?php echo htmlspecialchars(resolverRutaPublica($p['FOTO_PORTADA'])); ?>">
                                        <?php echo htmlspecialchars($p['SKU_REF'] . ' - ' . $p['NOMBRE']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="sku-quick-preview">
                                <img id="sku-preview-img" src="" style="display:none;">
                                <span id="sku-preview-placeholder">SIN FOTO</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="ia-options" style="display:none;">
                    <div class="nox-form-group">
                        <label>Instrucciones adicionales para Gemini</label>
                        <textarea id="li_ia_contexto" class="input-wow" style="width:100%" rows="3" placeholder="Ej: Menciona que es para el día del padre..."></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:1.5rem;">
                        <div class="nox-form-group" style="margin-bottom:0;">
                            <label>Tono</label>
                            <select id="li_ia_tono" class="input-wow" style="width:100%">
                                <option value="Cercano y Artesanal">Cercano y Artesanal</option>
                                <option value="Poético y Minimalista">Poético y Minimalista</option>
                                <option value="Sofisticado y Premium">Sofisticado y Premium</option>
                                <option value="Técnico y Didáctico">Técnico y Didáctico</option>
                                <option value="Inspirador / Lifestyle">Inspirador / Lifestyle</option>
                            </select>
                        </div>
                        <div class="nox-form-group" style="margin-bottom:0;">
                            <label>Enfoque</label>
                            <select id="li_ia_enfoque" class="input-wow" style="width:100%">
                                <option value="storytelling">Narrativa de Marca</option>
                                <option value="lanzamiento">Lanzamiento / Nuevo</option>
                                <option value="interiorismo">Interiorismo</option>
                                <option value="detalles">Detalles de Autor</option>
                                <option value="material">Nobleza Material</option>
                                <option value="refugio">Refugio y Calma</option>
                            </select>
                        </div>
                    </div>
                    <button onclick="generarConIA()" class="btn-premium-wow btn-gold" style="width:100%; justify-content:center;">
                        <i class="fas fa-robot"></i> 🤖 Generar con Gemini
                    </button>
                    <div id="spinner-ia"><i class="fas fa-circle-notch fa-spin"></i> Pensando con Gemini...</div>
                </div>

                <div id="ia-auto-options" style="display:none;">
                    <div class="nox-form-group">
                        <label>Filtros para Mockups</label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <select id="li_auto_cat" class="input-wow" onchange="previewAutoMockups()">
                                <option value="">Categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="li_auto_estancia" class="input-wow" onchange="previewAutoMockups()">
                                <option value="">Estancia...</option>
                                <?php foreach ($estancias as $e): ?>
                                    <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <select id="li_auto_deco" class="input-wow" onchange="previewAutoMockups()">
                                <option value="">Decoración...</option>
                                <?php foreach ($decoraciones as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" id="li_auto_cantidad" class="input-wow" placeholder="Cantidad (ej: 10)" value="10" oninput="previewAutoMockups()">
                        </div>
                    </div>

                    <!-- Grid de Preview de Mockups -->
                    <div id="auto-mockups-preview" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); gap:10px; max-height:200px; overflow-y:auto; background:rgba(0,0,0,0.2); padding:10px; border-radius:10px; margin-bottom:1.5rem; border:1px solid var(--border-glass);">
                        <div style="grid-column:1/-1; text-align:center; color:var(--text-gray); font-size:0.8rem;">Selecciona filtros para ver mockups</div>
                    </div>

                    <div class="nox-form-group">
                        <label>Instrucciones para Gemini</label>
                        <textarea id="li_auto_contexto" class="input-wow" style="width:100%" rows="3" placeholder="Contexto para todos los posts..."></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:1.5rem;">
                        <div class="nox-form-group" style="margin-bottom:0;">
                            <label>Tono</label>
                            <select id="li_auto_tono" class="input-wow" style="width:100%">
                                <option value="Cercano y Artesanal">Cercano y Artesanal</option>
                                <option value="Poético y Minimalista">Poético y Minimalista</option>
                                <option value="Sofisticado y Premium">Sofisticado y Premium</option>
                                <option value="Técnico y Didáctico">Técnico y Didáctico</option>
                                <option value="Inspirador / Lifestyle">Inspirador / Lifestyle</option>
                            </select>
                        </div>
                        <div class="nox-form-group" style="margin-bottom:0;">
                            <label>Enfoque</label>
                            <select id="li_auto_enfoque" class="input-wow" style="width:100%">
                                <option value="storytelling">Narrativa de Marca</option>
                                <option value="lanzamiento">Lanzamiento / Nuevo</option>
                                <option value="interiorismo">Interiorismo</option>
                                <option value="detalles">Detalles de Autor</option>
                                <option value="material">Nobleza Material</option>
                                <option value="refugio">Refugio y Calma</option>
                            </select>
                        </div>
                    </div>

                    <div style="background:rgba(212,175,55,0.05); padding:15px; border-radius:12px; border:1px solid var(--border-glass); margin-bottom:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span style="font-size:0.85rem; font-weight:bold;">Llamadas API: <span id="api-call-count" style="color:var(--accent-gold);">0</span></span>
                            <span id="auto-progress-text" style="font-size:0.85rem; color:var(--text-gray);">Esperando...</span>
                        </div>
                        <div style="width:100%; height:8px; background:rgba(255,255,255,0.05); border-radius:10px; overflow:hidden;">
                            <div id="auto-progress-bar" style="width:0%; height:100%; background:var(--accent-gold); transition:width 0.3s;"></div>
                        </div>
                    </div>

                    <button id="btn-auto-programar" onclick="programarAutomatico()" class="btn-premium-wow btn-gold" style="width:100%; justify-content:center;">
                        <i class="fas fa-magic"></i> ✨ Programar Automáticamente (3/día)
                    </button>
                    <div id="spinner-auto" style="display:none; align-items:center; gap:10px; color:var(--accent-gold); margin-top:15px; font-weight:bold; background:rgba(212,175,55,0.1); padding:12px; border-radius:10px; justify-content:center;">
                        <i class="fas fa-circle-notch fa-spin"></i> Procesando posts...
                    </div>
                </div>

                <div id="li-texto-group" class="nox-form-group" style="margin-top:1.5rem;">
                    <label>Texto del post</label>
                    <textarea id="li_texto" class="input-wow" style="width:100%" rows="8" maxlength="3000" oninput="actualizarContador()"></textarea>
                    <div class="char-counter"><span id="char-count">0</span>/3000</div>
                </div>
            </div>

            <!-- Columna Multimedia y Acciones -->
            <div class="config-card">
                <div class="nox-form-group">
                    <label>URL de Imagen (Opcional)</label>
                    <input type="text" id="li_imagen_url" class="input-wow" style="width:100%" placeholder="uploads/..." oninput="previewImagen(resolverRutaJS(this.value))">
                    <div class="img-preview-container">
                        <img id="img-preview" class="img-preview">
                        <div id="img-preview-empty" style="color:var(--text-gray); font-size:0.8rem;">Vista previa de la imagen</div>
                    </div>
                </div>
                
                <div class="nox-form-group">
                    <label>Enlace (Opcional)</label>
                    <input type="text" id="li_enlace" class="input-wow" style="width:100%" placeholder="https://noxertez.com/...">
                </div>

                <div class="nox-form-group">
                    <label>Programar para:</label>
                    <input type="datetime-local" id="li_fecha" class="input-wow" style="width:100%" value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>

                <div style="display:flex; flex-direction:column; gap:10px; margin-top:2rem;">
                    <div style="display:flex; gap:10px;">
                        <button onclick="guardarPost('borrador')" class="btn-premium-wow" style="flex:1; background:#4b5563;">💾 Guardar borrador</button>
                        <button onclick="guardarPost('pendiente')" class="btn-premium-wow btn-gold" style="flex:1;">📅 Programar</button>
                    </div>
                    <button onclick="publicarAhora()" class="btn-premium-wow" style="background:var(--linkedin-blue);">
                        <i class="fas fa-play"></i> ▶ Publicar ahora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑA 3: COLA DE POSTS -->
    <div id="tab-cola" class="tab-container">
        <div style="display:flex; gap:10px; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center;">
            <select id="filtro-estado" class="input-wow" onchange="cargarCola()">
                <option value="">Todos los estados</option>
                <option value="borrador">Borrador</option>
                <option value="pendiente">Pendiente</option>
                <option value="publicado">Publicado</option>
                <option value="error">Error</option>
            </select>
            <input type="text" id="busq-cola" class="input-wow" placeholder="Buscar..." oninput="cargarCola()">
            <button onclick="cargarCola()" class="btn-premium-wow"><i class="fas fa-sync"></i></button>
        </div>

        <div class="table-container-wow scroll-x-wow">
            <table class="table-wow">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Preview</th>
                        <th>Imagen</th>
                        <th>F. Programada</th>
                        <th>Estado</th>
                        <th>IA</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-cola">
                    <!-- Se carga via AJAX -->
                </tbody>
            </table>
        </div>
        <div id="paginacion-cola" style="margin-top:1rem; display:flex; gap:5px; justify-content:center;"></div>
    </div>

    <!-- PESTAÑA 4: ESTADÍSTICAS -->
    <div id="tab-stats" class="tab-container">
        <div style="display:flex; gap:1rem; margin-bottom:2rem; flex-wrap:wrap;" id="stats-cards">
            <!-- Cargado via AJAX -->
        </div>

        <div class="config-grid">
            <div class="config-card">
                <h3>📅 Próximos 14 días</h3>
                <div id="calendario-stats"></div>
            </div>
            <div class="config-card">
                <h3>🔗 Últimos publicados</h3>
                <div id="ultimos-publicados"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div id="modal-edit" class="modal-overlay-wow" style="display:none;" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content-wow" style="max-width:600px;">
        <div class="modal-header-wow">
            <h2>Editar Post</h2>
            <button onclick="document.getElementById('modal-edit').style.display='none'">&times;</button>
        </div>
        <div class="modal-body-wow">
            <input type="hidden" id="edit-id">
            <div class="nox-form-group">
                <label>Texto</label>
                <textarea id="edit-texto" class="input-wow" style="width:100%" rows="6"></textarea>
            </div>
            <div class="nox-form-group">
                <label>Fecha Programada</label>
                <input type="datetime-local" id="edit-fecha" class="input-wow" style="width:100%">
            </div>
            <div class="nox-form-group">
                <label>URL Imagen / Ruta</label>
                <input type="text" id="edit-imagen" class="input-wow" style="width:100%" oninput="document.getElementById('edit-preview').src = resolverRutaJS(this.value)">
                <div class="img-preview-container" style="min-height:100px; margin-top:10px;">
                    <img id="edit-preview" class="img-preview" style="display:block; max-height:150px;">
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:1rem;">
                <button onclick="guardarEdicion()" class="btn-premium-wow btn-gold" style="flex:1.5;">💾 Guardar Cambios</button>
                <button onclick="regenerarIA(document.getElementById('edit-id').value)" class="btn-premium-wow" style="background:var(--linkedin-blue); flex:1;"><i class="fas fa-magic"></i> IA</button>
                <button onclick="document.getElementById('modal-edit').style.display='none'" class="btn-premium-wow" style="background:#4b5563; flex:1;">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/linkedin.js?v=<?php echo time(); ?>"></script>

