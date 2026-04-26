<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
$page_class = 'management-page';
require_once('../includes/header.php');
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.3">
<style>
    .tab-container { display: none; }
    .tab-container.active { display: block; }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <h1><i class="fas fa-rocket"></i> Futuros Proyectos</h1>
        <button class="btn-premium-wow btn-gold" onclick="openModal('modalNuevo')">
            <i class="fas fa-plus"></i> Nuevo Proyecto
        </button>
    </div>

    <!-- Pestañas estilo PC -->
    <div style="display: flex; gap: 5px; margin-bottom: 0px; position: relative; z-index: 2;">
        <button id="btnReg" class="btn-premium-wow" onclick="switchTab('registro')" style="border-radius: 8px 8px 0 0; background: var(--accent-gold); color: var(--bg-main); border: 1px solid var(--border-glass); border-bottom: none;"><i class="fas fa-edit"></i> 1.1 Registro</button>
        <button id="btnGrid" class="btn-premium-wow" onclick="switchTab('grid')" style="border-radius: 8px 8px 0 0; background: rgba(255,255,255,0.05); color: var(--text-white); border: 1px solid var(--border-glass); border-bottom: none;"><i class="fas fa-th-large"></i> 1.2 Galeria</button>
        <button id="btnTable" class="btn-premium-wow" onclick="switchTab('table')" style="border-radius: 8px 8px 0 0; background: rgba(255,255,255,0.05); color: var(--text-white); border: 1px solid var(--border-glass); border-bottom: none;"><i class="fas fa-list"></i> 1.3 Tabla</button>
        <button id="btnRealizados" class="btn-premium-wow" onclick="switchTab('realizados')" style="border-radius: 8px 8px 0 0; background: rgba(255,255,255,0.05); color: var(--text-white); border: 1px solid var(--border-glass); border-bottom: none;"><i class="fas fa-check-double"></i> 1.4 Realizados</button>
    </div>

    <!-- Contenedor Pestaña Registro (1.1) -->
    <div id="tabRegistro" class="tab-container active" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); padding: 2.5rem; border-radius: 0 8px 8px 8px;">
        <h3 style="color: var(--accent-gold); margin-bottom: 2rem;"><i class="fas fa-plus-circle"></i> Registro de Nueva Idea / Proyecto</h3>
        <form id="formRegistro" onsubmit="guardarProyecto(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="input-group-wow">
                    <label>Nombre</label>
                    <input type="text" name="NOMBRE" class="input-wow" required>
                </div>
                <div class="input-group-wow">
                    <label>Categoría</label>
                    <input type="text" name="CATEGORIA" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>Subcategoría</label>
                    <input type="text" name="SUBCATEGORIA" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>Marca</label>
                    <input type="text" name="MARCA" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>Estado</label>
                    <select name="ESTADO" class="input-wow">
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="Urgente">Urgente</option>
                        <option value="En Proceso">En Proceso</option>
                    </select>
                </div>
                <div class="input-group-wow" style="grid-column: span 2;">
                    <label>Imagen del Proyecto (Subir o URL)</label>
                    <div style="display: flex; gap: 15px; align-items: flex-start;">
                        <div id="previewContainerReg" style="width: 100px; height: 100px; border: 2px dashed var(--border-glass); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: rgba(0,0,0,0.2); flex-shrink: 0;">
                            <img id="imgPreviewReg" src="img/logo.png" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <i id="imgIconReg" class="fas fa-image" style="font-size: 2rem; color: var(--border-glass);"></i>
                        </div>
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                            <input type="file" name="IMAGEN_UPLOAD" id="fileReg" class="input-wow" accept="image/*" onchange="previewImagen(this, 'Reg')" style="padding: 8px;">
                            <input type="text" name="FOTO_REFERENCIA" id="urlReg" class="input-wow" placeholder="O pega una URL de imagen aquí...">
                        </div>
                    </div>
                </div>
                <div class="input-group-wow">
                    <label>Precio</label>
                    <input type="text" name="PRECIO" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>Color</label>
                    <input type="text" name="COLOR" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>Festividad</label>
                    <input type="text" name="FESTIVIDAD" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>SKU Sugerido</label>
                    <input type="text" name="SKU" class="input-wow">
                </div>
                <div class="input-group-wow">
                    <label>Unidades Realizadas</label>
                    <input type="number" name="UNIDADES_REALIZADAS" class="input-wow" value="0">
                </div>
            </div>
            <div class="input-group-wow" style="margin-top: 20px;">
                <label>Descripción</label>
                <textarea name="DESCRIPCION" class="input-wow" style="height: 100px;"></textarea>
            </div>
            <button type="submit" class="btn-premium-wow btn-gold" style="margin-top: 2rem; width: 100%; justify-content: center;">💾 Guardar Registro 1.1</button>
        </form>
    </div>

    <!-- Contenedor Pestaña Grid (1.2) -->
    <div id="tabGrid" class="tab-container" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); padding: 2rem; border-radius: 0 8px 8px 8px;">
        <!-- Filtros -->
        <div style="margin-bottom: 2rem; display: flex; gap: 15px; align-items: center; background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border-glass);">
            <div style="display: flex; gap: 10px; flex: 1;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 11px; color: var(--accent-gold); margin-bottom: 5px; text-transform: uppercase; font-weight: bold;">Nombre / Termino</label>
                    <input type="text" id="filtroNombre" class="input-wow" placeholder="Buscar por nombre..." style="width: 100%;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 11px; color: var(--accent-gold); margin-bottom: 5px; text-transform: uppercase; font-weight: bold;">Categoría</label>
                    <div class="filter-controls">
                        <select id="filtroCategoria" onchange="cargarProyectos()" class="input-wow">
                            <option value="">-- Todas las Categorías --</option>
                        </select>
                        <button onclick="cargarProyectos('all')" class="btn-premium-wow" style="background: #4b5563; color: white;">Cargar Todo</button>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; padding-top: 18px;">
                <button class="btn-premium-wow btn-gold" onclick="filtrarProyectos()">
                    <i class="fas fa-search"></i> BUSCAR
                </button>
                <button class="btn-premium-wow" onclick="limpiarFiltros()" style="background: #4b5563; color: white;">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div id="proyectosGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;"></div>


    </div>

    <!-- Contenedor Pestaña Tabla (1.3) -->
    <div id="tabTable" class="tab-container" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); padding: 2rem; border-radius: 0 8px 8px 8px;">
        <div class="table-container-wow" style="margin-bottom: 0; border: none; background: transparent;">
            <table class="table-wow" id="tablaProyectos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Marca</th>
                        <th>Estado</th>
                        <th>Precio</th>
                        <th>SKU</th>
                        <th>Realizadas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyProyectos">
                    <!-- Se carga via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Contenedor Pestaña Realizados (1.4) -->
    <div id="tabRealizados" class="tab-container" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); padding: 2rem; border-radius: 0 8px 8px 8px;">
        <h3 style="color: var(--accent-gold); margin-bottom: 2rem;"><i class="fas fa-trophy"></i> Proyectos Convertidos en Realidad</h3>
        <div id="proyectosRealizadosGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;"></div>
    </div>
