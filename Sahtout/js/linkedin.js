
const BASE_PATH = '../';
let modoActual = 'manual';
let paginaActual = 1;

console.log("LinkedIn External Script loading...");

window.resolverRutaJS = function(foto) {
    if (!foto || foto === 'img/logo.png') return BASE_PATH + 'img/logo.png';
    if (foto.startsWith('http')) return foto;
    const clean = foto.replace(/\\/g, '/');
    let path = clean;
    if (path.startsWith('../')) path = path.substring(3);
    if (path.startsWith('uploads/')) return BASE_PATH + path;
    return BASE_PATH + 'uploads/mockups_varios/' + path;
}

window.editarPost = async function(id) {
    console.log("Acción: Editar post", id);
    try {
        const r = await fetch(`../api/linkedin_publish.php?accion=get&id=${id}`);
        const d = await r.json();
        if (d.ok && d.item) {
            document.getElementById('edit-id').value = d.item.id;
            document.getElementById('edit-texto').value = d.item.texto;
            const fecha = d.item.fecha_programada ? d.item.fecha_programada.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('edit-fecha').value = fecha;
            document.getElementById('edit-imagen').value = d.item.imagen_url;
            document.getElementById('edit-preview').src = window.resolverRutaJS(d.item.imagen_url);
            document.getElementById('modal-edit').style.display = 'flex';
        }
    } catch(e) { console.error("Error en editarPost:", e); }
}

window.guardarEdicion = async function() {
    const data = {
        id: document.getElementById('edit-id').value,
        texto: document.getElementById('edit-texto').value,
        fecha_programada: document.getElementById('edit-fecha').value,
        imagen_url: document.getElementById('edit-imagen').value
    };
    try {
        const r = await fetch('../api/linkedin_publish.php?accion=update', { 
            method: 'POST', 
            body: JSON.stringify(data) 
        });
        const d = await r.json();
        if (d.ok) {
            document.getElementById('modal-edit').style.display = 'none';
            mostrarResultado('✅ Cambios guardados', 'ok');
            cargarCola(paginaActual);
        } else {
            mostrarResultado('❌ ' + d.error, 'error');
        }
    } catch(e) { console.error("Error en guardarEdicion:", e); }
}

window.getEstadoColor = function(est) {
    if (est === 'publicado') return 'green';
    if (est === 'pendiente') return 'orange';
    if (est === 'error') return 'red';
    return 'gray';
}

window.borrarPost = async function(id) {
    if (!confirm('¿Eliminar este post de la cola?')) return;
    try {
        const r = await fetch(`../api/linkedin_publish.php?accion=delete&id=${id}`);
        const d = await r.json();
        if (d.ok) cargarCola(paginaActual);
    } catch(e) { console.error(e); }
}

window.publicarIndividual = async function(id) {
    if (!confirm('¿Publicar este post ahora?')) return;
    try {
        const r = await fetch(`../api/linkedin_publish.php?accion=publish_single&id=${id}`);
        const d = await r.json();
        if (d.ok) {
            mostrarResultado('✅ ¡Publicado!', 'ok');
            cargarCola(paginaActual);
        } else {
            mostrarResultado('❌ ' + d.error, 'error');
        }
    } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
}

window.switchTab = function(id, el) {
    document.querySelectorAll('.tab-container').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-link-wow').forEach(l => l.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    el.classList.add('active');
    if (id === 'tab-cola') cargarCola();
    if (id === 'tab-stats') cargarStats();
}

window.setModoRedactor = function(modo, el) {
    modoActual = modo;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('form-manual').style.display = (modo === 'automatico') ? 'none' : 'block';
    document.getElementById('ia-options').style.display = (modo === 'ia') ? 'block' : 'none';
    document.getElementById('ia-auto-options').style.display = (modo === 'automatico') ? 'block' : 'none';
    document.getElementById('li-texto-group').style.display = (modo === 'automatico') ? 'none' : 'block';
    const colDerecha = document.querySelector('.config-grid > .config-card:last-child');
    if (colDerecha && modo === 'automatico') {
        colDerecha.style.display = 'none';
        document.querySelector('.config-grid').style.gridTemplateColumns = '1fr';
    } else if (colDerecha) {
        colDerecha.style.display = 'block';
        document.querySelector('.config-grid').style.gridTemplateColumns = '1.2fr 0.8fr';
    }
}

