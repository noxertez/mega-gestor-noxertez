<?php
define('ALLOWED_ACCESS', true);
require_once '../api/config.php';
$db = conectar();

// Función de apoyo para parsear metadatos
function parseMetadata($file) {
    $base = pathinfo($file, PATHINFO_FILENAME);
    // Limpieza de sufijos comunes de mockup
    $base = preg_replace('/_mockup[-_]\d+.*$/i', '', $base);
    $base = str_replace('_watermarked', '', $base);
    
    $parts = explode('_', $base);
    
    // 1. Estancia (siempre la primera parte)
    $estancia = isset($parts[0]) ? str_replace('-', ' ', $parts[0]) : '';
    // Pequeños ajustes para frases largas de IA
    if (strlen($estancia) > 60) {
        if (stripos($estancia, 'cafeter') !== false) $estancia = 'Cafetería Realista';
        else if (stripos($estancia, 'tienda') !== false) $estancia = 'Tienda/Boutique';
    }

    // 2. Luz (segunda parte)
    $luz = isset($parts[1]) ? str_replace('-', ' ', $parts[1]) : '';

    // 3. Formato / Vista (Buscamos palabras clave de cámara)
    $formato = '';
    $decoracion = '';
    
    $vistas_keywords = ['eye level', 'low angle', 'wide shot', 'close up', 'bird view', 'top view', 'perspective'];
    
    // Recorremos el resto de partes para clasificar
    for ($i = 2; $i < count($parts); $i++) {
        $cleanPart = str_replace('-', ' ', $parts[$i]);
        
        // ¿Es una vista/formato?
        $isView = false;
        foreach ($vistas_keywords as $kv) {
            if (stripos($cleanPart, $kv) !== false) {
                $formato = $cleanPart;
                $isView = true;
                break;
            }
        }
        
        if (!$isView) {
            // Es decoración o extras
            if (empty($decoracion)) $decoracion = $cleanPart;
            else $decoracion .= ' (' . $cleanPart . ')';
        }
    }

    return [
        'estancia'   => substr(ucfirst($estancia), 0, 100),
        'luz'        => substr(ucfirst($luz), 0, 100),
        'formato'    => substr(ucfirst($formato), 0, 100),
        'decoracion' => substr(ucfirst($decoracion), 0, 100),
    ];
}

$categorias_rows = $db->query("SELECT DISTINCT categoria FROM articulos WHERE categoria != '' ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);
$estancias_rows  = $db->query("SELECT DISTINCT estancia   FROM mockups_varios WHERE estancia   != '' ORDER BY estancia")->fetchAll(PDO::FETCH_COLUMN);
$estilos_rows    = $db->query("SELECT DISTINCT estilo     FROM mockups_varios WHERE estilo     != '' ORDER BY estilo")->fetchAll(PDO::FETCH_COLUMN);
$decos_rows      = $db->query("SELECT DISTINCT decoracion FROM mockups_varios WHERE decoracion != '' ORDER BY decoracion")->fetchAll(PDO::FETCH_COLUMN);

if (isset($_GET['action']) && $_GET['action'] === 'get_articles') {
    header('Content-Type: application/json');
    $cat = $_GET['categoria'] ?? ''; $soloBase = ($_GET['solo_base'] ?? 'true') === 'true';
    $where = ["1=1"]; $params = [];
    if ($cat !== '__TODAS__' && !empty($cat)) { $where[] = "categoria = ?"; $params[] = $cat; }
    if ($soloBase) $where[] = "(es_variante = 'BASE' OR referencia REGEXP 'P01$' OR referencia REGEXP 'P01-')";
    $sql = "SELECT referencia, nombre, foto_portada FROM articulos WHERE " . implode(" AND ", $where) . " ORDER BY referencia ASC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($articulos as &$art) {
        $sku = $art['referencia']; $skuBase = preg_replace('/P\d+.*$/', '', $sku);
        $stmtM = $db->prepare("SELECT id, ruta, tipo, 0 as manual FROM mockups_varios WHERE asignado_a_sku = ? OR asignado_a_sku LIKE ? OR archivo LIKE ? UNION SELECT mv.id, mv.ruta, mv.tipo, 1 as manual FROM mockups_varios mv JOIN mockups_vinculaciones v ON mv.id = v.mockup_id WHERE v.sku = ?");
        $stmtM->execute([$sku, $skuBase . '%', '%' . $skuBase . '%', $sku]);
        $art['local_mockups'] = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['articulos' => $articulos]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json'); $count = 0;
    if (!is_dir('../uploads/videos_varios')) mkdir('../uploads/videos_varios', 0777, true);
    foreach ($_FILES['files']['name'] as $i => $name) {
        $tmp = $_FILES['files']['tmp_name'][$i]; $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $tipo = in_array($ext, ['mp4','mov','avi','mkv']) ? 'video' : 'imagen';
        $subFolder = ($tipo === 'video') ? 'videos_varios' : 'mockups_varios';
        $dest = '../uploads/' . $subFolder . '/' . $name;
        if (move_uploaded_file($tmp, $dest)) {
            $meta = parseMetadata($name); $rutaRel = 'uploads/' . $subFolder . '/' . $name;
            
            // Si es vídeo, forzamos etiquetas genéricas para no mezclar con fotos
            if ($tipo === 'video') {
                $meta['estancia'] = 'Mix / Varias';
                $meta['formato'] = 'Reel / Vertical';
                $meta['decoracion'] = 'Estilos mezclados';
            }

            $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, estancia, luz, formato, decoracion, calidad) VALUES (?,?,?,?,?,?,?,?)");
            $ins->execute([ $name, $rutaRel, $tipo, $meta['estancia'], $meta['luz'], $meta['formato'], $meta['decoracion'], 'publicar' ]);
            $count++;
        }
    }
    echo json_encode(['mensaje' => "¡Éxito! $count archivos procesados."]); exit;
}