</div>

<!-- Modal Nuevo Proyecto -->
<div id="modalNuevo" class="modal-overlay-wow" onclick="if(event.target==this) closeModal('modalNuevo')">
    <div class="modal-content-wow" style="max-width: 600px;">
        <div class="modal-header-wow">
            <h2><i class="fas fa-magic"></i> Nuevo Proyecto</h2>
            <button class="btn-premium-wow" onclick="closeModal('modalNuevo')" style="background: none; color: var(--accent-gold); font-size: 1.5rem;">&times;</button>
        </div>
        <form id="formNuevo" onsubmit="guardarProyecto(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding: 2rem;">
                <div class="input-group-wow">
                    <label>Nombre del Proyecto</label>
                    <input type="text" name="NOMBRE" class="input-wow" required style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Categoría</label>
                    <input type="text" name="CATEGORIA" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Subcategoría</label>
                    <input type="text" name="SUBCATEGORIA" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Marca Sugerida</label>
                    <input type="text" name="MARCA" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Estado Inicial</label>
                    <select name="ESTADO" class="input-wow" style="width:100%;">
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="Urgente">Urgente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Terminado">Terminado</option>
                    </select>
                </div>
                <div class="input-group-wow">
                    <label>Precio Estimado</label>
                    <input type="text" name="PRECIO" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Color</label>
                    <input type="text" name="COLOR" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Festividad</label>
                    <input type="text" name="FESTIVIDAD" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>SKU Sugerido</label>
                    <input type="text" name="SKU" class="input-wow" style="width:100%;">
                </div>
                <div class="input-group-wow">
                    <label>Unidades Realizadas</label>
                    <input type="number" name="UNIDADES_REALIZADAS" class="input-wow" style="width:100%;">
                </div>
            </div>
            <div style="padding: 0 2rem;">
                <div class="input-group-wow" style="margin-top: 15px;">
                    <label>Descripción / Concepto</label>
                    <textarea name="DESCRIPCION" class="input-wow" style="height: 80px; width:100%; box-sizing:border-box;"></textarea>
                </div>
                <div class="input-group-wow" style="margin-top: 15px;">
                    <label>Imagen del Proyecto (Subir o URL)</label>
                    <div style="display: flex; gap: 15px; align-items: flex-start;">
                        <div id="previewContainerNuevo" style="width: 80px; height: 80px; border: 2px dashed var(--border-glass); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: rgba(0,0,0,0.2); flex-shrink: 0;">
                            <img id="imgPreviewNuevo" src="img/logo.png" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <i id="imgIconNuevo" class="fas fa-image" style="font-size: 1.5rem; color: var(--border-glass);"></i>
                        </div>
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 8px;">
                            <input type="file" name="IMAGEN_UPLOAD" id="fileNuevo" class="input-wow" accept="image/*" onchange="previewImagen(this, 'Nuevo')" style="padding: 5px; font-size: 12px;">
                            <input type="text" name="FOTO_REFERENCIA" id="urlNuevo" class="input-wow" placeholder="O pega una URL..." style="padding: 5px; font-size: 12px;">
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="padding: 1.5rem 2rem; border-top: 1px solid var(--border-glass); display: flex; gap: 10px;">
                <button type="submit" class="btn-premium-wow btn-gold" style="flex: 1; justify-content: center;">💾 Registrar Idea</button>
                <button type="button" class="btn-premium-wow" style="background: #4b5563; color: white; flex: 1; justify-content: center;" onclick="closeModal('modalNuevo')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
