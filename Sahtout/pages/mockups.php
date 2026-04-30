<?php
define('ALLOWED_ACCESS', true);
require_once '../api/config.php';
$db = conectar();

// Función de apoyo para parsear metadatos (igual que en la API)
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

// Filtros para PHP (precargados)
$categorias_rows = $db->query("SELECT DISTINCT categoria FROM articulos WHERE categoria != '' ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);
$estancias_rows  = $db->query("SELECT DISTINCT estancia   FROM mockups_varios WHERE estancia   != '' ORDER BY estancia")->fetchAll(PDO::FETCH_COLUMN);
$estilos_rows    = $db->query("SELECT DISTINCT estilo     FROM mockups_varios WHERE estilo     != '' ORDER BY estilo")->fetchAll(PDO::FETCH_COLUMN);
$decos_rows      = $db->query("SELECT DISTINCT decoracion FROM mockups_varios WHERE decoracion != '' ORDER BY decoracion")->fetchAll(PDO::FETCH_COLUMN);

// AJAX: obtener artículos
if (isset($_GET['action']) && $_GET['action'] === 'get_articles') {
    header('Content-Type: application/json');
    $cat      = $_GET['categoria'] ?? '';
    $soloBase = ($_GET['solo_base'] ?? 'true') === 'true';
    $where = ["1=1"]; $params = [];
    if ($cat !== '__TODAS__' && !empty($cat)) { $where[] = "categoria = ?"; $params[] = $cat; }
    if ($soloBase) $where[] = "(es_variante = 'BASE' OR referencia REGEXP 'P01$' OR referencia REGEXP 'P01-')";
    $sql = "SELECT referencia, nombre, foto_portada, mockup FROM articulos WHERE " . implode(" AND ", $where) . " ORDER BY referencia ASC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($articulos as &$art) {
        $sku = $art['referencia']; $skuBase = preg_replace('/P\d+.*$/', '', $sku);
        $stmtM = $db->prepare("SELECT ruta FROM mockups_varios WHERE asignado_a_sku = ? OR asignado_a_sku LIKE ? OR archivo LIKE ?");
        $stmtM->execute([$sku, $skuBase . '%', '%' . $skuBase . '%']);
        $art['local_mockups'] = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    }
    echo json_encode(['articulos' => $articulos]);
    exit;
}

// POST: subir carpeta con metadatos automáticos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json');
    $count = 0;
    $paths = $_POST['paths'] ?? []; // Recibimos las rutas relativas desde JS
    foreach ($_FILES['files']['name'] as $i => $name) {
        $tmp = $_FILES['files']['tmp_name'][$i];
        $relPath = $paths[$i] ?? $name;
        
        // Detectar Marca por el nombre de la carpeta (primera parte de la ruta)
        $marcaN = 0; $marcaC = 0; $marcaZ = 0;
        if (stripos($relPath, 'noxertez') !== false) $marcaN = 1;
        if (stripos($relPath, 'candle') !== false) $marcaC = 1;
        if (stripos($relPath, 'zen') !== false) $marcaZ = 1;

        // Metadatos del nombre del archivo
        $meta = parseMetadata($name);
        
        // Destino final: mantenemos la estructura si queremos, o todo a la raíz de variados
        $dest = '../uploads/mockups_varios/' . $name;
        if (move_uploaded_file($tmp, $dest)) {
            $rutaRel = 'uploads/mockups_varios/' . $name;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $tipo = in_array($ext, ['mp4','mov']) ? 'video' : 'imagen';
            
            $ins = $db->prepare("INSERT INTO mockups_varios (archivo, ruta, tipo, estancia, luz, formato, decoracion, calidad, marca_noxertez, marca_candleholder, marca_zen) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $name, $rutaRel, $tipo, 
                $meta['estancia'], $meta['luz'], $meta['formato'], $meta['decoracion'], 
                'publicar', $marcaN, $marcaC, $marcaZ
            ]);
            $count++;
        }
    }
    echo json_encode(['mensaje' => "¡Éxito! $count mockups procesados con metadatos automáticos."]);
    exit;
}

