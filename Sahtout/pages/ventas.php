<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../api/config.php';

$page_class = 'management-page';
include('../includes/header.php'); 
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.2">

<style>
    .tab-container { display: none; }
    .tab-container.active { display: block; }
    .nav-tabs-wow { display: flex; gap: 10px; margin-bottom: 2rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 10px; }
    .tab-link-wow { padding: 10px 20px; color: var(--text-gray); cursor: pointer; border-radius: 8px; transition: all 0.3s; font-weight: bold; }
    .tab-link-wow.active { background: var(--accent-gold); color: #000; }
    .tab-link-wow:hover:not(.active) { background: rgba(255,255,255,0.05); color: var(--text-white); }

    /* Buscador Visual */
    .grid-visual { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
    .card-visual { background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); border-radius: 12px; overflow: hidden; cursor: pointer; transition: all 0.3s; }
    .card-visual:hover { transform: translateY(-5px); border-color: var(--accent-gold); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
    .card-img { width: 100%; height: 180px; object-fit: cover; background: #000; }
    .card-info { padding: 15px; text-align: center; }
    .card-title { color: var(--text-white); font-size: 0.95rem; margin-bottom: 5px; font-weight: bold; }
    .card-sku { color: var(--accent-gold); font-size: 0.8rem; opacity: 0.8; }

    /* Detalle Artículo */
    .detail-container { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
    .detail-sidebar { background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass); border-radius: 15px; padding: 25px; text-align: center; }
    .detail-img { width: 100%; border-radius: 10px; margin-bottom: 20px; border: 2px solid var(--border-glass); }
    .detail-plats { background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass); border-radius: 15px; padding: 25px; }
    .plat-card { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; margin-bottom: 10px; }
    .plat-name { font-weight: bold; color: var(--text-white); flex: 1; }
    .plat-controls { display: flex; gap: 10px; align-items: center; }

    .input-mini { background: rgba(0,0,0,0.3); border: 1px solid var(--border-glass); color: white; padding: 5px 10px; border-radius: 5px; width: 80px; }
    .select-mini { background: rgba(0,0,0,0.3); border: 1px solid var(--border-glass); color: white; padding: 5px 10px; border-radius: 5px; }

    /* Doble Scrollbar */
    .top-scrollbar-wrapper { overflow-x: auto; overflow-y: hidden; height: 12px; margin-bottom: 5px; border-radius: 6px; background: rgba(255,255,255,0.02); }
    .top-scrollbar-dummy { height: 1px; }
    
    /* Scrollbar estético */
    ::-webkit-scrollbar { height: 8px; width: 8px; }
    ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
    ::-webkit-scrollbar-thumb { background: var(--accent-gold); border-radius: 10px; }

    /* Cabecera Fija (Sticky Header) */
    .table-wow thead th { position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.5); }
    .table-container-wow { max-height: 75vh; overflow: auto; position: relative; }

    /* Colores de Estado */
    .st-pendiente { background: rgba(255, 165, 0, 0.2) !important; color: #ffb347 !important; border: 1px solid rgba(255, 165, 0, 0.3) !important; border-radius: 6px; padding: 2px 8px; }
    .st-subido { background: rgba(16, 185, 129, 0.2) !important; color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; border-radius: 6px; padding: 2px 8px; }
    .st-vendido { background: rgba(59, 130, 246, 0.2) !important; color: #60a5fa !important; border: 1px solid rgba(59, 130, 246, 0.3) !important; border-radius: 6px; padding: 2px 8px; }
    .st-oculto { background: rgba(148, 163, 184, 0.2) !important; color: #cbd5e1 !important; border: 1px solid rgba(148, 163, 184, 0.3) !important; border-radius: 6px; padding: 2px 8px; }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <h1><i class="fas fa-chart-line"></i> Gestión de Plataformas de Venta</h1>
    </div>

    <!-- Navegación de Pestañas -->
    <div class="nav-tabs-wow">
        <div class="tab-link-wow active" onclick="switchTab('tab-visual', this)">🔍 Buscador Visual</div>
        <div class="tab-link-wow" onclick="switchTab('tab-tabla', this)">📊 Vista Tabla</div>
        <div class="tab-link-wow" onclick="switchTab('tab-detalle', this)" id="link-detalle">📝 Detalle Artículo</div>
    </div>

    <!-- Pestaña 1: Buscador Visual -->
    <div id="tab-visual" class="tab-container active">
        <div class="search-container" style="margin-bottom: 2rem; display: flex; gap: 15px;">
            <input type="text" id="searchVisual" class="input-wow" placeholder="Buscar por SKU o Nombre..." style="flex: 1;" oninput="filterVisual()">
            <select id="filterCatVisual" class="select-mini" style="width: 220px; height: 45px; font-weight: bold;" onchange="filterVisual()">
                <option value="">Todas las Categorías</option>
            </select>
        </div>
        <div id="gridVisual" class="grid-visual">
            <!-- Cargando... -->
        </div>
    </div>

    <!-- Pestaña 2: Vista Tabla -->
    <div id="tab-tabla" class="tab-container">
        <div class="search-container" style="margin-bottom: 1.5rem; display: flex; gap: 15px;">
            <input type="text" id="searchTabla" class="input-wow" placeholder="Filtrar tabla..." style="flex: 1;" oninput="filterTabla()">
            <select id="filterCatTabla" class="select-mini" style="width: 220px; height: 45px; font-weight: bold;" onchange="filterTabla()">
                <option value="">Todas las Categorías</option>
            </select>
        </div>
        
        <div id="topScrollWrapper" class="top-scrollbar-wrapper">
            <div id="topScrollDummy" class="top-scrollbar-dummy"></div>
        </div>
        
        <div id="tableContainer" class="table-container-wow" style="overflow-x: auto;">
            <table class="table-wow" id="ventasTable">
                <thead>
                    <tr id="thead-row">
                        <th style="width: 50px;">📸</th>
                        <th>SKU</th>
                        <th>Nombre</th>
                        <th>Online</th>
                        <th>Físico</th>
                        <!-- Columnas dinámicas de plataformas -->
                    </tr>
                </thead>
                <tbody id="tbody-ventas">
                    <!-- Datos... -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pestaña 3: Detalle por Artículo -->
    <div id="tab-detalle" class="tab-container">
        <div id="no-selection" style="text-align: center; padding: 5rem; opacity: 0.5;">
            <i class="fas fa-search" style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <h3>Selecciona un artículo del buscador o la tabla para ver sus detalles.</h3>
        </div>
        <div id="detail-content" class="detail-container" style="display: none;">
            <div class="detail-sidebar">
                <img id="det-img" src="" class="detail-img" onerror="this.src='img/logo.png'">
                <h2 id="det-nombre" style="color: var(--accent-gold); margin-bottom: 5px;">Nombre del Producto</h2>
                <p id="det-sku" style="opacity: 0.7; margin-bottom: 20px;">SKU: -</p>
                
                <div class="nox-form-group" style="text-align: left; margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--accent-gold);">Stock Online (Venta):</label>
                    <input type="number" id="inp-stock-online" class="input-wow" style="width: 100%;">
                </div>
                <div class="nox-form-group" style="text-align: left; margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--accent-green);">Stock Físico (Terminados):</label>
                    <input type="number" id="inp-stock-fisico" class="input-wow" style="width: 100%;">
                </div>

                <button onclick="saveAll()" class="btn-premium-wow btn-gold" style="width: 100%; justify-content: center;">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
            
            <div class="detail-plats">
                <h3 style="margin-bottom: 20px; color: var(--accent-gold);">Estados en Plataformas</h3>
                <div id="plats-list">
                    <!-- Lista de plataformas -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let allData = [];
let plataformas = [];
let selectedSku = null;

async function init() {
    // 1. Obtener plataformas
    const resPlats = await fetch('../api/index.php?ruta=ventas&accion=get_plataformas');
    plataformas = await resPlats.json();
    
    // Configurar cabecera de tabla
    const thead = document.getElementById('thead-row');
    plataformas.forEach(p => {
        const th = document.createElement('th');
        th.innerText = p.nombre_visible;
        thead.appendChild(th);
    });

    // 2. Obtener datos
    await loadData();

    // 3. Sincronizar scrollbar doble
    const top = document.getElementById('topScrollWrapper');
    const bot = document.getElementById('tableContainer');
    
    top.onscroll = () => { bot.scrollLeft = top.scrollLeft; };
    bot.onscroll = () => { top.scrollLeft = bot.scrollLeft; };
}

async function loadData() {
    const res = await fetch('../api/index.php?ruta=ventas&accion=get_datos');
    allData = await res.json();
    
    // Poblar dropdowns de categorías
    const cats = [...new Set(allData.map(p => p.CATEGORIA).filter(c => c))].sort();
    const selects = [document.getElementById('filterCatVisual'), document.getElementById('filterCatTabla')];
    
    selects.forEach(sel => {
        // Mantener el "Todas..."
        sel.innerHTML = '<option value="">Todas las Categorías</option>';
        cats.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.innerText = c;
            sel.appendChild(opt);
        });
    });

    renderVisual();
    renderTabla();
}

function switchTab(tabId, el) {
    document.querySelectorAll('.tab-container').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-link-wow').forEach(l => l.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    el.classList.add('active');

    if (tabId === 'tab-tabla') {
        setTimeout(syncScrollWidth, 100);
    }
}

function syncScrollWidth() {
    const table = document.getElementById('ventasTable');
    const dummy = document.getElementById('topScrollDummy');
    if (table && dummy) {
        dummy.style.width = table.scrollWidth + 'px';
    }
}

function getImgPath(foto) {
    if (!foto || foto === 'img/logo.png') return 'img/logo.png';
    
    // Normalizar a barras inclinadas
    let clean = foto.replace(/\\/g, '/');
    
    // Si contiene '/imagenes/', extraer desde ahí
    const imgIndex = clean.indexOf('/imagenes/');
    if (imgIndex !== -1) {
        return 'uploads/articulos' + clean.substring(imgIndex);
    }
    
    // Si es una ruta de Windows absoluta pero no tiene /imagenes/
    if (clean.includes(':/')) {
        const parts = clean.split('/');
        return 'uploads/articulos/imagenes/' + parts[parts.length - 1];
    }
    
    // Si ya es relativa o el fallback final
    return clean.startsWith('uploads/') ? clean : 'uploads/' + clean;
}

function renderVisual() {
    const grid = document.getElementById('gridVisual');
    grid.innerHTML = '';
    
    allData.forEach(p => {
        let foto = getImgPath(p.FOTO_PORTADA);

        const card = document.createElement('div');
        card.className = 'card-visual';
        card.dataset.category = p.CATEGORIA || '';
        card.onclick = () => showDetail(p.SKU_REF);
        card.innerHTML = `
            <img src="${foto}" class="card-img" onerror="this.src='img/logo.png'">
            <div class="card-info">
                <div class="card-title">${p.NOMBRE}</div>
                <div class="card-sku">${p.SKU_REF}</div>
                <div style="font-size: 0.75rem; color: var(--accent-gold); margin-top: 5px; opacity: 0.6;">${p.CATEGORIA || 'Sin Categoría'}</div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function renderTabla() {
    const tbody = document.getElementById('tbody-ventas');
    tbody.innerHTML = '';

    allData.forEach(p => {
        const tr = document.createElement('tr');
        tr.className = 'articulo-row';
        tr.dataset.category = p.CATEGORIA || '';
        tr.onclick = () => showDetail(p.SKU_REF);
        tr.style.cursor = 'pointer';

        let foto = getImgPath(p.FOTO_PORTADA);
        let html = `
            <td style="padding: 5px;"><img src="${foto}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-glass);"></td>
            <td>${p.SKU_REF}</td>
            <td>
                <div style="font-weight: bold; color: var(--accent-gold);">${p.NOMBRE}</div>
                <small style="opacity: 0.5;">${p.CATEGORIA || '-'}</small>
            </td>
            <td style="color: var(--accent-gold); font-weight: bold; text-align: center;">${p.STOCK_ONLINE}</td>
            <td style="color: var(--accent-green); font-weight: bold; text-align: center;">${p.STOCK_FISICO}</td>
        `;

        plataformas.forEach(plat => {
            const estado = p[plat.nombre_columna + '_ESTADO'] || '-';
            const precio = p[plat.nombre_columna + '_PRECIO'] || '-';
            
            // Clase de color según estado
            let cls = '';
            const estNorm = estado.toLowerCase();
            if (estNorm.includes('pendiente')) cls = 'st-pendiente';
            else if (estNorm.includes('subido')) cls = 'st-subido';
            else if (estNorm.includes('vendido')) cls = 'st-vendido';
            else if (estNorm.includes('oculto')) cls = 'st-oculto';

            html += `<td><span class="${cls}">${estado}</span> <br><small style="opacity:0.6; margin-top: 5px; display: inline-block;">${precio}€</small></td>`;
        });

        tr.innerHTML = html;
        tbody.appendChild(tr);
    });

    // Ajustar ancho del scroll dummy
    setTimeout(syncScrollWidth, 100);
}

function filterVisual() {
    const txt = document.getElementById('searchVisual').value.toLowerCase();
    const cat = document.getElementById('filterCatVisual').value;
    
    document.querySelectorAll('.card-visual').forEach(card => {
        const content = card.innerText.toLowerCase();
        const cardCat = card.dataset.category;
        
        const matchTxt = content.includes(txt);
        const matchCat = cat === '' || cardCat === cat;
        
        card.style.display = (matchTxt && matchCat) ? 'block' : 'none';
    });
}

function filterTabla() {
    const txt = document.getElementById('searchTabla').value.toLowerCase();
    const cat = document.getElementById('filterCatTabla').value;
    
    document.querySelectorAll('#tbody-ventas tr').forEach(row => {
        const content = row.innerText.toLowerCase();
        const rowCat = row.dataset.category;
        
        const matchTxt = content.includes(txt);
        const matchCat = cat === '' || rowCat === cat;
        
        row.style.display = (matchTxt && matchCat) ? '' : 'none';
    });
}

async function showDetail(sku) {
    selectedSku = sku;
    const res = await fetch(`../api/index.php?ruta=ventas&accion=get_art&sku=${sku}`);
    const data = await res.json();
    
    const p = data.producto;
    const v = data.ventas;

    document.getElementById('no-selection').style.display = 'none';
    document.getElementById('detail-content').style.display = 'grid';
    
    document.getElementById('det-nombre').innerText = p.NOMBRE;
    document.getElementById('det-sku').innerText = 'SKU: ' + p.SKU_REF;
    document.getElementById('inp-stock-online').value = p.STOCK_ONLINE;
    document.getElementById('inp-stock-fisico').value = p.STOCK_FISICO;

    document.getElementById('det-img').src = getImgPath(p.FOTO_PORTADA);

    // Renderizar lista de plataformas
    const list = document.getElementById('plats-list');
    list.innerHTML = '';
    
    plataformas.forEach(plat => {
        const col = plat.nombre_columna;
        const div = document.createElement('div');
        div.className = 'plat-card';
        div.innerHTML = `
            <div class="plat-name">${plat.nombre_visible}</div>
            <div class="plat-controls">
                <select class="select-mini" data-col="${col}_ESTADO">
                    <option value="Pendiente" ${v[col+'_ESTADO'] == 'Pendiente' ? 'selected' : ''}>Pendiente</option>
                    <option value="Subido" ${v[col+'_ESTADO'] == 'Subido' ? 'selected' : ''}>Subido</option>
                    <option value="Vendido" ${v[col+'_ESTADO'] == 'Vendido' ? 'selected' : ''}>Vendido</option>
                    <option value="Oculto" ${v[col+'_ESTADO'] == 'Oculto' ? 'selected' : ''}>Oculto</option>
                </select>
                <input type="number" step="0.01" class="input-mini" data-col="${col}_PRECIO" value="${v[col+'_PRECIO'] || 0}" placeholder="Precio">
                <span style="color: var(--accent-gold)">€</span>
            </div>
            <div class="plat-url" style="margin-top: 5px;">
                <input type="text" class="input-wow" data-col="${col}_URL" value="${v[col+'_URL'] || ''}" placeholder="Dirección de la página (URL)" style="width: 100%; font-size: 0.8rem;">
            </div>
        `;
        list.appendChild(div);
    });

    switchTab('tab-detalle', document.getElementById('link-detalle'));
}

async function saveAll() {
    if (!selectedSku) return;

    const body = {
        sku_base: selectedSku,
        stock_online: document.getElementById('inp-stock-online').value || 0,
        stock_fisico: document.getElementById('inp-stock-fisico').value || 0
    };

    // Recoger datos de plataformas dinámicamente
    plataformas.forEach(plat => {
        const col = plat.nombre_columna;
        document.querySelectorAll(`[data-col^="${col}_"]`).forEach(el => {
            let val = el.value;
            // Si el nombre del campo sugiere que es numérico y está vacío, poner 0
            if (val === '' && (el.dataset.col.includes('_PRECIO') || el.dataset.col.includes('CANTIDAD'))) {
                val = 0;
            }
            body[el.dataset.col] = val;
        });
    });

    const res = await fetch('../api/index.php?ruta=ventas&accion=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });

    const result = await res.json();
    if (result.success) {
        alert('Datos guardados correctamente');
        await loadData();
    } else {
        alert('Error: ' + result.error);
    }
}

// Iniciar aplicación
init();
</script>

<?php include('../includes/footer.php'); ?>
