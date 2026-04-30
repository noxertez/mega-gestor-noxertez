<?php
define('ALLOWED_ACCESS', true);
require_once '../api/config.php';
$db = conectar();

// Función de apoyo para parsear metadatos
function parseMetadata($file) {
    $base = pathinfo($file, PATHINFO_FILENAME);
    $base = preg_replace('/_mockup[-_]\d+$/i', '', $base);
    $p = explode('_', $base);
    $est = isset($p[0]) ? str_replace('-', ' ', $p[0]) : '';
    if (stripos($est, 'cafet') !== false || stripos($est, 'acogedora') !== false) $est = 'Cafetería realista';
    if (stripos($est, 'boutique') !== false) $est = 'Boutique artesanía';
    return [
        'estancia'   => substr(ucfirst($est), 0, 100),
        'luz'        => isset($p[1]) ? substr(ucfirst(str_replace('-',' ',$p[1])), 0, 100) : '',
        'formato'    => isset($p[2]) ? substr(ucfirst(str_replace('-',' ',$p[2])), 0, 100) : '',
        'decoracion' => isset($p[3]) ? substr(ucfirst(str_replace('-',' ',$p[3])), 0, 100) : '',
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
:root{--bg-dark:#0a1224;--bg-card:#131e31;--accent-gold:#d4af37;--accent-green:#27ae60;--border-glass:rgba(212,175,55,0.15);}
body{background-color:var(--bg-dark)!important;color:#e0e0e0;font-family:'Cinzel',serif;}
.mockups-container{padding:20px;max-width:1600px;margin:0 auto;}
.tabs-nav{display:flex;gap:20px;margin-bottom:30px;border-bottom:1px solid var(--border-glass);}
.tab-btn{background:none;border:none;color:#777;font-size:1.2rem;cursor:pointer;padding:10px 20px;font-family:'UnifrakturCook',cursive;transition:.3s;}
.tab-btn.active{color:var(--accent-gold);border-bottom:2px solid var(--accent-gold);}
.tab-content{display:none;}.tab-content.active{display:block;}
.filters-bar{display:flex;gap:12px;margin-bottom:15px;flex-wrap:wrap;align-items:center;background:rgba(255,255,255,0.02);padding:15px;border-radius:12px;border:1px solid var(--border-glass);}
.search-input{background:#08101d;border:1px solid var(--border-glass);color:#fff;padding:10px 14px;border-radius:8px;flex:1;min-width:180px;}
.select-wow{background:#08101d;border:1px solid var(--border-glass);color:#fff;padding:9px 12px;border-radius:8px;cursor:pointer;min-width:110px;}
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
.usage-stats{font-size:.7rem;color:#777;margin-top:8px;display:flex;justify-content:space-between;border-top:1px solid rgba(255,255,255,0.05);padding-top:5px;}
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
</style>

<div id="modalVinculos" class="modal-over" onclick="if(event.target===this)closeVinculos()">
    <div class="modal-box" style="max-width:1200px;">
        <div class="modal-head"><h2>Vincular Artículos</h2><button class="btn btn-gold" onclick="closeVinculos()" style="width:auto;padding:12px 40px;"><i class="fas fa-save"></i> Finalizar y Guardar</button></div>
        <div class="modal-body"><div style="display:grid; grid-template-columns: 350px 1fr; gap:30px;"><div><div id="vinPreview" style="height:350px; background:#000; border-radius:12px; overflow:hidden; margin-bottom:20px; border:1px solid var(--border-glass);"></div><h4 style="color:var(--accent-gold);margin:0 0 10px 0;"><i class="fas fa-link"></i> Vinculados</h4><div id="vinActuales" style="display:flex; flex-wrap:wrap; gap:8px;"></div></div><div><div class="filters-bar"><select id="vCat" class="select-wow" onchange="loadVinCatalog()"><option value="">Categoría...</option><?php foreach($categorias_rows as $c) echo "<option value=\"".htmlspecialchars($c)."\">".htmlspecialchars($c)."</option>"; ?></select><input type="text" id="vSearch" class="search-input" placeholder="Buscar SKU..." oninput="loadVinCatalog()"></div><div id="vGrid" class="mockups-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); max-height:480px; overflow-y:auto;"></div></div></div></div>
    </div>
</div>

<div id="modalEdit" class="modal-over" onclick="if(event.target===this)closeModal()">
    <div class="modal-box" style="max-width:1100px;">
        <div class="modal-head"><h2>Editar Metadatos</h2><div style="display:flex;gap:10px;"><button class="btn btn-red" onclick="deleteMockup()" style="width:auto;padding:10px 20px;"><i class="fas fa-trash"></i> Eliminar</button><button class="btn btn-gold" onclick="saveMockup()" style="width:auto;padding:10px 40px;"><i class="fas fa-save"></i> Guardar Cambios</button><span style="cursor:pointer;font-size:1.6rem;color:#aaa;margin-left:15px;" onclick="closeModal()">&times;</span></div></div>
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
        <button class="tab-btn active" id="tabArtBtn" onclick="switchTab('tab-art')">Por Artículo</button>
        <button class="tab-btn" onclick="switchTab('tab-gen')">Banco General</button>
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
            <button class="btn" onclick="loadGeneral()" style="flex:none; width:auto; padding:8px 15px;"><i class="fas fa-sync" id="genSyncIcon"></i> Refrescar</button>
            <button class="btn btn-gold" onclick="document.getElementById('folderInput').click()" style="flex:none;padding:9px 18px;"><i class="fas fa-upload"></i> Subir</button>
            <input type="file" id="folderInput" webkitdirectory directory multiple style="display:none;" onchange="handleFolderUpload()">
        </div>
        <div id="genLoading" class="spinner" style="display:none;text-align:center;padding:40px;"><i class="fas fa-circle-notch fa-spin"></i> Cargando...</div>
        <div id="gridGen" class="mockups-grid"></div>
    </div>
</div>

<div id="lbOver" class="lb-over" onclick="if(event.target===this||event.target.className==='lb-close')closeLB()"><span class="lb-close">&times;</span><div class="lb-inner"><div id="lbMedia"></div><a id="lbDl" href="#" download class="lb-dl"><i class="fas fa-download"></i> Descargar Master</a></div></div>

<script>
let artFilter = 'with', currentVinMockupId = null, currentVinSkus = [];
function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if(event) event.currentTarget.classList.add('active');
    if(id === 'tab-gen' && document.getElementById('gridGen').children.length === 0) {
        document.getElementById('gridGen').innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:100px;color:#666;"><h3>Banco Vacío</h3><p>Usa los filtros para buscar mockups.</p></div>';
    }
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
        card.innerHTML = `<div class="card-img" onclick="openLB('../${art.foto_portada}','image')"><img src="../${art.foto_portada}" onerror="this.src='../img/placeholder_product.png'"></div><div class="card-body"><div class="card-title">${art.nombre}</div><div class="card-sub">SKU: ${art.referencia}</div><div class="thumbs">${thumbs}</div><div class="card-actions"><button class="btn" onclick="openLB('../${art.foto_portada}','image')"><i class="fas fa-eye"></i></button>${hasMock?`<button class="btn btn-green" style="flex:2" onclick="downloadAll(${JSON.stringify(mocks.map(m=>m.ruta))})"><i class="fas fa-download"></i> Descargar ${mocks.length}</button>`:`<button class="btn btn-gold" style="flex:2" onclick="goToLink('${art.referencia}')"><i class="fas fa-search-plus"></i> Buscar en Banco</button>`}</div></div>`;
        grid.appendChild(card);
    });
    document.getElementById('artCount').innerText = `Mostrando: ${shown} artículos.`;
}

function downloadAll(m) { if(!m || !m.length) return; m.forEach((u, i) => { setTimeout(() => { const link = document.createElement('a'); link.href = '../' + u; link.setAttribute('download', u.split('/').pop()); link.style.display = 'none'; document.body.appendChild(link); link.click(); setTimeout(() => document.body.removeChild(link), 100); }, i * 600); }); }

async function loadGeneral() {
    const q = document.getElementById('fBuscar').value, t = document.getElementById('fTipo').value, ma = document.getElementById('fMarca').value, est = document.getElementById('fEstancia').value, esti = document.getElementById('fEstilo').value, dec = document.getElementById('fDecoracion').value, soc = document.getElementById('fSocial').value;
    if(!q && !t && !ma && !est && !esti && !dec && !soc) { alert('Selecciona un filtro o busca algo.'); return; }
    const ic = document.getElementById('genSyncIcon'); if(ic) ic.classList.add('fa-spin');
    document.getElementById('genLoading').style.display = 'block';
    const p = new URLSearchParams({ accion: 'listar', tipo: t, marca: ma, estancia: est, estilo: esti, decoracion: dec, social: soc, buscar: q });
    try {
        const r = await fetch(`../api/mockups_varios.php?${p}`); const mocks = await r.json();
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

function handleFolderUpload() {
    const input = document.getElementById('folderInput'); if (!input.files.length) return;
    const fd = new FormData(); for (let f of input.files) { fd.append('files[]', f); }
    const xhr = new XMLHttpRequest(); document.getElementById('progCont').style.display = 'block';
    xhr.upload.addEventListener('progress', e => { if (e.lengthComputable) { const per = (e.loaded / e.total) * 100; document.getElementById('progBar').style.width = per + '%'; } });
    xhr.onload = () => { alert('¡Subida completada!'); document.getElementById('progCont').style.display = 'none'; document.getElementById('progBar').style.width = '0'; loadGeneral(); };
    xhr.open('POST', window.location.pathname); xhr.send(fd);
}

async function openEdit(id) {
    const r = await fetch(`../api/mockups_varios.php?accion=uno&id=${id}`); const m = await r.json();
    document.getElementById('editId').value = m.id; document.getElementById('editPreview').innerHTML = m.tipo==='video'?`<video src="../${m.ruta}" controls style="width:100%;height:100%"></video>`:`<img src="../${m.ruta}" style="width:100%;height:100%;object-fit:contain">`;
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
async function openVinculos(id, ruta, tipo, skusStr) { currentVinMockupId = id; currentVinSkus = skusStr ? skusStr.split(',') : []; document.getElementById('vinPreview').innerHTML = tipo==='video'?`<video src="../${ruta}" autoplay muted loop style="width:100%;height:100%;object-fit:cover"></video>`:`<img src="../${ruta}" style="width:100%;height:100%;object-fit:cover">`; document.getElementById('vGrid').innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#666;padding:40px;">Selecciona una categoría...</div>'; document.getElementById('modalVinculos').style.display = 'flex'; renderVinActuales(); }
function renderVinActuales() { const cont = document.getElementById('vinActuales'); cont.innerHTML = currentVinSkus.length ? '' : '...'; currentVinSkus.forEach(sku => { const tag = document.createElement('div'); tag.style = 'background:rgba(212,175,55,0.15); border:1px solid var(--accent-gold); padding:8px 15px; border-radius:20px; font-size:0.85rem; display:flex; align-items:center; gap:10px;'; tag.innerHTML = `<span><b>${sku}</b></span><i class="fas fa-times-circle" style="cursor:pointer;color:#e74c3c;" onclick="toggleVinculo('${sku}')"></i>`; cont.appendChild(tag); }); }
async function loadVinCatalog() { const cat = document.getElementById('vCat').value, q = document.getElementById('vSearch').value.toLowerCase(); if(!cat && q.length < 3) return; const r = await fetch(`${window.location.pathname}?action=get_articles&categoria=${encodeURIComponent(cat)}&solo_base=true`); const d = await r.json(), grid = document.getElementById('vGrid'); grid.innerHTML = ''; (d.articulos || []).filter(a => a.nombre.toLowerCase().includes(q) || a.referencia.toLowerCase().includes(q)).forEach(a => { const isSel = currentVinSkus.includes(a.referencia), item = document.createElement('div'); item.className = 'art-item' + (isSel ? ' selected' : ''); item.onclick = () => toggleVinculo(a.referencia); item.innerHTML = `<div style="height:100px; background:#000;"><img src="../${a.foto_portada}" style="width:100%;height:100%;object-fit:cover;"></div><div style="padding:8px; font-size:0.75rem;"><div style="color:var(--accent-gold);">${a.nombre}</div><div>${a.referencia}</div></div>`; grid.appendChild(item); }); }
async function toggleVinculo(sku) { const isLinked = currentVinSkus.includes(sku), action = isLinked ? 'desvincular_multiple' : 'vincular_multiple'; if (isLinked) currentVinSkus = currentVinSkus.filter(s => s !== sku); else currentVinSkus.push(sku); renderVinActuales(); loadVinCatalog(); const fd = new FormData(); fd.append('accion', action); fd.append('id', currentVinMockupId); fd.append('sku', sku); await fetch('../api/mockups_varios.php', {method:'POST', body:fd}); }
function closeVinculos() { document.getElementById('modalVinculos').style.display = 'none'; loadGeneral(); loadArticles(); }
function deleteMockup() { if(confirm('¿Eliminar?')) { const fd = new FormData(); fd.append('accion','eliminar'); fd.append('id', document.getElementById('editId').value); fetch('../api/mockups_varios.php', {method:'POST',body:fd}).then(()=> { closeModal(); loadGeneral(); }); } }
function closeModal() { document.getElementById('modalEdit').style.display = 'none'; }
function openLB(url, tipo) { document.getElementById('lbMedia').innerHTML = tipo==='video'?`<video src="${url}" controls autoplay style="max-width:90vw;max-height:78vh;"></video>`:`<img src="${url}" style="max-width:90vw;max-height:78vh;">`; document.getElementById('lbDl').href = url; document.getElementById('lbOver').style.display = 'flex'; }
function closeLB() { document.getElementById('lbOver').style.display = 'none'; const v=document.querySelector('#lbMedia video'); if(v) v.pause(); }
function filterArt() { const q = document.getElementById('searchArt').value.toLowerCase(); document.querySelectorAll('.article-card').forEach(c => c.style.display = (c.dataset.sku.includes(q) || c.dataset.nom.includes(q)) ? '' : 'none'); }
function setArtFilter(f, btn) { artFilter = f; document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); loadArticles(); }
document.addEventListener('DOMContentLoaded', () => { loadArticles(); });
</script>
<?php require_once '../includes/footer.php'; ?>