$page_class = 'management-page';
require_once '../includes/header.php';
?>
<style>
:root{--bg-dark:#050b18;--bg-card:#0d1626;--accent-gold:#d4af37;--accent-green:#2ecc71;--border-glass:rgba(212,175,55,0.12);}
body{background-color:var(--bg-dark)!important;color:#e0e0e0;font-family:'Cinzel',serif;}
.mockups-container{padding:20px;max-width:1600px;margin:0 auto;}
.tabs-nav{display:flex;gap:20px;margin-bottom:30px;border-bottom:1px solid var(--border-glass);}
.tab-btn{background:none;border:none;color:#777;font-size:1.2rem;cursor:pointer;padding:10px 20px;font-family:'UnifrakturCook',cursive;transition:.3s;}
.tab-btn.active{color:var(--accent-gold);border-bottom:2px solid var(--accent-gold);}
.tab-content{display:none;}.tab-content.active{display:block;}
.filters-bar{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;align-items:center;background:rgba(255,255,255,0.02);padding:15px;border-radius:12px;border:1px solid var(--border-glass);}
.search-input{background:#08101d;border:1px solid var(--border-glass);color:#fff;padding:10px 14px;border-radius:8px;flex:1;min-width:180px;}
.select-wow{background:#08101d;border:1px solid var(--border-glass);color:#fff;padding:9px 12px;border-radius:8px;cursor:pointer;min-width:130px;}
.filter-btn{background:var(--bg-card);border:1px solid var(--border-glass);color:#fff;padding:8px 14px;border-radius:8px;cursor:pointer;transition:.2s;}
.filter-btn.active{background:var(--accent-gold);color:#000;font-weight:700;}
.mockups-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px;}
.mockup-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:12px;overflow:hidden;transition:.3s;}
.mockup-card:hover{transform:translateY(-4px);border-color:var(--accent-gold);}
.card-img{width:100%;height:210px;background:#000;overflow:hidden;cursor:pointer;position:relative;}
.card-img img,.card-img video{width:100%;height:100%;object-fit:cover;}
.card-body{padding:14px;}
.card-title{font-size:1rem;color:var(--accent-gold);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.card-sub{font-size:.8rem;color:#888;margin-bottom:10px;}
.thumbs{display:flex;gap:6px;margin-bottom:12px;overflow-x:auto;padding:3px;}
.thumb{width:50px;height:50px;border-radius:7px;object-fit:cover;cursor:pointer;border:2px solid transparent;flex:0 0 50px;}
.thumb.active{border-color:var(--accent-gold);}
.card-actions{display:flex;gap:7px;}
.btn{flex:1;padding:9px;border-radius:7px;border:1px solid var(--border-glass);background:rgba(255,255,255,0.05);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;font-size:.85rem;transition:.2s;}
.btn:hover{background:var(--accent-gold);color:#000;}
.btn-green{background:var(--accent-green);color:#fff;border-color:#27ae60;}
.btn-red{color:#ff4d4d;border-color:rgba(255,77,77,.2);flex:0 0 42px;}
.btn-gold{background:var(--accent-gold);color:#000;font-weight:700;}
.modal-over{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;justify-content:center;align-items:center;z-index:9000;backdrop-filter:blur(6px);}
.modal-box{background:var(--bg-card);border:1px solid var(--accent-gold);border-radius:14px;overflow:hidden;width:95%;max-width:1000px;}
.modal-head{padding:18px 22px;border-bottom:1px solid var(--border-glass);display:flex;justify-content:space-between;align-items:center;background:rgba(212,175,55,.05);}
.modal-head h2{margin:0;color:var(--accent-gold);font-size:1.2rem;}
.modal-body{padding:24px;max-height:82vh;overflow-y:auto;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
.form-group label{display:block;font-size:.78rem;color:#aaa;margin-bottom:5px;}
.lb-over{position:fixed;inset:0;background:rgba(0,0,0,.96);display:none;justify-content:center;align-items:center;z-index:9999;}
.lb-inner{text-align:center;max-width:92vw;}
.lb-close{position:absolute;top:18px;right:24px;color:#fff;font-size:2.5rem;cursor:pointer;line-height:1;}
.lb-dl{display:inline-block;margin-top:18px;background:var(--accent-gold);color:#000;padding:11px 28px;border-radius:28px;font-weight:700;text-decoration:none;}
.spinner{text-align:center;padding:40px;color:#aaa;font-size:1.3rem;}
.quality-badge{position:absolute;bottom:8px;left:8px;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700;text-transform:uppercase;}
.q-publicar{background:#2ecc71;color:#fff;}
.q-revisar{background:#f1c40f;color:#000;}
.q-descartar{background:#e74c3c;color:#fff;}
.upload-panel{background:rgba(212,175,55,0.05);border:1px dashed var(--accent-gold);border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;display:none;}
.upload-panel.active{display:block;}
</style>

<div class="mockups-container">
    <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('tab-art')">Por Artículo</button>
        <button class="tab-btn" onclick="switchTab('tab-gen');loadGeneral();">Banco General</button>
    </div>

    <!-- TAB: POR ARTÍCULO -->
    <div id="tab-art" class="tab-content active">
        <div class="filters-bar">
            <select id="selCat" class="select-wow" onchange="loadArticles()">
                <option value="__TODAS__">Todas las categorías</option>
                <?php foreach($categorias_rows as $c) echo "<option value=\"".htmlspecialchars($c)."\">".htmlspecialchars($c)."</option>"; ?>
            </select>
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;padding:8px 12px;border-radius:8px;border:1px solid var(--border-glass);font-size:.9rem;">
                <input type="checkbox" id="chkBase" checked onchange="loadArticles()" style="accent-color:var(--accent-gold);width:16px;height:16px;">
                Solo Base / P01
            </label>
            <div id="artCount" style="color:var(--accent-gold); font-size: 0.85rem; margin-left: 15px; font-family: monospace;">Cargando...</div>
            <div style="flex:1"></div>
            <input type="text" id="searchArt" class="search-input" placeholder="Buscar SKU o nombre..." oninput="filterArt()">
            <button class="filter-btn" onclick="setArtFilter('all',this)">Todos</button>
            <button class="filter-btn active" onclick="setArtFilter('with',this)">Con Mockup</button>
            <button class="filter-btn" onclick="setArtFilter('without',this)">Sin Mockup</button>
        </div>
        <div id="artLoading" class="spinner" style="display:none;"><i class="fas fa-circle-notch fa-spin"></i> Cargando artículos...</div>
        <div id="gridArt" class="mockups-grid"></div>
    </div>

    <!-- TAB: BANCO GENERAL -->
    <div id="tab-gen" class="tab-content">
        <div id="uploadPanel" class="upload-panel">
            <h3 style="color:var(--accent-gold);margin-top:0;">Subir carpeta de mockups</h3>
            <p style="color:#888;">El sistema detectará la <b>Marca</b> por el nombre de la carpeta y los <b>Metadatos</b> por el nombre del archivo.</p>
            <input type="file" id="folderInput" webkitdirectory directory multiple style="display:none;" onchange="handleFolderUpload()">
            <button class="btn btn-gold" onclick="document.getElementById('folderInput').click()" style="width:auto;padding:12px 30px;margin:10px auto;">
                <i class="fas fa-folder-open"></i> Seleccionar Carpeta
            </button>
            <div id="uploadStatus" style="margin-top:10px;font-size:0.9rem;color:var(--accent-green);"></div>
        </div>

        <div class="filters-bar">
            <select id="fMarca"     class="select-wow" onchange="loadGeneral()"><option value="">Marca</option><option value="NOXERTEZ">Noxertez</option><option value="CANDLEHOLDER">Candle Holder</option><option value="ZEN">Zen Garden</option></select>
            <select id="fEstancia"  class="select-wow" onchange="loadGeneral()"><option value="">Estancia</option><?php foreach($estancias_rows as $e) echo "<option value=\"$e\">$e</option>"; ?></select>
            <select id="fEstilo"    class="select-wow" onchange="loadGeneral()"><option value="">Estilo</option><?php foreach($estilos_rows as $e) echo "<option value=\"$e\">$e</option>"; ?></select>
            <select id="fDecoracion"class="select-wow" onchange="loadGeneral()"><option value="">Decoración</option><?php foreach($decos_rows as $d) echo "<option value=\"$d\">$d</option>"; ?></select>
            <select id="fCalidad"   class="select-wow" onchange="loadGeneral()"><option value="">Calidad</option><option value="publicar">Publicar</option><option value="revisar">Revisar</option><option value="descartar">Descartar</option></select>
            <input type="text" id="fBuscar" class="search-input" placeholder="Buscar..." oninput="loadGeneral()">
            <button class="btn btn-gold" onclick="toggleUpload()" style="flex:none;padding:9px 18px;"><i class="fas fa-upload"></i> Subir Carpeta</button>
            <button class="btn" onclick="syncCat()" style="flex:none;padding:9px 18px;"><i class="fas fa-sync" id="syncIcon"></i> Sincronizar</button>
        </div>
        <div id="genLoading" class="spinner" style="display:none;"><i class="fas fa-circle-notch fa-spin"></i> Cargando banco...</div>
        <div id="gridGen" class="mockups-grid"></div>
    </div>
</div>

<!-- MODALS Y LIGHTBOX -->
<div id="modalEdit" class="modal-over" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-head"><h2>Editar Mockup</h2><span style="cursor:pointer;font-size:1.6rem;color:#aaa;" onclick="closeModal()">&times;</span></div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:25px;">
                <div id="editPreview" style="height:480px;background:#000;border-radius:10px;overflow:hidden;"></div>
                <div>
                    <input type="hidden" id="editId">
                    <div class="form-grid">
                        <div class="form-group"><label>Estancia</label><input id="eEstancia" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Estilo</label><input id="eEstilo" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Luz</label><input id="eLuz" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Decoración</label><input id="eDecoracion" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Formato</label><input id="eFormato" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Color dominante</label><input id="eColor" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Temporada</label><input id="eTemporada" class="search-input" style="width:100%"></div>
                        <div class="form-group"><label>Calidad</label>
                            <select id="eCalidad" class="select-wow" style="width:100%">
                                <option value="publicar">Publicar</option>
                                <option value="revisar">Revisar</option>
                                <option value="descartar">Descartar</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;"><label>Notas</label><textarea id="eNotas" class="search-input" style="width:100%;height:70px;resize:vertical;"></textarea></div>
                    <div style="margin-bottom:16px;"><label style="display:flex;align-items:center;gap:10px;cursor:pointer;"><input type="checkbox" id="eFavorito" style="width:18px;height:18px;accent-color:var(--accent-gold);"> <b>Favorito ❤️</b></label></div>
                    <div style="display:flex;justify-content:space-between;gap:12px;">
                        <button class="btn btn-red" onclick="deleteMockup()" style="flex:none;padding:10px 22px;background:#c0392b;color:#fff;"><i class="fas fa-trash"></i> Borrar</button>
                        <button class="btn btn-gold" onclick="saveMockup()" style="flex:none;padding:10px 40px;"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="lbOver" class="lb-over" onclick="if(event.target===this||event.target.className==='lb-close')closeLB()">
    <span class="lb-close" onclick="closeLB()">&times;</span>
    <div class="lb-inner">
        <div id="lbMedia"></div>
        <a id="lbDl" href="#" download class="lb-dl"><i class="fas fa-download"></i> Descargar Master</a>
    </div>
</div>

<script>
let artFilter = 'with';

function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.currentTarget.classList.add('active');
}

function toggleUpload() { document.getElementById('uploadPanel').classList.toggle('active'); }

async function handleFolderUpload() {
    const input = document.getElementById('folderInput');
    if (!input.files.length) return;
    document.getElementById('uploadStatus').innerText = 'Subiendo ' + input.files.length + ' archivos...';
    const fd = new FormData();
    for (let f of input.files) { 
        fd.append('files[]', f); 
        fd.append('paths[]', f.webkitRelativePath); // IMPORTANTE: enviamos la ruta para detectar la marca
    }
    try {
        const r = await fetch(window.location.pathname, {method:'POST', body:fd});
        const d = await r.json();
        alert(d.mensaje);
        loadGeneral();
        toggleUpload();
    } catch(e) { alert('Error al subir carpeta'); }
}

async function loadArticles() {
    const cat = document.getElementById('selCat').value;
    const base = document.getElementById('chkBase').checked;
    document.getElementById('artLoading').style.display = 'block';
    document.getElementById('gridArt').innerHTML = '';
    document.getElementById('artCount').innerText = 'Conectando...';
    try {
        const url = `${window.location.pathname}?action=get_articles&categoria=${encodeURIComponent(cat)}&solo_base=${base}`;
        const r = await fetch(url);
        const d = await r.json();
        document.getElementById('artCount').innerText = `Recibidos: ${d.articulos?.length || 0} artículos.`;
        renderArticles(d.articulos || []);
    } catch(e) { document.getElementById('artCount').innerText = 'Error.'; }
    document.getElementById('artLoading').style.display = 'none';
}

function renderArticles(list) {
    const grid = document.getElementById('gridArt'); grid.innerHTML = '';
    let shown = 0;
    list.forEach(art => {
        const mocks = art.local_mockups || [];
        const hasMock = art.mockup || mocks.length > 0;
        if (artFilter === 'with' && !hasMock) return;
        if (artFilter === 'without' && hasMock) return;
        shown++;
        const card = document.createElement('div');
        card.className = 'mockup-card article-card';
        card.dataset.sku = art.referencia.toLowerCase();
        card.dataset.nom = art.nombre.toLowerCase();
        const thumbs = mocks.map(m => {
            const isVid = /\.(mp4|mov)$/i.test(m);
            return `<img src="../${isVid?'img/video_placeholder.jpg':m}" class="thumb" onclick="openLB('../${m}','${isVid?'video':'image'}')" title="${m}">`;
        }).join('');
        const dlBtn = mocks.length > 0
            ? `<button class="btn btn-green" style="flex:2" onclick="downloadAll(${JSON.stringify(mocks)})"><i class="fas fa-download"></i> Descargar ${mocks.length}</button>`
            : `<button class="btn" style="flex:2"><i class="fas fa-link"></i> Vincular</button>`;
        const delBtn = art.mockup ? `<button class="btn btn-red" onclick="quitarMockup('${art.referencia}')"><i class="fas fa-trash"></i></button>` : '';
        card.innerHTML = `
            <div class="card-img" onclick="openLB('../${art.foto_portada}','image')">
                <img src="../${art.foto_portada}" onerror="this.src='../img/placeholder_product.png'">
            </div>
            <div class="card-body">
                <div class="card-title">${art.nombre}</div>
                <div class="card-sub">SKU: ${art.referencia}</div>
                <div class="thumbs">${thumbs}</div>
                <div class="card-actions">
                    <button class="btn" onclick="openLB('../${art.foto_portada}','image')"><i class="fas fa-eye"></i></button>
                    ${dlBtn}${delBtn}
                </div>
            </div>`;
        grid.appendChild(card);
    });
    document.getElementById('artCount').innerText += ` Mostrando: ${shown}.`;
}

function filterArt() {
    const q = document.getElementById('searchArt').value.toLowerCase();
    document.querySelectorAll('.article-card').forEach(c => {
        c.style.display = (c.dataset.sku.includes(q) || c.dataset.nom.includes(q)) ? '' : 'none';
    });
}
function setArtFilter(f, btn) {
    artFilter = f;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadArticles();
}

async function loadGeneral() {
    document.getElementById('genLoading').style.display = 'block';
    document.getElementById('gridGen').innerHTML = '';
    const p = new URLSearchParams({
        accion: 'listar',
        marca:      document.getElementById('fMarca').value,
        estancia:   document.getElementById('fEstancia').value,
        estilo:     document.getElementById('fEstilo').value,
        decoracion: document.getElementById('fDecoracion').value,
        calidad:    document.getElementById('fCalidad').value,
        buscar:     document.getElementById('fBuscar').value,
    });
    try {
        const r = await fetch(`../api/mockups_varios.php?${p}`);
        const mocks = await r.json();
        const grid = document.getElementById('gridGen');
        mocks.forEach(m => {
            const card = document.createElement('div'); card.className = 'mockup-card';
            const isVid = m.tipo === 'video';
            card.innerHTML = `
                <div class="card-img" onclick="openLB('../${m.ruta}','${m.tipo}')">
                    ${isVid ? `<video src="../${m.ruta}"></video><i class="fas fa-play" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:2rem;color:rgba(255,255,255,.6)"></i>` : `<img src="../${m.ruta}" loading="lazy">`}
                    <span class="quality-badge q-${m.calidad}">${m.calidad}</span>
                </div>
                <div class="card-body">
                    <div class="card-title" title="${m.archivo}">${m.archivo}</div>
                    <div class="card-sub">${m.estancia||''} ${m.estilo?'| '+m.estilo:''} ${m.decoracion?'| '+m.decoracion:''}</div>
                    <div class="card-actions">
                        <button class="btn" onclick="openLB('../${m.ruta}','${m.tipo}')"><i class="fas fa-expand"></i></button>
                        <button class="btn" onclick="openEdit(${m.id})"><i class="fas fa-cog"></i></button>
                        <button class="btn btn-green" style="flex:2" onclick="openLB('../${m.ruta}','${m.tipo}')"><i class="fas fa-link"></i> ${m.asignado_a_sku||'Vincular'}</button>
                    </div>
                </div>`;
            grid.appendChild(card);
        });
    } catch(e) {}
    document.getElementById('genLoading').style.display = 'none';
}

async function syncCat() {
    const ic = document.getElementById('syncIcon'); ic.classList.add('fa-spin');
    try {
        const r = await fetch('../api/mockups_varios.php?accion=sync_catalog');
        const d = await r.json(); alert(d.mensaje); loadGeneral();
    } catch(e) { alert('Error al sincronizar'); }
    ic.classList.remove('fa-spin');
}

async function openEdit(id) {
    const r = await fetch(`../api/mockups_varios.php?accion=uno&id=${id}`);
    const m = await r.json();
    document.getElementById('editId').value = m.id;
    document.getElementById('editPreview').innerHTML = m.tipo === 'video'
        ? `<video src="../${m.ruta}" controls style="width:100%;height:100%"></video>`
        : `<img src="../${m.ruta}" style="width:100%;height:100%;object-fit:contain">`;
    document.getElementById('eEstancia').value  = m.estancia||'';
    document.getElementById('eEstilo').value    = m.estilo||'';
    document.getElementById('eLuz').value       = m.luz||'';
    document.getElementById('eDecoracion').value= m.decoracion||'';
    document.getElementById('eFormato').value   = m.formato||'';
    document.getElementById('eColor').value     = m.color_dominante||'';
    document.getElementById('eTemporada').value = m.temporada||'';
    document.getElementById('eCalidad').value   = m.calidad||'publicar';
    document.getElementById('eNotas').value     = m.notas||'';
    document.getElementById('eFavorito').checked= m.favorito==1;
    document.getElementById('modalEdit').style.display = 'flex';
}

function closeModal() { document.getElementById('modalEdit').style.display = 'none'; }

async function saveMockup() {
    const fd = new FormData();
    fd.append('accion','editar');
    fd.append('id', document.getElementById('editId').value);
    fd.append('estancia',   document.getElementById('eEstancia').value);
    fd.append('estilo',     document.getElementById('eEstilo').value);
    fd.append('luz',        document.getElementById('eLuz').value);
    fd.append('decoracion', document.getElementById('eDecoracion').value);
    fd.append('formato',    document.getElementById('eFormato').value);
    fd.append('color_dominante', document.getElementById('eColor').value);
    fd.append('temporada',  document.getElementById('eTemporada').value);
    fd.append('calidad',    document.getElementById('eCalidad').value);
    fd.append('notas',      document.getElementById('eNotas').value);
    fd.append('favorito',   document.getElementById('eFavorito').checked?1:0);
    await fetch('../api/mockups_varios.php', {method:'POST',body:fd});
    closeModal(); loadGeneral();
}

async function deleteMockup() {
    if (!confirm('¿Eliminar este mockup permanentemente?')) return;
    const fd = new FormData();
    fd.append('accion','eliminar');
    fd.append('id', document.getElementById('editId').value);
    await fetch('../api/mockups_varios.php', {method:'POST',body:fd});
    closeModal(); loadGeneral();
}

function downloadAll(mocks) {
    mocks.forEach((m,i) => setTimeout(() => { const a=document.createElement('a'); a.href='../'+m; a.download=''; a.click(); }, i*400));
}

function openLB(url, tipo) {
    const med = document.getElementById('lbMedia'); const dl = document.getElementById('lbDl');
    med.innerHTML = tipo === 'video'
        ? `<video src="${url}" controls autoplay style="max-width:90vw;max-height:78vh;"></video>`
        : `<img src="${url}" style="max-width:90vw;max-height:78vh;">`;
    dl.href = url;
    document.getElementById('lbOver').style.display = 'flex';
}
function closeLB() { document.getElementById('lbOver').style.display = 'none'; const v=document.querySelector('#lbMedia video'); if(v) v.pause(); }
document.addEventListener('keydown', e => { if(e.key==='Escape') { closeLB(); closeModal(); } });

document.addEventListener('DOMContentLoaded', () => { loadArticles(); loadGeneral(); });
</script>
<?php require_once '../includes/footer.php'; ?>