let proyectosGlobal = [];

function switchTab(tab) {
    document.querySelectorAll('.btn-premium-wow').forEach(b => {
        if(b.id === 'btnReg' || b.id === 'btnGrid' || b.id === 'btnTable' || b.id === 'btnRealizados') {
            b.style.background = 'rgba(255,255,255,0.05)';
            b.style.color = 'var(--text-white)';
        }
    });
    document.querySelectorAll('.tab-container').forEach(c => c.classList.remove('active'));
    
    if (tab === 'registro') {
        document.getElementById('btnReg').style.background = 'var(--accent-gold)';
        document.getElementById('btnReg').style.color = 'var(--bg-main)';
        document.getElementById('tabRegistro').classList.add('active');
    } else if (tab === 'grid') {
        document.getElementById('btnGrid').style.background = 'var(--accent-gold)';
        document.getElementById('btnGrid').style.color = 'var(--bg-main)';
        document.getElementById('tabGrid').classList.add('active');
        cargarProyectos();
    } else if (tab === 'table') {
        document.getElementById('btnTable').style.background = 'var(--accent-gold)';
        document.getElementById('btnTable').style.color = 'var(--bg-main)';
        document.getElementById('tabTable').classList.add('active');
        cargarProyectos('all').then(() => renderTabla());
    } else if (tab === 'realizados') {
        document.getElementById('btnRealizados').style.background = 'var(--accent-gold)';
        document.getElementById('btnRealizados').style.color = 'var(--bg-main)';
        document.getElementById('tabRealizados').classList.add('active');
        cargarRealizados();
    }
}