window.checkTipoProducto = function() {
    const tipo = document.getElementById('li_tipo').value;
    document.getElementById('group-producto').style.display = (tipo === 'producto') ? 'block' : 'none';
}

window.filtrarProductosSelector = function() {
    const cat = document.getElementById('li_filtro_cat').value.toLowerCase();
    const txt = document.getElementById('li_filtro_txt').value.toLowerCase();
    const options = document.getElementById('li_sku').options;
    for (let i = 1; i < options.length; i++) {
        const option = options[i];
        const optCat = (option.dataset.categoria || '').toLowerCase();
        const optTxt = option.text.toLowerCase();
        const matchCat = !cat || optCat === cat;
        const matchTxt = !txt || optTxt.includes(txt);
        option.style.display = (matchCat && matchTxt) ? 'block' : 'none';
    }
}

window.cargarDatosProducto = function(sku) {
    if (!sku) {
        document.getElementById('sku-preview-img').style.display = 'none';
        document.getElementById('sku-preview-placeholder').style.display = 'block';
        return;
    }
    const opt = document.querySelector(`#li_sku option[value="${sku}"]`);
    const nombre = opt.dataset.nombre;
    const foto_raw = opt.dataset.foto;
    if (modoActual === 'manual') {
        document.getElementById('li_texto').value = `Presentamos nuestro ${nombre}. Hecho a mano con madera natural. #Noxertez #Artesania`;
        actualizarContador();
    }
    if (foto_raw) {
        const foto_final = resolverRutaJS(foto_raw);
        document.getElementById('li_imagen_url').value = foto_raw;
        previewImagen(foto_final);
        const quickImg = document.getElementById('sku-preview-img');
        const quickPh = document.getElementById('sku-preview-placeholder');
        quickImg.src = foto_final;
        quickImg.style.display = 'block';
        quickPh.style.display = 'none';
    }
}

window.previewImagen = function(url) {
    const img = document.getElementById('img-preview');
    const empty = document.getElementById('img-preview-empty');
    if (url) {
        img.src = url;
        img.style.display = 'block';
        if(empty) empty.style.display = 'none';
    } else {
        img.style.display = 'none';
        if(empty) empty.style.display = 'block';
    }
}

window.actualizarContador = function() {
    const n = document.getElementById('li_texto').value.length;
    const el = document.getElementById('char-count');
    if(el) {
        el.textContent = n;
        el.style.color = (n > 2800) ? '#ef4444' : 'var(--text-gray)';
    }
}