$page_class = 'management-page'; require_once '../includes/header.php';
?>
<style>
:root{--bg-dark:#0f172a;--bg-card:#1e293b;--accent-gold:#d4af37;--accent-green:#27ae60;--border-glass:rgba(212,175,55,0.2);}
body{background-color:var(--bg-dark)!important;color:#ffffff;font-family:'Cinzel',serif;}
.mockups-container{padding:20px;max-width:1600px;margin:0 auto;}
.tabs-nav{display:flex;gap:20px;margin-bottom:30px;border-bottom:1px solid var(--border-glass);flex-wrap:wrap;}
.tab-btn{background:none;border:none;color:#94a3b8;font-size:1.1rem;cursor:pointer;padding:10px 20px;font-family:'UnifrakturCook',cursive;transition:.3s;}
.tab-btn.active{color:var(--accent-gold);border-bottom:2px solid var(--accent-gold);}
.tab-content{display:none;}.tab-content.active{display:block;}
.filters-bar{display:flex;gap:12px;margin-bottom:15px;flex-wrap:wrap;align-items:center;background:rgba(255,255,255,0.02);padding:15px;border-radius:12px;border:1px solid var(--border-glass);}
.search-input{background:#0f172a;border:1px solid var(--border-glass);color:#fff;padding:10px 14px;border-radius:8px;flex:1;min-width:180px;}
.select-wow{background:#0f172a;border:1px solid var(--border-glass);color:#fff;padding:9px 12px;border-radius:8px;cursor:pointer;min-width:110px;}
.filter-btn{background:var(--bg-card);border:1px solid var(--border-glass);color:#fff;padding:8px 14px;border-radius:8px;cursor:pointer;transition:.2s;}
.filter-btn.active{background:var(--accent-gold);color:#000;font-weight:700;}
.mockups-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px;}
.mockup-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:12px;overflow:hidden;transition:.3s;position:relative;}
.mockup-card:hover{transform:translateY(-4px);border-color:var(--accent-gold);}
.card-img{width:100%;height:210px;background:#000;overflow:hidden;cursor:pointer;position:relative;}
.card-img img,.card-img video{width:100%;height:100%;object-fit:cover;}
.card-body{padding:14px;}
.card-title{font-size:1rem;color:var(--accent-gold);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.thumbs{display:flex;gap:8px;margin-bottom:12px;overflow-x:auto;padding:5px;background:rgba(0,0,0,0.2);border-radius:8px;}
.thumb-wrap{position:relative;flex:0 0 50px;width:50px;height:50px;}
.thumb{width:100%;height:100%;border-radius:6px;object-fit:cover;cursor:pointer;border:1px solid var(--border-glass);}
.thumb-del{position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:0.6rem;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transition:.2s;z-index:2;}
.thumb-wrap:hover .thumb-del{opacity:1;}
.card-actions{display:flex;gap:7px;}
.btn{flex:1;padding:9px;border-radius:7px;border:1px solid var(--border-glass);background:rgba(255,255,255,0.05);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;font-size:.85rem;transition:.2s;}
.btn:hover{background:var(--accent-gold);color:#000;}
.btn-green{background:var(--accent-green);color:#fff;border-color:#1e8449;}
.btn-gold{background:var(--accent-gold);color:#000;font-weight:700;}
.modal-over{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;justify-content:center;align-items:flex-start;padding-top:40px;z-index:9000;backdrop-filter:blur(6px);overflow-y:auto;}
.modal-box{background:var(--bg-card);border:1px solid var(--accent-gold);border-radius:14px;overflow:hidden;width:95%;max-width:1150px;margin-bottom:40px;}
.modal-head{padding:18px 22px;border-bottom:1px solid var(--border-glass);display:flex;justify-content:space-between;align-items:center;background:rgba(212,175,55,.05);}
.modal-body{padding:24px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
.form-group label{display:block;font-size:.75rem;color:var(--accent-gold);margin-bottom:4px;text-transform:uppercase;letter-spacing:1px;font-weight:700;}
.social-icons{display:flex;gap:10px;margin-top:8px;}
.social-icons i{font-size:1.1rem;color:#333;transition:.2s;}
.social-icons i.active.fa-instagram{color:#e1306c;}
.social-icons i.active.fa-pinterest{color:#bd081c;}
.social-icons i.active.fa-linkedin{color:#0a66c2;}
.usage-stats{font-size:.7rem;color:#94a3b8;margin-top:8px;display:flex;justify-content:space-between;border-top:1px solid rgba(255,255,255,0.05);padding-top:5px;}
.quality-badge{position:absolute;bottom:8px;left:8px;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700;text-transform:uppercase;}
.q-publicar{background:#2ecc71;color:#fff;}
.q-revisar{background:#f1c40f;color:#000;}
.q-descartar{background:#e74c3c;color:#fff;}
.progress-container{width:100%;height:12px;background:rgba(255,255,255,0.05);border-radius:10px;margin-bottom:20px;overflow:hidden;display:none;border:1px solid var(--border-glass);}
.progress-bar{height:100%;width:0;background:linear-gradient(90deg, var(--accent-gold), #fff);transition:width 0.3s ease;}
.lb-over{position:fixed;inset:0;background:rgba(0,0,0,.96);display:none;justify-content:center;align-items:center;z-index:9999;}
.lb-inner{text-align:center;max-width:92vw;}
.lb-close{position:absolute;top:18px;right:24px;color:#fff;font-size:2.5rem;cursor:pointer;line-height:1;}
.lb-dl{display:inline-block;margin-top:18px;background:var(--accent-gold);color:#000;padding:11px 28px;border-radius:28px;font-weight:700;text-decoration:none;}

/* ===== ESTADISTICAS TAB ===== */
.stats-grid-kpi{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:30px;}
.kpi-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:22px 18px;text-align:center;position:relative;overflow:hidden;transition:.3s;}
.kpi-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(212,175,55,0.05),transparent);pointer-events:none;}
.kpi-card:hover{border-color:var(--accent-gold);transform:translateY(-2px);}
.kpi-num{font-size:2.4rem;font-weight:900;color:var(--accent-gold);line-height:1;font-family:'Cinzel',serif;}
.kpi-label{font-size:.75rem;color:#cbd5e1;text-transform:uppercase;letter-spacing:1px;margin-top:8px;}
.kpi-icon{font-size:1.6rem;margin-bottom:10px;opacity:.5;}
.kpi-card.danger .kpi-num{color:#e74c3c;}
.kpi-card.success .kpi-num{color:#27ae60;}
.kpi-card.info .kpi-num{color:#3498db;}

.stats-section{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:24px;margin-bottom:24px;}
.stats-section h3{color:var(--accent-gold);font-size:1rem;margin:0 0 20px 0;text-transform:uppercase;letter-spacing:2px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border-glass);padding-bottom:12px;}

/* Bar chart rows */
.bar-row{display:flex;align-items:center;gap:12px;margin-bottom:10px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);}
.bar-row:last-child{border-bottom:none;}
.bar-label{flex:0 0 200px;font-size:.8rem;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;transition:.2s;}
.bar-label:hover{color:var(--accent-gold);}
.bar-label .sku-tag{color:#94a3b8;font-size:.7rem;display:block;}
.bar-track{flex:1;background:rgba(255,255,255,0.05);border-radius:20px;height:18px;overflow:hidden;position:relative;}
.bar-fill{height:100%;border-radius:20px;width:0%;transition:width 1.2s cubic-bezier(.4,0,.2,1);position:relative;}
.bar-fill::after{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15));border-radius:20px;}
.bar-fill.rich{background:linear-gradient(90deg,#27ae60,#2ecc71);}
.bar-fill.medium{background:linear-gradient(90deg,#d4af37,#f39c12);}
.bar-fill.low{background:linear-gradient(90deg,#e74c3c,#c0392b);}
.bar-fill.zero{background:rgba(255,255,255,0.08);}
.bar-val{flex:0 0 50px;text-align:right;font-size:.85rem;font-weight:700;color:var(--accent-gold);}
.bar-val.zero{color:#64748b;}

/* Donut-style circular progress (CSS only) */
.coverage-ring-wrap{display:flex;align-items:center;gap:20px;margin-bottom:16px;}
.ring{width:64px;height:64px;border-radius:50%;background:conic-gradient(var(--accent-green) 0% var(--pct, 0%), rgba(255,255,255,0.07) var(--pct, 0%) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;}
.ring::before{content:'';width:44px;height:44px;background:var(--bg-card);border-radius:50%;position:absolute;}
.ring-pct{font-size:.75rem;font-weight:700;color:#fff;position:relative;z-index:1;}
.ring-info{flex:1;}
.ring-title{font-size:.9rem;color:#f1f5f9;margin-bottom:4px;}
.ring-sub{font-size:.7rem;color:#94a3b8;}

/* Alert strip for 0-mockup articles */
.alert-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;max-height:350px;overflow-y:auto;padding-right:4px;}
.alert-item{background:rgba(231,76,60,0.06);border:1px solid rgba(231,76,60,0.25);border-radius:10px;padding:10px 12px;display:flex;align-items:center;gap:10px;transition:.2s;cursor:pointer;}
.alert-item:hover{border-color:#e74c3c;background:rgba(231,76,60,0.12);}
.alert-thumb{width:42px;height:42px;border-radius:8px;object-fit:cover;border:1px solid rgba(231,76,60,0.3);flex-shrink:0;}
.alert-info .sku{font-size:.75rem;color:#e74c3c;font-weight:700;}
.alert-info .nom{font-size:.72rem;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px;}
.alert-info .cat{font-size:.65rem;color:#94a3b8;}

/* Pie-chart simulation with stacked gradient bars */
.qual-pills{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
.qual-pill{padding:8px 18px;border-radius:20px;font-size:.8rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.qual-pill.publicar{background:rgba(46,204,113,0.15);border:1px solid #27ae60;color:#2ecc71;}
.qual-pill.revisar{background:rgba(241,196,15,0.15);border:1px solid #f39c12;color:#f1c40f;}
.qual-pill.descartar{background:rgba(231,76,60,0.15);border:1px solid #c0392b;color:#e74c3c;}
.stacked-bar{width:100%;height:28px;border-radius:14px;overflow:hidden;display:flex;}
.stacked-seg{height:100%;transition:width 1s ease;position:relative;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:rgba(255,255,255,0.8);}

.stats-two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:900px){.stats-two-col{grid-template-columns:1fr;} .bar-label{flex:0 0 130px;} }

.detail-mock-card { background:rgba(255,255,255,0.03); border:1px solid var(--border-glass); border-radius:10px; overflow:hidden; transition:.3s; }
.detail-mock-card:hover { transform:translateY(-3px); border-color:var(--accent-gold); background:rgba(255,255,255,0.06); }
.detail-mock-img { height:160px; width:100%; object-fit:cover; cursor:pointer; }
</style>

<div id="modalArtDetail" class="modal-over" onclick="if(event.target===this)closeArtDetail()">
    <div class="modal-box" style="max-width:1300px;">
        <div class="modal-head">
            <h2 id="artDetailTitle">Galería del Artículo</h2>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="btn btn-green" id="btnDownloadAllDetail" style="width:auto;padding:10px 20px;"><i class="fas fa-download"></i> Descargar Todo</button>
                <button class="btn btn-gold" onclick="closeArtDetail()" style="width:auto;padding:10px 40px;">Cerrar</button>
            </div>
        </div>
        <div class="modal-body">
            <div style="display:grid; grid-template-columns: 400px 1fr; gap:30px;">
                <div style="position:sticky; top:0;">
                    <div id="artDetailImg" style="height:400px; background:#000; border-radius:12px; overflow:hidden; margin-bottom:15px; border:1px solid var(--border-glass);"></div>
                    <button class="btn btn-gold" id="btnDownloadArtImg" style="width:100%;margin-bottom:20px;justify-content:center;"><i class="fas fa-download"></i> Descargar Foto Artículo</button>
                    <h3 id="artDetailName" style="color:var(--accent-gold);margin:0; font-family:'Cinzel',serif;"></h3>
                    <p id="artDetailSku" style="color:#94a3b8; font-size:1rem; margin-top:5px;"></p>
                </div>
                <div>
                    <h4 style="color:var(--accent-gold);margin:0 0 15px 0; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border-glass); padding-bottom:10px;">
                        <i class="fas fa-images"></i> Mockups Asociados
                    </h4>
                    <div id="artDetailMocks" class="mockups-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); max-height:650px; overflow-y:auto; padding-right:10px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalVinculos" class="modal-over" onclick="if(event.target===this)closeVinculos()">
    <div class="modal-box" style="max-width:1200px;">
        <div class="modal-head"><h2>Vincular Artículos</h2><button class="btn btn-gold" onclick="closeVinculos()" style="width:auto;padding:12px 40px;"><i class="fas fa-save"></i> Finalizar y Guardar</button></div>
        <div class="modal-body"><div style="display:grid; grid-template-columns: 350px 1fr; gap:30px;"><div><div id="vinPreview" style="height:350px; background:#000; border-radius:12px; overflow:hidden; margin-bottom:20px; border:1px solid var(--border-glass);"></div><h4 style="color:var(--accent-gold);margin:0 0 10px 0;"><i class="fas fa-link"></i> Vinculados</h4><div id="vinActuales" style="display:flex; flex-wrap:wrap; gap:8px;"></div></div><div><div class="filters-bar"><select id="vCat" class="select-wow" onchange="loadVinCatalog()"><option value="">Categoría...</option><?php foreach($categorias_rows as $c) echo "<option value=\"".htmlspecialchars($c)."\">".htmlspecialchars($c)."</option>"; ?></select><input type="text" id="vSearch" class="search-input" placeholder="Buscar SKU..." oninput="loadVinCatalog()"></div><div id="vGrid" class="mockups-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); max-height:480px; overflow-y:auto;"></div></div></div></div>
    </div>
</div>

<div id="modalEdit" class="modal-over" onclick="if(event.target===this)closeModal()">
    <div class="modal-box" style="max-width:1100px;">
        <div class="modal-head"><h2 id="editTitle">Editar Metadatos</h2><div style="display:flex;gap:10px;"><button class="btn btn-red" onclick="deleteMockup()" style="width:auto;padding:10px 20px;"><i class="fas fa-trash"></i> Eliminar</button><button class="btn btn-gold" onclick="saveMockup()" style="width:auto;padding:10px 40px;"><i class="fas fa-save"></i> Guardar Cambios</button><span style="cursor:pointer;font-size:1.6rem;color:#aaa;margin-left:15px;" onclick="closeModal()">&times;</span></div></div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:25px;">
                <div id="editPreview" style="height:480px;background:#000;border-radius:10px;overflow:hidden;"></div>
                <div>
                    <input type="hidden" id="editId">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div>
                            <h4 style="color:var(--accent-gold);margin:0 0 8px 0;font-size:.75rem;text-transform:uppercase;">Marca</h4>
                            <div style="display:flex; gap:10px; background:rgba(255,255,255,0.03); padding:8px; border-radius:8px; border:1px solid var(--border-glass);">
                                <label style="font-size:.7rem;cursor:pointer;"><input type="checkbox" id="eMarcaN"> Nox</label>
                                <label style="font-size:.7rem;cursor:pointer;"><input type="checkbox" id="eMarcaC"> Can</label>
                                <label style="font-size:.7rem;cursor:pointer;"><input type="checkbox" id="eMarcaZ"> Zen</label>
                            </div>
                        </div>
                        <div>
                            <h4 style="color:var(--accent-gold);margin:0 0 8px 0;font-size:.75rem;text-transform:uppercase;">Publicado en</h4>
                            <div style="display:flex; gap:10px; background:rgba(255,255,255,0.03); padding:8px; border-radius:8px; border:1px solid var(--border-glass);">
                                <label title="Instagram" style="cursor:pointer;"><input type="checkbox" id="eRS_ig"> <i class="fab fa-instagram"></i></label>
                                <label title="Pinterest" style="cursor:pointer;"><input type="checkbox" id="eRS_pi"> <i class="fab fa-pinterest"></i></label>
                                <label title="LinkedIn" style="cursor:pointer;"><input type="checkbox" id="eRS_li"> <i class="fab fa-linkedin"></i></label>
                            </div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Estancia</label><input id="eEstancia" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Estilo</label><input id="eEstilo" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Luz</label><input id="eLuz" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Decoración</label><input id="eDecoracion" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Formato</label><input id="eFormato" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Color</label><input id="eColor" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Temporada</label><input id="eTemporada" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Calidad</label><select id="eCalidad" class="select-wow" style="width:100%"><option value="publicar">Publicar</option><option value="revisar">Revisar</option><option value="descartar">Descartar</option></select></div>
                    </div>
                    <div class="form-group"><label>Notas internas</label><textarea id="eNotas" class="search-input" style="width:100%;height:45px;resize:none;"></textarea></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mockups-container">
    <div class="tabs-nav">
        <button class="tab-btn active" id="tabArtBtn" onclick="switchTab('tab-art',this)">Por Artículo</button>
        <button class="tab-btn" onclick="switchTab('tab-gen',this)">Banco General</button>
        <button class="tab-btn" onclick="switchTab('tab-stats',this)"><i class="fas fa-chart-bar" style="margin-right:6px"></i>Estadísticas</button>
    </div>
    <div id="progCont" class="progress-container"><div id="progBar" class="progress-bar"></div></div>

    <div id="tab-art" class="tab-content active">
        <div class="filters-bar">
            <select id="selCat" class="select-wow" onchange="loadArticles()"><option value="__TODAS__">Todas las categorías</option><?php foreach($categorias_rows as $c) echo "<option value=\"".htmlspecialchars($c)."\">".htmlspecialchars($c)."</option>"; ?></select>
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;padding:8px 12px;border-radius:8px;border:1px solid var(--border-glass);font-size:.9rem;"><input type="checkbox" id="chkBase" checked onchange="loadArticles()"> Solo Base</label>
            <div id="artCount" style="color:var(--accent-gold); font-size: 0.85rem; margin-left: 15px;">...</div>
            <button class="btn" onclick="loadArticles()" style="flex:none; width:auto; padding:8px 15px;"><i class="fas fa-sync" id="artSyncIcon"></i> Refrescar</button>
            <div style="flex:1"></div>
            <input type="text" id="searchArt" class="search-input" placeholder="Buscar SKU..." oninput="filterArt()">
            <button class="filter-btn" onclick="setArtFilter('all',this)">Todos</button>
            <button class="filter-btn active" onclick="setArtFilter('with',this)">Con Mockup</button>
            <button class="filter-btn" onclick="setArtFilter('without',this)">Sin Mockup</button>
        </div>
        <div id="artLoading" class="spinner" style="display:none;text-align:center;padding:40px;"><i class="fas fa-circle-notch fa-spin"></i> Cargando...</div>
        <div id="gridArt" class="mockups-grid"></div>
    </div>

    <div id="tab-gen" class="tab-content">
        <div class="filters-bar">
            <select id="fTipo"      class="select-wow" onchange="loadGeneral()"><option value="">Tipo...</option><option value="imagen">Imágenes</option><option value="video">Vídeos</option></select>
            <select id="fSocial"    class="select-wow" onchange="loadGeneral()"><option value="">Publicado en...</option><option value="ig">Instagram</option><option value="li">LinkedIn</option><option value="pi">Pinterest</option></select>
            <select id="fMarca"     class="select-wow" onchange="loadGeneral()"><option value="">Marca...</option><option value="NOXERTEZ">Noxertez</option><option value="CANDLEHOLDER">Candle Holder</option><option value="ZEN">Zen Garden</option></select>
            <select id="fEstancia"  class="select-wow" onchange="loadGeneral()"><option value="">Estancia...</option><?php foreach($estancias_rows as $e) echo "<option value=\"$e\">$e</option>"; ?></select>
            <select id="fEstilo"    class="select-wow" onchange="loadGeneral()"><option value="">Estilo...</option><?php foreach($estilos_rows as $e) echo "<option value=\"$e\">$e</option>"; ?></select>
            <select id="fDecoracion"class="select-wow" onchange="loadGeneral()"><option value="">Decoración...</option><?php foreach($decos_rows as $d) echo "<option value=\"$d\">$d</option>"; ?></select>
            <input type="text" id="fBuscar" class="search-input" placeholder="Buscar..." oninput="loadGeneral()">
            <div id="genCount" style="color:var(--accent-gold); font-size: 0.85rem; margin-left: 15px;">...</div>
            <button class="btn" onclick="loadGeneral()" style="flex:none; width:auto; padding:8px 15px;"><i class="fas fa-sync" id="genSyncIcon"></i> Refrescar</button>
            <button class="btn" onclick="refreshFilters()" style="flex:none; width:auto; padding:8px 15px;"><i class="fas fa-broom"></i> Limpiar Filtros</button>
            <button class="btn btn-gold" onclick="document.getElementById('folderInput').click()" style="flex:none;padding:9px 18px;"><i class="fas fa-upload"></i> Subir</button>
            <input type="file" id="folderInput" webkitdirectory directory multiple style="display:none;" onchange="handleFolderUpload()">
        </div>
        <div id="genLoading" class="spinner" style="display:none;text-align:center;padding:40px;"><i class="fas fa-circle-notch fa-spin"></i> Cargando...</div>
        <div id="gridGen" class="mockups-grid"></div>
    </div>

    <!-- ========== PESTAÑA ESTADÍSTICAS ========== -->
    <div id="tab-stats" class="tab-content">
        <div id="statsLoading" style="text-align:center;padding:60px;display:none;"><i class="fas fa-circle-notch fa-spin" style="font-size:2rem;color:var(--accent-gold);"></i><p style="margin-top:14px;color:#888;">Calculando estadísticas...</p></div>
        <div id="statsContent" style="display:none;">
            <h2 style="font-family:'UnifrakturCook',cursive; color:var(--accent-gold); margin-bottom:25px; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fas fa-chart-pie"></i> Panel de Rendimiento</span>
                <button class="btn" onclick="statsLoaded=false; loadStats();" style="width:auto; padding:8px 15px; font-size:0.8rem;">
                    <i class="fas fa-sync"></i> Actualizar Datos
                </button>
            </h2>

            <!-- KPIs -->
            <div class="stats-grid-kpi" id="kpiGrid"></div>

            <!-- Cobertura de categorías -->
            <div class="stats-section">
                <h3><i class="fas fa-layer-group"></i> Cobertura por Categoría</h3>
                <div id="catCoverage"></div>
            </div>

            <!-- Layout dos columnas -->
            <div class="stats-two-col">

                <!-- Ranking TOP artículos con más mockups -->
                <div class="stats-section">
                    <h3><i class="fas fa-trophy"></i> Top Artículos con Más Mockups</h3>
                    <div id="rankingTop"></div>
                </div>

                <!-- Calidad + Estancias -->
                <div>
                    <div class="stats-section">
                        <h3><i class="fas fa-star"></i> Calidad del Banco</h3>
                        <div id="qualStats"></div>
                    </div>
                    <div class="stats-section">
                        <h3><i class="fas fa-home"></i> Top Estancias</h3>
                        <div id="estanciaStats"></div>
                    </div>
                </div>
            </div>

            <!-- Artículos sin mockup (prioridad) -->
            <div class="stats-section" id="sinMockupSec">
                <h3><i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i> Artículos Sin Mockups — Prioridad de Creación</h3>
                <div id="sinMockupGrid" class="alert-strip"></div>
            </div>

        </div>
    </div>

</div>

<div id="lbOver" class="lb-over" onclick="if(event.target===this||event.target.className==='lb-close')closeLB()"><span class="lb-close">&times;</span><div class="lb-inner"><div id="lbMedia"></div><a id="lbDl" href="#" download class="lb-dl"><i class="fas fa-download"></i> Descargar Master</a></div></div>

<script>
let artFilter = 'with', currentVinMockupId = null, currentVinSkus = [];
function switchTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if(btn) btn.classList.add('active');
    if(id === 'tab-gen' && document.getElementById('gridGen').children.length === 0) {
        document.getElementById('gridGen').innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:100px;color:#666;"><h3>Banco Vacío</h3><p>Usa los filtros para buscar mockups.</p></div>';
    }
    if(id === 'tab-stats') loadStats();
}

async function loadArticles() {
    const cat = document.getElementById('selCat').value, base = document.getElementById('chkBase').checked, ic = document.getElementById('artSyncIcon');
    if(ic) ic.classList.add('fa-spin'); document.getElementById('artLoading').style.display = 'block';
    try {
        const r = await fetch(`${window.location.pathname}?action=get_articles&categoria=${encodeURIComponent(cat)}&solo_base=${base}`);
        const d = await r.json(); renderArticles(d.articulos || []);
    } catch(e) {}
    document.getElementById('artLoading').style.display = 'none'; if(ic) ic.classList.remove('fa-spin');
}

function renderArticles(list) {
    const grid = document.getElementById('gridArt'); grid.innerHTML = ''; let shown = 0;
    list.forEach(art => {
        const mocks = art.local_mockups || []; const hasMock = mocks.length > 0;
        if (artFilter === 'with' && !hasMock) return; if (artFilter === 'without' && hasMock) return;
        shown++;
        const thumbs = mocks.map(m => `<div class="thumb-wrap"><img src="../${m.tipo==='video'?'img/video_placeholder.jpg':m.ruta}" class="thumb" onclick="openLB('../${m.ruta}','${m.tipo}')">${m.tipo==='video'?'<i class="fas fa-play" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#fff;pointer-events:none;font-size:0.8rem;"></i>':''}${m.manual?`<i class="fas fa-times thumb-del" onclick="event.stopPropagation();unlinkDirect('${art.referencia}',${m.id})"></i>`:''}</div>`).join('');
        const card = document.createElement('div'); card.className = 'mockup-card article-card'; card.dataset.sku = art.referencia.toLowerCase(); card.dataset.nom = art.nombre.toLowerCase();
        const mocksJson = JSON.stringify(mocks).replace(/'/g, "&#39;");
        card.innerHTML = `<div class="card-img" onclick='openArtDetail("${art.referencia}","${art.nombre}","${art.foto_portada}",${mocksJson})'><img src="../${art.foto_portada}" onerror="this.src='../img/placeholder_product.png'"><div style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,0.6);padding:5px 10px;border-radius:20px;font-size:0.7rem;color:var(--accent-gold);border:1px solid var(--accent-gold);"><i class="fas fa-images"></i> ${mocks.length}</div></div><div class="card-body"><div class="card-title">${art.nombre}</div><div class="card-sub">SKU: ${art.referencia}</div><div class="thumbs">${thumbs}</div><div class="card-actions"><button class="btn" onclick='openArtDetail("${art.referencia}","${art.nombre}","${art.foto_portada}",${mocksJson})'><i class="fas fa-eye"></i> Detalle</button>${hasMock?`<button class="btn btn-green" style="flex:2" onclick="downloadAll(${JSON.stringify(mocks.map(m=>m.ruta))})"><i class="fas fa-download"></i> Descargar</button>`:`<button class="btn btn-gold" style="flex:2" onclick="goToLink('${art.referencia}')"><i class="fas fa-search-plus"></i> Banco</button>`}</div></div>`;
        grid.appendChild(card);
    });
    document.getElementById('artCount').innerText = `Mostrando: ${shown} artículos.`;
}

function downloadAll(m) { if(!m || !m.length) return; m.forEach((u, i) => { setTimeout(() => { const link = document.createElement('a'); link.href = '../' + u; link.setAttribute('download', u.split('/').pop()); link.style.display = 'none'; document.body.appendChild(link); link.click(); setTimeout(() => document.body.removeChild(link), 100); }, i * 600); }); }

async function refreshFilters() {
    const r = await fetch(`../api/mockups_varios.php?accion=get_filters`);
    const d = await r.json();
    
    const updateSelect = (id, list, placeholder) => {
        const sel = document.getElementById(id);
        const val = sel.value; // Guardar valor actual
        sel.innerHTML = `<option value="">${placeholder}...</option>` + list.map(v => `<option value="${v}">${v}</option>`).join('');
        sel.value = val; // Restaurar valor si sigue existiendo
    };
    
    updateSelect('fEstancia', d.estancias, 'Estancia');
    updateSelect('fEstilo', d.estilos, 'Estilo');
    updateSelect('fDecoracion', d.decoraciones, 'Decoración');
    
    alert('¡Filtros actualizados con éxito!');
}

async function loadGeneral() {
    const q = document.getElementById('fBuscar').value, t = document.getElementById('fTipo').value, ma = document.getElementById('fMarca').value, est = document.getElementById('fEstancia').value, esti = document.getElementById('fEstilo').value, dec = document.getElementById('fDecoracion').value, soc = document.getElementById('fSocial').value;
    if(!q && !t && !ma && !est && !esti && !dec && !soc) { alert('Selecciona un filtro o busca algo.'); return; }
    const ic = document.getElementById('genSyncIcon'); if(ic) ic.classList.add('fa-spin');
    document.getElementById('genLoading').style.display = 'block';
    const p = new URLSearchParams({ accion: 'listar', tipo: t, marca: ma, estancia: est, estilo: esti, decoracion: dec, social: soc, buscar: q });
    try {
        const r = await fetch(`../api/mockups_varios.php?${p}`); const mocks = await r.json();
        document.getElementById('genCount').innerText = `Encontrados: ${mocks.length} mockups.`;
        const grid = document.getElementById('gridGen'); grid.innerHTML = '';
        mocks.forEach(m => {
            const card = document.createElement('div'); card.className = 'mockup-card';
            const skus = m.skus ? m.skus.split(',') : []; const hasLinks = skus.length > 0;
            const linksHtml = skus.map(s => `<span style="background:rgba(212,175,55,0.1); padding:2px 5px; border-radius:4px; font-size:0.7rem; color:var(--accent-gold); border:1px solid rgba(212,175,55,0.2);">${s}</span>`).join(' ');
            const socialIcons = `<div class="social-icons">
                <i class="fab fa-instagram ${m.publicado_instagram?'active':''}"></i>
                <i class="fab fa-pinterest ${m.publicado_pinterest?'active':''}"></i>
                <i class="fab fa-linkedin ${m.publicado_linkedin?'active':''}"></i>
            </div>`;
            const stats = `<div class="usage-stats"><span><i class="fas fa-history"></i> ${m.ultima_vez_usado || 'Nunca'}</span><span><i class="fas fa-chart-line"></i> ${m.veces_usado} usos</span></div>`;
            card.innerHTML = `<div class="card-img" onclick="openLB('../${m.ruta}','${m.tipo}')">${m.tipo==='video'?`<video src="../${m.ruta}"></video><i class="fas fa-play" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:rgba(255,255,255,0.6);font-size:1.5rem;"></i>`:`<img src="../${m.ruta}" loading="lazy">`}<span class="quality-badge q-${m.calidad}">${m.calidad}</span></div><div class="card-body"><div class="card-title">${m.archivo}</div><div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:10px; min-height:20px;">${linksHtml}</div>${socialIcons}${stats}<div class="card-actions" style="margin-top:12px;"><button class="btn" onclick="openLB('../${m.ruta}','${m.tipo}')"><i class="fas fa-expand"></i></button><button class="btn" onclick="openEdit(${m.id})"><i class="fas fa-cog"></i></button><button class="btn ${hasLinks?'btn-green':'btn-gold'}" style="flex:2" onclick="openVinculos(${m.id},'${m.ruta}','${m.tipo}','${m.skus||''}')"><i class="fas ${hasLinks?'fa-check-circle':'fa-link'}"></i> ${hasLinks?'Vinculado':'Vincular'}</button></div></div>`;
            grid.appendChild(card);
        });
    } catch(e) {}
    document.getElementById('genLoading').style.display = 'none'; if(ic) ic.classList.remove('fa-spin');
}

async function handleFolderUpload() {
    const input = document.getElementById('folderInput'); if (!input.files.length) return;
    const files = Array.from(input.files);
    const total = files.length;
    let uploaded = 0;
    document.getElementById('progCont').style.display = 'block';
    for (const file of files) {
        const fd = new FormData(); fd.append('files[]', file);
        try {
            await fetch(window.location.pathname, { method: 'POST', body: fd });
        } catch(e) { console.error("Error subiendo:", file.name); }
        uploaded++;
        const per = (uploaded / total) * 100;
        document.getElementById('progBar').style.width = per + '%';
    }
    alert('¡Subida completada!'); document.getElementById('progCont').style.display = 'none'; document.getElementById('progBar').style.width = '0'; loadGeneral();
}

async function openEdit(id) {
    const r = await fetch(`../api/mockups_varios.php?accion=uno&id=${id}`); const m = await r.json();
    document.getElementById('editId').value = m.id; 
    document.getElementById('editTitle').innerText = 'Editar: ' + m.archivo;
    document.getElementById('editPreview').innerHTML = m.tipo==='video'?`<video src="../${m.ruta}" controls style="width:100%;height:100%"></video>`:`<img src="../${m.ruta}" style="width:100%;height:100%;object-fit:contain">`;
    document.getElementById('eMarcaN').checked = m.marca_noxertez == 1; document.getElementById('eMarcaC').checked = m.marca_candleholder == 1; document.getElementById('eMarcaZ').checked = m.marca_zen == 1;
    document.getElementById('eRS_ig').checked = m.publicado_instagram != null;
    document.getElementById('eRS_pi').checked = m.publicado_pinterest != null;
    document.getElementById('eRS_li').checked = m.publicado_linkedin != null;
    ['Estancia','Estilo','Luz','Decoracion','Formato','Color','Temporada'].forEach(f => { const el = document.getElementById('e'+f); if(el) el.value = m[f.toLowerCase()==='color'?'color_dominante':f.toLowerCase()]||''; });
    document.getElementById('eCalidad').value = m.calidad||'publicar'; document.getElementById('eNotas').value = m.notas||''; document.getElementById('modalEdit').style.display = 'flex';
}
async function saveMockup() {
    const fd = new FormData(); fd.append('accion','editar'); fd.append('id', document.getElementById('editId').value);
    fd.append('marca_noxertez', document.getElementById('eMarcaN').checked?1:0); fd.append('marca_candleholder', document.getElementById('eMarcaC').checked?1:0); fd.append('marca_zen', document.getElementById('eMarcaZ').checked?1:0);
    fd.append('publicado_instagram', document.getElementById('eRS_ig').checked?1:0);
    fd.append('publicado_pinterest', document.getElementById('eRS_pi').checked?1:0);
    fd.append('publicado_linkedin', document.getElementById('eRS_li').checked?1:0);
    ['Estancia','Estilo','Luz','Decoracion','Formato','Color','Temporada','Calidad','Notas'].forEach(f => fd.append(f.toLowerCase()==='color'?'color_dominante':f.toLowerCase(), document.getElementById('e'+f).value));
    await fetch('../api/mockups_varios.php', {method:'POST',body:fd}); closeModal(); loadGeneral();
}
async function unlinkDirect(sku, mockupId) { if(!confirm('¿Desvincular?')) return; const fd = new FormData(); fd.append('accion', 'desvincular_multiple'); fd.append('id', mockupId); fd.append('sku', sku); await fetch('../api/mockups_varios.php', {method:'POST', body:fd}); loadArticles(); }
function goToLink(sku) { switchTab('tab-gen'); document.getElementById('tabArtBtn').classList.remove('active'); document.querySelector('[onclick*="tab-gen"]').classList.add('active'); document.getElementById('fBuscar').value = sku; loadGeneral(); }
function goToArticle(sku) {
    switchTab('tab-art', document.getElementById('tabArtBtn'));
    document.getElementById('selCat').value = '__TODAS__';
    document.getElementById('searchArt').value = sku;
    artFilter = 'all';
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.filter-btn[onclick*="all"]').classList.add('active');
    loadArticles().then(() => { filterArt(); });
}
async function openVinculos(id, ruta, tipo, skusStr) { currentVinMockupId = id; currentVinSkus = skusStr ? skusStr.split(',') : []; document.getElementById('vinPreview').innerHTML = tipo==='video'?`<video src="../${ruta}" autoplay muted loop style="width:100%;height:100%;object-fit:cover"></video>`:`<img src="../${ruta}" style="width:100%;height:100%;object-fit:cover">`; document.getElementById('vGrid').innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#666;padding:40px;">Selecciona una categoría...</div>'; document.getElementById('modalVinculos').style.display = 'flex'; renderVinActuales(); }
function renderVinActuales() { const cont = document.getElementById('vinActuales'); cont.innerHTML = currentVinSkus.length ? '' : '...'; currentVinSkus.forEach(sku => { const tag = document.createElement('div'); tag.style = 'background:rgba(212,175,55,0.15); border:1px solid var(--accent-gold); padding:8px 15px; border-radius:20px; font-size:0.85rem; display:flex; align-items:center; gap:10px;'; tag.innerHTML = `<span><b>${sku}</b></span><i class="fas fa-times-circle" style="cursor:pointer;color:#e74c3c;" onclick="toggleVinculo('${sku}')"></i>`; cont.appendChild(tag); }); }
async function loadVinCatalog() { const cat = document.getElementById('vCat').value, q = document.getElementById('vSearch').value.toLowerCase(); if(!cat && q.length < 3) return; const r = await fetch(`${window.location.pathname}?action=get_articles&categoria=${encodeURIComponent(cat)}&solo_base=true`); const d = await r.json(), grid = document.getElementById('vGrid'); grid.innerHTML = ''; (d.articulos || []).filter(a => a.nombre.toLowerCase().includes(q) || a.referencia.toLowerCase().includes(q)).forEach(a => { const isSel = currentVinSkus.includes(a.referencia), item = document.createElement('div'); item.className = 'art-item' + (isSel ? ' selected' : ''); item.onclick = () => toggleVinculo(a.referencia); item.innerHTML = `<div style="height:100px; background:#000;"><img src="../${a.foto_portada}" style="width:100%;height:100%;object-fit:cover;"></div><div style="padding:8px; font-size:0.75rem;"><div style="color:var(--accent-gold);">${a.nombre}</div><div>${a.referencia}</div></div>`; grid.appendChild(item); }); }
async function toggleVinculo(sku) { const isLinked = currentVinSkus.includes(sku), action = isLinked ? 'desvincular_multiple' : 'vincular_multiple'; if (isLinked) currentVinSkus = currentVinSkus.filter(s => s !== sku); else currentVinSkus.push(sku); renderVinActuales(); loadVinCatalog(); const fd = new FormData(); fd.append('accion', action); fd.append('id', currentVinMockupId); fd.append('sku', sku); await fetch('../api/mockups_varios.php', {method:'POST', body:fd}); }
function closeVinculos() { document.getElementById('modalVinculos').style.display = 'none'; loadGeneral(); loadArticles(); }
function deleteMockup() { if(confirm('¿Eliminar?')) { const fd = new FormData(); fd.append('accion','eliminar'); fd.append('id', document.getElementById('editId').value); fetch('../api/mockups_varios.php', {method:'POST',body:fd}).then(()=> { closeModal(); loadGeneral(); }); } }
function closeModal() { document.getElementById('modalEdit').style.display = 'none'; }
function openArtDetail(sku, name, photo, mocks) {
    document.getElementById('artDetailTitle').innerText = 'Galería: ' + name;
    document.getElementById('artDetailName').innerText = name;
    document.getElementById('artDetailSku').innerText = 'SKU: ' + sku;
    document.getElementById('artDetailImg').innerHTML = `<img src="../${photo}" style="width:100%;height:100%;object-fit:cover;">`;
    const grid = document.getElementById('artDetailMocks'); grid.innerHTML = '';
    if (!mocks || !mocks.length) {
        grid.innerHTML = '<p style="color:#94a3b8;padding:20px;">Este artículo no tiene mockups asociados todavía.</p>';
    } else {
        mocks.forEach(m => {
            const card = document.createElement('div'); card.className = 'detail-mock-card';
            card.innerHTML = `<div style="position:relative;"><img src="../${m.tipo==='video'?'img/video_placeholder.jpg':m.ruta}" class="detail-mock-img" onclick="openLB('../${m.ruta}','${m.tipo}')">${m.tipo==='video'?'<i class="fas fa-play" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#fff;pointer-events:none;font-size:1.5rem;opacity:0.8;"></i>':''}</div><div style="padding:10px; display:flex; justify-content:space-between; align-items:center;"><span style="font-size:0.7rem; color:#94a3b8;">${m.tipo.toUpperCase()}</span><button class="btn btn-gold" style="width:auto; padding:5px 10px; font-size:0.8rem;" onclick="downloadAll(['${m.ruta}'])"><i class="fas fa-download"></i></button></div>`;
            grid.appendChild(card);
        });
    }
    document.getElementById('btnDownloadAllDetail').onclick = () => downloadAll(mocks.map(m => m.ruta));
    document.getElementById('btnDownloadArtImg').onclick = () => downloadAll([photo]);
    document.getElementById('modalArtDetail').style.display = 'flex';
}
function closeArtDetail() { document.getElementById('modalArtDetail').style.display = 'none'; }
function openLB(url, tipo) { document.getElementById('lbMedia').innerHTML = tipo==='video'?`<video src="${url}" controls autoplay style="max-width:90vw;max-height:78vh;"></video>`:`<img src="${url}" style="max-width:90vw;max-height:78vh;">`; document.getElementById('lbDl').href = url; document.getElementById('lbOver').style.display = 'flex'; }
function closeLB() { document.getElementById('lbOver').style.display = 'none'; const v=document.querySelector('#lbMedia video'); if(v) v.pause(); }
function filterArt() { const q = document.getElementById('searchArt').value.toLowerCase(); document.querySelectorAll('.article-card').forEach(c => c.style.display = (c.dataset.sku.includes(q) || c.dataset.nom.includes(q)) ? '' : 'none'); }
function setArtFilter(f, btn) { artFilter = f; document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); loadArticles(); }
document.addEventListener('DOMContentLoaded', () => { loadArticles(); });

// ===== ESTADÍSTICAS =====
let statsLoaded = false;
async function loadStats() {
    if (statsLoaded) return; // cache: solo carga una vez por sesión
    document.getElementById('statsLoading').style.display = 'block';
    document.getElementById('statsContent').style.display = 'none';
    try {
        const r = await fetch('../api/mockups_varios.php?accion=estadisticas');
        const d = await r.json();
        renderKPIs(d.totales);
        renderCatCoverage(d.por_categoria);
        renderRankingTop(d.ranking);
        renderQuality(d.por_calidad, d.totales.mockups);
        renderEstancias(d.por_estancia);
        renderSinMockup(d.sin_mockup);
        statsLoaded = true;
        document.getElementById('statsLoading').style.display = 'none';
        document.getElementById('statsContent').style.display = 'block';
        // Trigger animations after visible
        setTimeout(() => animateBars(), 80);
    } catch(e) {
        document.getElementById('statsLoading').innerHTML = '<p style="color:#e74c3c;"><i class="fas fa-exclamation-triangle"></i> Error cargando estadísticas.</p>';
    }
}

function renderKPIs(t) {
    const pct = t.articulos > 0 ? Math.round((t.con_mockup / t.articulos) * 100) : 0;
    const kpis = [
        { icon: 'fa-images',          num: t.mockups,    label: 'Total Mockups',       cls: '' },
        { icon: 'fa-photo-video',     num: t.imagenes,   label: 'Imágenes',            cls: 'info' },
        { icon: 'fa-film',            num: t.videos,     label: 'Vídeos',              cls: 'info' },
        { icon: 'fa-box',             num: t.articulos,  label: 'Artículos Base',      cls: '' },
        { icon: 'fa-check-circle',    num: t.con_mockup, label: 'Con Mockup',          cls: 'success' },
        { icon: 'fa-exclamation-circle', num: t.sin_mockup, label: 'Sin Mockup',       cls: 'danger' },
        { icon: 'fa-percentage',      num: pct + '%',    label: 'Cobertura Total',     cls: pct >= 80 ? 'success' : pct >= 50 ? '' : 'danger' },
    ];
    document.getElementById('kpiGrid').innerHTML = kpis.map(k => `
        <div class="kpi-card ${k.cls}">
            <div class="kpi-icon"><i class="fas ${k.icon}"></i></div>
            <div class="kpi-num">${k.num}</div>
            <div class="kpi-label">${k.label}</div>
        </div>`).join('');
}

function renderCatCoverage(cats) {
    const el = document.getElementById('catCoverage');
    if (!cats || !cats.length) { el.innerHTML = '<p style="color:#666;">Sin datos.</p>'; return; }
    el.innerHTML = cats.map(c => {
        const pct = c.total_arts > 0 ? Math.round((c.arts_con_mockup / c.total_arts) * 100) : 0;
        const pctStr = pct + '%';
        const color = pct >= 80 ? '#27ae60' : pct >= 40 ? '#d4af37' : '#e74c3c';
        return `<div class="coverage-ring-wrap">
            <div class="ring" style="--pct:${pctStr}; background:conic-gradient(${color} 0% ${pctStr}, rgba(255,255,255,0.07) ${pctStr} 100%);">
                <span class="ring-pct">${pct}%</span>
            </div>
            <div class="ring-info">
                <div class="ring-title">${c.categoria}</div>
                <div class="ring-sub">${c.arts_con_mockup} de ${c.total_arts} artículos &mdash; <b style="color:var(--accent-gold);">${c.total_mockups}</b> mockups</div>
            </div>
            <div style="flex:1; background:rgba(255,255,255,0.04); border-radius:20px; height:10px; overflow:hidden; margin-left:10px; max-width:200px;">
                <div class="bar-fill ${pct>=80?'rich':pct>=40?'medium':'low'}" data-w="${pct}" style="width:0%;height:100%;border-radius:20px;"></div>
            </div>
        </div>`;
    }).join('');
}

function renderRankingTop(ranking) {
    const el = document.getElementById('rankingTop');
    if (!ranking || !ranking.length) { el.innerHTML = '<p style="color:#666;">Sin datos.</p>'; return; }
    const max = Math.max(...ranking.map(r => parseInt(r.total_mockups)));
    el.innerHTML = ranking.slice(0, 20).map((r, i) => {
        const n = parseInt(r.total_mockups);
        const pct = max > 0 ? (n / max * 100) : 0;
        const cls = n >= 5 ? 'rich' : n >= 2 ? 'medium' : n === 0 ? 'zero' : 'low';
        const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `<span style="color:#555;font-size:.75rem;">${i+1}.</span>`;
        return `<div class="bar-row">
            <div class="bar-label" title="${r.nombre}" onclick="goToArticle('${r.referencia}')">${medal} ${r.nombre}<span class="sku-tag">${r.referencia}</span></div>
            <div class="bar-track"><div class="bar-fill ${cls}" data-w="${pct.toFixed(1)}" style="width:0%"></div></div>
            <div class="bar-val ${n===0?'zero':''}">${n}</div>
        </div>`;
    }).join('');
}

function renderQuality(quals, total) {
    const el = document.getElementById('qualStats');
    if (!quals || !quals.length) { el.innerHTML = '<p style="color:#666;">Sin datos.</p>'; return; }
    const colors = { publicar: '#27ae60', revisar: '#f39c12', descartar: '#e74c3c' };
    const pills = quals.map(q => `<div class="qual-pill ${q.calidad}"><i class="fas fa-circle" style="font-size:.5rem;"></i> ${q.calidad}: <b>${q.total}</b></div>`).join('');
    const segs = quals.map(q => {
        const pct = total > 0 ? (q.total / total * 100).toFixed(1) : 0;
        return `<div class="stacked-seg" data-w="${pct}" style="width:0%;background:${colors[q.calidad]||'#666'};">${pct > 8 ? pct + '%' : ''}</div>`;
    }).join('');
    el.innerHTML = `<div class="qual-pills">${pills}</div><div class="stacked-bar">${segs}</div>`;
}

function renderEstancias(estancias) {
    const el = document.getElementById('estanciaStats');
    if (!estancias || !estancias.length) { el.innerHTML = '<p style="color:#666;">Sin datos.</p>'; return; }
    const max = Math.max(...estancias.map(e => parseInt(e.total)));
    el.innerHTML = estancias.map(e => {
        const pct = max > 0 ? (parseInt(e.total) / max * 100).toFixed(1) : 0;
        return `<div class="bar-row">
            <div class="bar-label" title="${e.estancia}">${e.estancia}</div>
            <div class="bar-track"><div class="bar-fill medium" data-w="${pct}" style="width:0%"></div></div>
            <div class="bar-val">${e.total}</div>
        </div>`;
    }).join('');
}

function renderSinMockup(arts) {
    const el = document.getElementById('sinMockupGrid');
    const sec = document.getElementById('sinMockupSec');
    if (!arts || !arts.length) {
        sec.style.display = 'none'; return;
    }
    sec.querySelector('h3').innerHTML = `<i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i> Artículos Sin Mockups — Prioridad de Creación <span style="background:#e74c3c;color:#fff;border-radius:20px;padding:2px 10px;font-size:.75rem;margin-left:8px;">${arts.length}</span>`;
    el.innerHTML = arts.map(a => `
        <div class="alert-item" onclick="goToArticle('${a.referencia}')" title="Ir a buscar mockup para ${a.referencia}">
            <img class="alert-thumb" src="../${a.foto_portada}" onerror="this.src='../img/placeholder_product.png'">
            <div class="alert-info">
                <div class="sku">${a.referencia}</div>
                <div class="nom">${a.nombre}</div>
                <div class="cat">${a.categoria}</div>
            </div>
        </div>`).join('');
}

function animateBars() {
    // Barras horizontales
    document.querySelectorAll('.bar-fill[data-w]').forEach(el => {
        el.style.width = el.dataset.w + '%';
    });
    // Segmentos apilados (calidad)
    document.querySelectorAll('.stacked-seg[data-w]').forEach(el => {
        el.style.width = el.dataset.w + '%';
    });
}
</script>
<?php require_once '../includes/footer.php'; ?>