async function cargarRealizados() {
    const grid = document.getElementById('proyectosRealizadosGrid');
    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: white;">Cargando...</div>';
    
    try {
        const response = await fetch('api/index.php?ruta=futuros&action=getRealizados');
        const realizados = await response.json();

        grid.innerHTML = '';
        if (realizados.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: white; opacity: 0.6; padding: 3rem;">No hay proyectos realizados todavía. ¡A producir!</div>';
            return;
        }
        
        realizados.forEach(p => {
            const card = document.createElement('div');
            card.className = 'table-container-wow';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.padding = '0';
            card.style.overflow = 'hidden';
            card.style.border = '1px solid rgba(16, 185, 129, 0.3)'; // Verde sutil
            
            const nombre = p.NOMBRE || 'Sin nombre';
            const cat = p.CATEGORIA || 'Sin categoría';
            const numPedido = p.numero_pedido || '—';
            const fecha = p.fecha_entrega ? new Date(p.fecha_entrega).toLocaleDateString() : '—';
            
            let foto = p.FOTO_REFERENCIA || 'img/logo.png';
            if (foto.includes('\\') || foto.match(/^[A-Za-z]:\\/)) {
                const filename = foto.split('\\').pop();
                foto = 'uploads/articulos/proyectos/' + filename.split('/').map(encodeURIComponent).join('/');
            } else if (foto && !foto.includes('/') && !foto.startsWith('http')) {
                foto = 'uploads/articulos/proyectos/' + encodeURIComponent(foto);
            }

            card.innerHTML = `
                <div style="position: relative;">
                    <img src="${foto}" style="width: 100%; height: 180px; object-fit: cover; cursor: zoom-in;" onerror="this.src='img/logo.png'" onclick="verImagen('${foto}')">
                    <span class="badge-wow" style="position: absolute; top: 10px; right: 10px; background: #10b981;">
                        <i class="fas fa-check"></i> REALIZADO
                    </span>
                </div>
                <div style="padding: 1.2rem;">
                    <h3 style="margin: 0 0 5px 0; color: #10b981;">${nombre}</h3>
                    <p style="color: var(--text-gray); font-size: 11px; margin-bottom: 10px;">${cat} | Marca: ${p.MARCA || '—'}</p>
                    <div style="background: rgba(16, 185, 129, 0.1); padding: 10px; border-radius: 6px; font-size: 12px; color: var(--text-white);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Pedido:</span> <strong style="color: var(--accent-gold);">${numPedido}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Completado:</span> <strong>${fecha}</strong>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
    } catch (e) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444;">Error al cargar realizados.</div>';
    }
}

async function cargarProyectos(filtro = '') {
    const cat = document.getElementById('filtroCategoria').value;
    const grid = document.getElementById('proyectosGrid');
    
    let url = `api/index.php?ruta=futuros&filtro=${filtro === 'all' ? '' : filtro}&categoria=${cat}`;
    if (!filtro && !cat && filtro !== 'all') {
        url += '&limit=10';
    }

    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: white;">Cargando...</div>';
    
    try {
        const response = await fetch(url);
        proyectosGlobal = await response.json();

        grid.innerHTML = '';
        if (proyectosGlobal.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: white;">No se encontraron proyectos.</div>';
            return;
        }
        
        proyectosGlobal.forEach(p => {
            const card = document.createElement('div');
            card.className = 'table-container-wow';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.padding = '0';
            card.style.overflow = 'hidden';
            
            const nombre = p.NOMBRE || p.nombre || 'Sin nombre';
            const estado = p.ESTADO || p.estado || 'PENDIENTE';
            const cat = p.CATEGORIA || p.categoria || 'Sin categoría';
            const marca = p.MARCA || p.marca || 'Sin marca';
            const desc = p.DESCRIPCION || p.descripcion || 'Sin descripción';
            
            let foto = p.FOTO_REFERENCIA || p.foto_referencia || 'img/logo.png';
            // Transformar rutas locales absolutas (Windows) a rutas web correctas
            if (foto.includes('\\') || (foto.match(/^[A-Za-z]:\\/))) {
                // Es una ruta Windows absoluta: extraer solo el nombre del archivo
                const parts = foto.split('\\');
                const filename = parts[parts.length - 1];
                // URL-encodear para manejar espacios y caracteres especiales
                foto = 'uploads/articulos/proyectos/' + filename.split('/').map(encodeURIComponent).join('/');
            } else if (foto && !foto.includes('/') && !foto.startsWith('http')) {
                // Es solo un nombre de archivo sin ruta
                foto = 'uploads/articulos/proyectos/' + encodeURIComponent(foto);
            }




            
            const colorEstado = estado === 'Urgente' ? '#ef4444' : (estado === 'En Proceso' ? '#f59e0b' : '#10b981');
            
            card.innerHTML = `
                <div style="position: relative;">
                    <img src="${foto}" style="width: 100%; height: 200px; object-fit: cover; cursor: zoom-in;" onerror="this.src='img/logo.png'" onclick="verImagen('${foto}')" title="Click para ver a pantalla completa">
                    <span class="badge-wow" style="position: absolute; top: 10px; right: 10px; background: ${colorEstado};">
                        ${estado}
                    </span>
                </div>
                <div style="padding: 1.5rem;">
                    <h3 style="margin: 0 0 5px 0; color: var(--accent-gold);">${nombre}</h3>
                    <p style="color: var(--text-gray); font-size: 12px; margin-bottom: 10px;">${cat} | ${marca}</p>
                    <div style="flex-grow: 1; font-size: 13px; color: var(--text-white); margin-bottom: 15px; opacity: 0.8;">
                        ${desc}<br>
                        <small style="color: var(--accent-gold);">Price: ${p.PRECIO || p.precio || '—'} | SKU: ${p.SKU || p.sku || '—'}</small>
                    </div>
                    <div style="display: flex; gap: 5px;">
                        <button class="btn-premium-wow btn-blue" style="padding: 5px 10px; font-size: 12px;" onclick="enviarProduccion(${p.id}, '${nombre.replace(/'/g, "\\'")}')" title="Enviar a Producción"><i class="fas fa-industry"></i></button>
                        <button class="btn-premium-wow" style="padding: 5px 10px; font-size: 12px; background: #64748b; color: white;" onclick="editarProyecto(${p.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn-premium-wow" style="padding: 5px 10px; font-size: 12px; background: #ef4444; color: white;" onclick="eliminarProyecto(${p.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
        
    } catch (e) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444;">Error al cargar.</div>';
    }
}