window.mostrarResultado = function(msg, tipo) {
    const el = document.getElementById('resultado');
    const log = document.getElementById('main-activity-log');
    
    // 1. Mostrar mensaje temporal arriba
    if(el) {
        const colores = {
            ok:    { bg:'rgba(16,185,129,0.15)', border:'#10b981', color:'#10b981' },
            error: { bg:'rgba(239,68,68,0.15)',  border:'#ef4444', color:'#ef4444' },
            info:  { bg:'rgba(212,175,55,0.15)', border:'var(--accent-gold)', color:'var(--accent-gold)' }
        };
        const c = colores[tipo] || colores.info;
        el.style.cssText = `display:block; background:${c.bg}; border:1px solid ${c.border}; color:${c.color}; padding:12px 16px; border-radius:8px; margin-bottom:1rem; font-weight:bold;`;
        el.innerHTML = msg;
        setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    // 2. Añadir al Log Persistente (Verde Matrix)
    if(log) {
        const time = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        const color = (tipo === 'error') ? '#ff4444' : (tipo === 'ok' ? '#00ff00' : '#d4af37');
        entry.innerHTML = `<span class="log-time">[${time}]</span> <span style="color:${color}">${msg}</span>`;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    }
}

window.guardarCredenciales = async function() {
    const data = { 
        client_id: document.getElementById('li_client_id').value, 
        client_secret: document.getElementById('li_client_secret').value, 
        pps: document.getElementById('li_pps').value 
    };
    try {
        const r = await fetch('../api/linkedin_oauth.php?accion=save_config', { method: 'POST', body: JSON.stringify(data) });
        const d = await r.json();
        if (d.ok) { 
            mostrarResultado('✅ Credenciales guardadas', 'ok'); 
            setTimeout(() => location.reload(), 1000); 
        } else {
            mostrarResultado('❌ ' + d.error, 'error');
        }
    } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
}

window.autorizarLinkedIn = function() { window.location.href = '../api/linkedin_oauth.php?step=1'; }

window.renovarToken = async function() {
    try {
        const r = await fetch('../api/linkedin_oauth.php?accion=refresh');
        const d = await r.json();
        if (d.ok) { 
            mostrarResultado('✅ Token renovado', 'ok'); 
            setTimeout(() => location.reload(), 1000); 
        } else {
            mostrarResultado('❌ ' + d.error, 'error');
        }
    } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
}

window.verificarCuenta = async function() {
    try {
        const r = await fetch('../api/linkedin_oauth.php?accion=verify');
        const d = await r.json();
        if (d.ok) {
            document.getElementById('perfil-info').innerHTML = `
                <div style="display:flex; align-items:center; gap:10px; justify-content:center;">
                    <img src="${d.profile.picture || ''}" style="width:40px; height:40px; border-radius:50%;">
                    <strong>${d.profile.name}</strong>
                </div>
                <p>URN: <code>${d.profile.sub}</code></p>`;
            mostrarResultado('✅ Cuenta verificada', 'ok');
        } else {
            mostrarResultado('❌ ' + d.error, 'error');
        }
    } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
}

window.generarConIA = async function() {
    const tipo = document.getElementById('li_tipo').value;
    const sku = document.getElementById('li_sku').value;
    const contexto = document.getElementById('li_ia_contexto').value;
    const tono = document.getElementById('li_ia_tono').value;
    document.getElementById('spinner-ia').style.display = 'flex';
    try {
        const r = await fetch('../api/linkedin_generate.php', { 
            method: 'POST', 
            body: JSON.stringify({ tipo, sku_ref: sku, contexto, tono }) 
        });
        const d = await r.json();
        if (d.ok) { 
            document.getElementById('li_texto').value = d.texto; 
            actualizarContador(); 
            mostrarResultado('✅ Texto generado', 'ok'); 
        } else {
            mostrarResultado('❌ ' + d.error, 'error');
        }
    } catch(e) { mostrarResultado('❌ Error: ' + e.message, 'error'); }
    document.getElementById('spinner-ia').style.display = 'none';
}

window.mockupsSeleccionados = [];
window.previewAutoMockups = async function() {
    const data = {
        accion: 'get_mockups',
        categoria: document.getElementById('li_auto_cat').value,
        estancia: document.getElementById('li_auto_estancia').value,
        decoracion: document.getElementById('li_auto_deco').value,
        cantidad: document.getElementById('li_auto_cantidad').value
    };
    const container = document.getElementById('auto-mockups-preview');
    if(!container) return;
    container.innerHTML = '<div style="grid-column:1/-1; text-align:center;"><i class="fas fa-circle-notch fa-spin"></i> Buscando...</div>';
    try {
        const r = await fetch('../api/linkedin_auto.php', { method: 'POST', body: JSON.stringify(data) });
        const d = await r.json();
        if (d.ok) {
            window.mockupsSeleccionados = d.mockups;
            container.innerHTML = '';
            if (window.mockupsSeleccionados.length === 0) {
                container.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#ef4444;">⚠️ Sin mockups.</div>';
                return;
            }
            window.mockupsSeleccionados.forEach((m, idx) => {
                const item = document.createElement('div');
                item.className = 'auto-mockup-item';
                item.innerHTML = `<img src="${resolverRutaJS(m.ruta)}" title="${m.archivo}"><div class="auto-mockup-remove" onclick="removeMockupFromAuto(${idx})">&times;</div>`;
                container.appendChild(item);
            });
        }
    } catch(e) { container.innerHTML = '❌ Error'; }
}

window.removeMockupFromAuto = function(idx) {
    window.mockupsSeleccionados.splice(idx, 1);
    previewAutoMockups(); 
}

window.programarAutomatico = async function() {
    if (window.mockupsSeleccionados.length === 0) return mostrarResultado('⚠️ Selecciona mockups', 'error');
    const context = document.getElementById('li_auto_contexto').value;
    const tone = document.getElementById('li_auto_tono').value;
    const total = window.mockupsSeleccionados.length;
    if (!confirm(`¿Programar ${total} posts?`)) return;
    const btn = document.getElementById('btn-auto-programar');
    btn.disabled = true;
    document.getElementById('spinner-auto').style.display = 'flex';
    let apiCalls = 0;
    for (let i = 0; i < total; i++) {
        const m = window.mockupsSeleccionados[i];
        document.getElementById('auto-progress-text').textContent = `Procesando ${i+1}/${total}...`;
        document.getElementById('auto-progress-bar').style.width = `${((i+1)/total)*100}%`;
        try {
            const r = await fetch('../api/linkedin_auto.php', { 
                method: 'POST', 
                body: JSON.stringify({ accion: 'generate_one', mockup_id: m.id, contexto: context, tono: tone, index: i }) 
            });
            const d = await r.json();
            if (d.ok) { apiCalls++; document.getElementById('api-call-count').textContent = apiCalls; }
        } catch(e) { console.error(e); }
        await new Promise(resolve => setTimeout(resolve, 800));
    }
    document.getElementById('spinner-auto').style.display = 'none';
    btn.disabled = false;
    mostrarResultado(`✅ ${apiCalls} posts programados.`, 'ok');
}

window.guardarPost = async function(estado) {
    const data = { 
        tipo: document.getElementById('li_tipo').value, 
        sku_ref: document.getElementById('li_sku').value, 
        texto: document.getElementById('li_texto').value, 
        imagen_url: document.getElementById('li_imagen_url').value, 
        enlace: document.getElementById('li_enlace').value, 
        fecha_programada: document.getElementById('li_fecha').value, 
        estado: estado, 
        ia: (modoActual === 'ia' ? 1 : 0) 
    };
    if (!data.texto) return mostrarResultado('⚠️ Escribe el texto', 'error');
    try {
        const r = await fetch('../api/linkedin_publish.php?accion=save', { method: 'POST', body: JSON.stringify(data) });
        const d = await r.json();
        if (d.ok) { mostrarResultado('✅ Guardado', 'ok'); resetForm(); }
    } catch(e) { console.error(e); }
}

window.publicarAhora = async function() {
    const texto = document.getElementById('li_texto').value;
    if (!texto) return mostrarResultado('⚠️ Escribe el texto', 'error');
    if (!confirm('¿Publicar ahora?')) return;
    mostrarResultado('🚀 Publicando...', 'info');
    const data = { 
        tipo: document.getElementById('li_tipo').value, 
        sku_ref: document.getElementById('li_sku').value, 
        texto: texto, 
        imagen_url: document.getElementById('li_imagen_url').value, 
        enlace: document.getElementById('li_enlace').value, 
        ia: (modoActual === 'ia' ? 1 : 0) 
    };
    try {
        const r = await fetch('../api/linkedin_publish.php?accion=publish_now', { method: 'POST', body: JSON.stringify(data) });
        const d = await r.json();
        if (d.ok) { mostrarResultado('✅ ¡Publicado!', 'ok'); resetForm(); }
        else mostrarResultado('❌ ' + d.error, 'error');
    } catch(e) { console.error(e); }
}

window.resetForm = function() {
    document.getElementById('li_texto').value = '';
    document.getElementById('li_imagen_url').value = '';
    document.getElementById('li_enlace').value = '';
    const img = document.getElementById('img-preview');
    if(img) img.style.display = 'none';
    actualizarContador();
}

window.cargarCola = async function(pag = 1) {
    paginaActual = pag;
    const estadoEl = document.getElementById('filtro-estado');
    const busqEl = document.getElementById('busq-cola');
    const estado = estadoEl ? estadoEl.value : 'todos';
    const busq = busqEl ? busqEl.value : '';
    
    try {
        const r = await fetch(`../api/linkedin_publish.php?accion=list&pag=${pag}&estado=${estado}&busq=${busq}`);
        const d = await r.json();
        const tbody = document.getElementById('tbody-cola');
        if(!tbody) return;
        tbody.innerHTML = '';
        if (!d.items || d.items.length === 0) { 
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; opacity:0.5;">Vacío</td></tr>'; 
            return; 
        }
        d.items.forEach(i => {
            const tr = document.createElement('tr');
            const imgUrl = i.imagen_url ? resolverRutaJS(i.imagen_url) : '';
            tr.innerHTML = `
                <td><span class="badge-type type-${i.tipo}">${i.tipo}</span></td>
                <td title="${i.texto}">${i.texto.substring(0, 50)}...</td>
                <td>${i.imagen_url ? `<img src="${imgUrl}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">` : '—'}</td>
                <td>${i.fecha_programada || '—'}</td>
                <td><span class="badge-status badge-${getEstadoColor(i.estado)}">${i.estado}</span></td>
                <td>${i.generado_por_ia == 1 ? '🤖' : '👤'}</td>
                <td>
                    <div style="display:flex; gap:5px;">
                        <button onclick="editarPost(${i.id})" class="btn-premium-wow" title="Editar" style="padding:4px 8px;"><i class="fas fa-edit"></i></button>
                        <button onclick="regenerarIA(${i.id})" class="btn-premium-wow" title="Regenerar con IA" style="padding:4px 8px; background:var(--accent-gold); color:#000;"><i class="fas fa-magic"></i></button>
                        <button onclick="borrarPost(${i.id})" class="btn-premium-wow" title="Borrar" style="padding:4px 8px; background:#ef4444;"><i class="fas fa-trash"></i></button>
                        ${i.estado !== 'publicado' ? `<button onclick="publicarIndividual(${i.id})" class="btn-premium-wow" title="Publicar ya" style="padding:4px 8px; background:var(--linkedin-blue);"><i class="fas fa-play"></i></button>` : ''}
                    </div>
                </td>`;
            tbody.appendChild(tr);
        });
        const pagEl = document.getElementById('paginacion-cola');
        if(pagEl) {
            pagEl.innerHTML = '';
            for (let p = 1; p <= d.total_paginas; p++) {
                const b = document.createElement('button');
                b.textContent = p;
                b.className = 'btn-premium-wow' + (p === pag ? ' btn-gold' : '');
                b.onclick = () => cargarCola(p);
                pagEl.appendChild(b);
            }
        }
    } catch(e) { console.error(e); }
}

window.regenerarIA = async function(id) {
    if (!id) return mostrarResultado('⚠️ No se encontró el ID del post', 'error');
    if (!confirm('¿Regenerar descripción con las nuevas pautas de IA?')) return;
    
    mostrarResultado('🪄 Magia en proceso...', 'info');
    try {
        const r = await fetch(`../api/linkedin_publish.php?accion=regenerate_ia&id=${id}`);
        const text = await r.text(); // Leer como texto primero para depurar si no es JSON
        
        let d;
        try {
            d = JSON.parse(text);
        } catch(e) {
            console.error("Respuesta no es JSON:", text);
            mostrarResultado('❌ Error en la respuesta del servidor. Revisa la consola.', 'error');
            return;
        }

        if (d.ok) {
            mostrarResultado('✅ Texto regenerado', 'ok');
            if (document.getElementById('modal-edit').style.display === 'flex') {
                document.getElementById('edit-texto').value = d.texto;
            }
            cargarCola(paginaActual);
        } else {
            mostrarResultado('❌ ' + (d.error || 'Error desconocido'), 'error');
        }
    } catch(e) { 
        console.error(e); 
        mostrarResultado('❌ Error de conexión: ' + e.message, 'error');
    }
}

window.cargarStats = async function() {
    try {
        const r = await fetch('../api/linkedin_publish.php?accion=stats');
        const d = await r.json();
        if (d.ok) {
            const s = d.stats;
            const el = document.getElementById('stats-cards');
            if(el) {
                el.innerHTML = `
                    <div class="stat-card"><div class="stat-num">${s.total || 0}</div><div class="stat-lbl">Total</div></div>
                    <div class="stat-card"><div class="stat-num">${s.publicado || 0}</div><div class="stat-lbl">Hechos</div></div>
                    <div class="stat-card"><div class="stat-num">${s.pendiente || 0}</div><div class="stat-lbl">Espera</div></div>
                    <div class="stat-card"><div class="stat-num">${s.error || 0}</div><div class="stat-lbl">Fallo</div></div>`;
            }
        }
    } catch(e) { console.error(e); }
}

window.publicarPendientes = async function() {
    if (!confirm('¿Publicar hoy?')) return;
    mostrarResultado('🚀 Iniciando...', 'info');
    try {
        const r = await fetch('../api/linkedin_publish.php?accion=publish_batch');
        const d = await r.json();
        if (d.ok) {
            mostrarResultado(`✅ Hecho. Publicados: ${d.publicados}`, 'ok');
            cargarCola(paginaActual);
        }
    } catch(e) { console.error(e); }
}

console.log("LinkedIn External Script loaded and attached to window.");