function renderTabla() {
    const tbody = document.getElementById('tbodyProyectos');
    tbody.innerHTML = '';
    proyectosGlobal.forEach(p => {
        const tr = document.createElement('tr');
        const nombre = p.NOMBRE || p.nombre || 'Sin nombre';
        const cat = p.CATEGORIA || p.categoria || 'Sin categoría';
        const estado = p.ESTADO || p.estado || 'PENDIENTE';
        const fecha = p.fecha_creacion || p.FECHA_CREACION || '—';
        
        tr.innerHTML = `
            <td>${p.id}</td>
            <td style="color: var(--accent-gold); font-weight: bold;">${nombre}</td>
            <td>${cat}</td>
            <td>${p.MARCA || p.marca || '—'}</td>
            <td><span class="badge-wow" style="background: ${estado === 'Urgente' ? '#ef4444' : (estado === 'En Proceso' ? '#f59e0b' : '#10b981')}">${estado}</span></td>
            <td>${p.PRECIO || p.precio || '—'} €</td>
            <td>${p.SKU || p.sku || '—'}</td>
            <td>${p.UNIDADES_REALIZADAS || p.unidades_realizadas || 0}</td>
            <td>
                <button class="btn-premium-wow btn-blue" style="padding: 5px 10px; font-size: 12px;" onclick="editarProyecto(${p.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-premium-wow btn-red" style="padding: 5px 10px; font-size: 12px;" onclick="eliminarProyecto(${p.id})"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function filtrarProyectos() {
    const val = document.getElementById('filtroNombre').value;
    cargarProyectos(val);
}

function limpiarFiltros() {
    document.getElementById('filtroNombre').value = '';
    document.getElementById('filtroCategoria').value = '';
    cargarProyectos();
}

async function cargarCategorias() {
    try {
        const response = await fetch('api/index.php?ruta=futuros&action=getCategories');
        const cats = await response.json();
        const select = document.getElementById('filtroCategoria');
        cats.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            select.appendChild(opt);
        });
    } catch (e) {
        console.error("Error al cargar categorías", e);
    }
}


function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    if(id === 'modalNuevo') document.getElementById('formNuevo').reset();
}

async function guardarProyecto(e) {
    e.preventDefault();
    const btn = e.submitter;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btn.disabled = true;

    const formData = new FormData(e.target);
    const id = document.getElementById('edit_id') ? document.getElementById('edit_id').value : null;
    
    if(id) formData.append('id', id);

    try {
        const response = await fetch('api/index.php?ruta=futuros', {
            method: 'POST', // Siempre POST para multipart/form-data con archivos
            body: formData
        });
        const res = await response.json();
        if (res.ok) {
            alert('✅ Proyecto guardado correctamente');
            closeModal('modalNuevo');
            // Reset de vistas previas
            document.querySelectorAll('[id^="imgPreview"]').forEach(img => img.style.display = 'none');
            document.querySelectorAll('[id^="imgIcon"]').forEach(icon => icon.style.display = 'block');
            
            cargarProyectos();
        } else {
            alert('❌ Error: ' + (res.error || 'No se pudo guardar'));
        }
    } catch (e) {
        alert('❌ Error de conexión al guardar');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function previewImagen(input, suffix) {
    const preview = document.getElementById('imgPreview' + suffix);
    const icon = document.getElementById('imgIcon' + suffix);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            icon.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
        icon.style.display = 'block';
    }
}

async function editarProyecto(id) {
    const p = proyectosGlobal.find(x => x.id == id);
    if (!p) return;
    
    openModal('modalNuevo');
    const f = document.getElementById('formNuevo');
    f.NOMBRE.value = p.NOMBRE || p.nombre || '';
    f.CATEGORIA.value = p.CATEGORIA || p.categoria || '';
    f.SUBCATEGORIA.value = p.SUBCATEGORIA || p.subcategoria || '';
    f.MARCA.value = p.MARCA || p.marca || '';
    f.ESTADO.value = p.ESTADO || p.estado || 'PENDIENTE';
    f.FOTO_REFERENCIA.value = p.FOTO_REFERENCIA || p.foto_referencia || '';
    f.DESCRIPCION.value = p.DESCRIPCION || p.descripcion || '';
    f.PRECIO.value = p.PRECIO || p.precio || '';
    f.COLOR.value = p.COLOR || p.color || '';
    f.FESTIVIDAD.value = p.FESTIVIDAD || p.festividad || '';
    f.SKU.value = p.SKU || p.sku || '';
    f.UNIDADES_REALIZADAS.value = p.UNIDADES_REALIZADAS || p.unidades_realizadas || 0;
    
    // Añadir campo oculto ID si no existe
    let hid = document.getElementById('edit_id');
    if(!hid) {
        hid = document.createElement('input');
        hid.type = 'hidden';
        hid.id = 'edit_id';
        f.appendChild(hid);
    }
    hid.value = id;
}

async function eliminarProyecto(id) {
    if(!confirm('¿Seguro que quieres eliminar esta idea?')) return;
    try {
        await fetch(`api/index.php?ruta=futuros&id=${id}`, { method: 'DELETE' });
        cargarProyectos();
    } catch (e) { alert('Error al eliminar'); }
}

async function enviarProduccion(id, nombre) {
    if(!confirm(`¿Convertir "${nombre}" en un pedido real en el Kanban?`)) return;
    
    const p = proyectosGlobal.find(x => x.id == id);
    if (!p) { alert('No se encontró el proyecto'); return; }

    // Construir el pedido a partir del proyecto
    const pedidoData = {
        nombre_cliente: nombre,
        notas: `[Futuro Proyecto #${id}] ${p.DESCRIPCION || p.descripcion || ''}\nSKU: ${p.SKU || p.sku || '—'} | Precio: ${p.PRECIO || p.precio || '—'} | Marca: ${p.MARCA || p.marca || '—'}`,
        estado: 'por_empezar',
        canal: 'futuros_proyectos',
        sku_articulo: p.SKU || p.sku || '',
        prioridad: 'Verde',
        futuro_id: id,
        total: parseFloat(p.PRECIO || p.precio || 0) || 0,
        items: [{ nombre: nombre, cantidad: 1, precio: p.PRECIO || p.precio || 0 }]
    };

    try {
        const resp = await fetch('api/index.php?ruta=pedidos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pedidoData)
        });
        const res = await resp.json();
        if (res.ok) {
            // Marcar el proyecto como "En Proceso"
            await fetch('api/index.php?ruta=futuros', {
                method: 'PUT',
                body: JSON.stringify({ id: id, NOMBRE: nombre, CATEGORIA: p.CATEGORIA||'', SUBCATEGORIA: p.SUBCATEGORIA||'', MARCA: p.MARCA||'', ESTADO: 'En Proceso', FOTO_REFERENCIA: p.FOTO_REFERENCIA||'', DESCRIPCION: p.DESCRIPCION||'' })
            });
            alert(`✅ Pedido "${res.numero_pedido}" creado en el Kanban.\nEl proyecto ha sido marcado como "En Proceso".`);
            cargarProyectos();
        } else {
            alert('❌ Error al crear el pedido: ' + (res.error || 'Error desconocido'));
        }
    } catch (e) {
        alert('❌ Error de conexión: ' + e.message);
    }
}

// Inicializar
cargarCategorias();
cargarProyectos();

function verImagen(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightboxOverlay').style.display = 'flex';
}
function cerrarLightbox() {
    document.getElementById('lightboxOverlay').style.display = 'none';
    document.getElementById('lightboxImg').src = '';
}
</script>

<!-- Lightbox: visualizador pantalla completa -->
<div id="lightboxOverlay" onclick="cerrarLightbox()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:99999; align-items:center; justify-content:center; flex-direction:column; cursor:zoom-out;">
    <button onclick="cerrarLightbox()" style="position:absolute; top:20px; right:28px; background:none; border:none; color:#d4af37; font-size:2.5rem; cursor:pointer; line-height:1;">&times;</button>
    <img id="lightboxImg" src="" style="max-width:92vw; max-height:88vh; object-fit:contain; border-radius:8px; box-shadow:0 8px 48px rgba(0,0,0,0.8);" onclick="event.stopPropagation()">
    <p style="color:rgba(255,255,255,0.4); margin-top:12px; font-size:12px;">Click fuera de la imagen para cerrar</p>
</div>

<?php include('../includes/footer.php'); ?>
