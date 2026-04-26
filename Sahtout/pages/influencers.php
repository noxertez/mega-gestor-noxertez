<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
$page_class = 'management-page';
require_once '../api/config.php';
$db = conectar();

$influencers = $db->query("SELECT * FROM influencers ORDER BY nombre ASC")->fetchAll();
$articulos = $db->query("SELECT referencia, nombre FROM articulos WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();

include('../includes/header.php');
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.6">

<div class="panel-management">
    <div class="panel-header-wow">
        <h1><i class="fab fa-instagram"></i> Influencers & Marketing</h1>
        <button class="btn-premium-wow btn-gold" onclick="abrirModalNuevo()">
            <i class="fas fa-plus"></i> Añadir Influencer
        </button>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 2rem;">
        <?php foreach($influencers as $inf): ?>
            <div class="card-glass-wow" style="padding: 20px; border-radius: 15px; position: relative;">
                <div style="position: absolute; top: 15px; right: 15px; display: flex; gap: 8px;">
                    <button onclick="editarInfluencer(<?= htmlspecialchars(json_encode($inf)) ?>)" class="btn-icon-wow" style="color: var(--accent-gold);"><i class="fas fa-edit"></i></button>
                    <button onclick="borrarInfluencer(<?= $inf['id'] ?>)" class="btn-icon-wow" style="color: #ef4444;"><i class="fas fa-trash"></i></button>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <h3 style="color: var(--accent-gold); margin: 0; padding-right: 60px;"><?= htmlspecialchars($inf['nombre']) ?></h3>
                    <small style="opacity: 0.6;"><i class="fab fa-<?= strtolower($inf['red_social']) ?>"></i> <?= htmlspecialchars($inf['red_social']) ?></small>
                </div>
                
                <div style="font-size: 0.9rem; margin-bottom: 15px;">
                    <div style="margin-bottom: 5px;"><i class="fas fa-users" style="width: 20px; color: var(--accent-gold);"></i> <?= number_format($inf['seguidores']) ?> seg.</div>
                    <div style="margin-bottom: 5px;"><i class="fas fa-at" style="width: 20px;"></i> <?= htmlspecialchars($inf['usuario_ig'] ?: 'No definido') ?></div>
                    <div><i class="fas fa-envelope" style="width: 20px;"></i> <?= htmlspecialchars($inf['email'] ?: 'Sin email') ?></div>
                </div>
                
                <button class="btn-premium-wow btn-blue" onclick="verInfluencer(<?= $inf['id'] ?>)" style="width: 100%; justify-content: center;">
                    <i class="fas fa-box-open"></i> GESTIONAR PROMOS
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Influencer -->
<div id="modalInfluencer" class="modal-overlay-wow" style="display: none;" onclick="if(event.target==this) this.style.display='none'">
    <div class="modal-content-wow" style="max-width: 500px;">
        <div class="modal-header-wow">
            <h2 id="modal_title">Datos Influencer</h2>
            <button onclick="document.getElementById('modalInfluencer').style.display='none'" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <form id="formInfluencer" style="padding: 20px;">
            <input type="hidden" name="id" id="inf_id">
            <div class="nox-form-group">
                <label>Nombre Artístico</label>
                <input type="text" name="nombre" id="inf_nombre" class="input-wow" required style="width: 100%;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="nox-form-group">
                    <label>Red Social</label>
                    <select name="red_social" id="inf_red" class="input-wow" style="width: 100%;">
                        <option value="Instagram">Instagram</option>
                        <option value="TikTok">TikTok</option>
                        <option value="YouTube">YouTube</option>
                    </select>
                </div>
                <div class="nox-form-group">
                    <label>Seguidores</label>
                    <input type="number" name="seguidores" id="inf_seguidores" class="input-wow" style="width: 100%;">
                </div>
            </div>
            <div class="nox-form-group">
                <label>Usuario (@...)</label>
                <input type="text" name="usuario_ig" id="inf_usuario" class="input-wow" style="width: 100%;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="nox-form-group">
                    <label>Email</label>
                    <input type="text" name="email" id="inf_email" class="input-wow" style="width: 100%;">
                </div>
                <div class="nox-form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="inf_telefono" class="input-wow" style="width: 100%;">
                </div>
            </div>
            <button type="button" onclick="guardarInfluencer()" class="btn-premium-wow btn-gold" style="width: 100%; margin-top: 20px; justify-content:center;">💾 GUARDAR DATOS</button>
        </form>
    </div>
</div>

<!-- Modal Detalles y Promo -->
<div id="modalDetalle" class="modal-overlay-wow" style="display: none;" onclick="if(event.target==this) this.style.display='none'">
    <div class="modal-content-wow" style="max-width: 700px;">
        <div class="modal-header-wow">
            <h2 id="det_nombre" style="color: var(--accent-gold);"></h2>
            <button onclick="document.getElementById('modalDetalle').style.display='none'" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <div class="nav-tabs-wow" style="display:flex; gap:10px; border-bottom:1px solid var(--border-glass); margin-bottom: 20px;">
                <div class="tab-link-wow active" onclick="switchDetTab('det-historial', this)">Historial de Promos</div>
                <div class="tab-link-wow" onclick="switchDetTab('det-envio', this)">+ Nuevo Envío</div>
            </div>

            <div id="det-historial" class="det-tab active">
                <div id="lista_colabs" style="max-height: 400px; overflow-y: auto;"></div>
            </div>

            <div id="det-envio" class="det-tab" style="display: none;">
                <div class="nox-form-group">
                    <label>Artículo para Promo (Stock actual)</label>
                    <select id="sel_art" class="input-wow" style="width:100%;">
                        <?php foreach($articulos as $a): ?>
                            <option value="<?= $a['referencia'] ?>"><?= htmlspecialchars($a['nombre']) ?> (<?= $a['referencia'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="nox-form-group" style="margin-top: 15px;">
                    <label>Notas de Colaboración / Objetivo</label>
                    <textarea id="envio_notas" class="input-wow" style="width:100%; height:80px;" placeholder="Ej: Review de kit de tintado en Reels..."></textarea>
                </div>
                <button onclick="crearColaboracion()" class="btn-premium-wow btn-gold" style="width:100%; margin-top: 15px; justify-content:center;">🚀 LANZAR PEDIDO PROMO</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentInfId = null;

function abrirModalNuevo() {
    document.getElementById('formInfluencer').reset();
    document.getElementById('inf_id').value = '';
    document.getElementById('modal_title').innerText = 'Nuevo Influencer';
    document.getElementById('modalInfluencer').style.display = 'flex';
}

function editarInfluencer(inf) {
    document.getElementById('inf_id').value = inf.id;
    document.getElementById('inf_nombre').value = inf.nombre;
    document.getElementById('inf_red').value = inf.red_social;
    document.getElementById('inf_seguidores').value = inf.seguidores;
    document.getElementById('inf_usuario').value = inf.usuario_ig;
    document.getElementById('inf_email').value = inf.email;
    document.getElementById('inf_telefono').value = inf.telefono;
    document.getElementById('modal_title').innerText = 'Editar Influencer';
    document.getElementById('modalInfluencer').style.display = 'flex';
}

async function borrarInfluencer(id) {
    if(confirm('¿Seguro que quieres borrar este influencer y todos sus pedidos promocionales?')) {
        await fetch(`api/index.php?ruta=influencers&id=${id}`, { method: 'DELETE' });
        location.reload();
    }
}

async function guardarInfluencer() {
    const data = {
        id: document.getElementById('inf_id').value,
        nombre: document.getElementById('inf_nombre').value,
        red_social: document.getElementById('inf_red').value,
        seguidores: document.getElementById('inf_seguidores').value,
        usuario_ig: document.getElementById('inf_usuario').value,
        email: document.getElementById('inf_email').value,
        telefono: document.getElementById('inf_telefono').value,
        activo: 1
    };
    await fetch('api/index.php?ruta=influencers', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    });
    location.reload();
}

async function verInfluencer(id) {
    currentInfId = id;
    const res = await fetch(`api/index.php?ruta=influencers&id=${id}`);
    const data = await res.json();
    document.getElementById('det_nombre').innerText = data.influencer.nombre;
    const lista = document.getElementById('lista_colabs');
    
    if(data.colaboraciones.length === 0) {
        lista.innerHTML = '<div style="text-align:center; opacity:0.5; padding:40px;"><i class="fas fa-box-open" style="font-size:2rem; display:block; margin-bottom:10px;"></i>Sin pedidos realizados.</div>';
    } else {
        let html = '<table class="table-wow" style="font-size:0.85rem;"><thead><tr><th>Fecha</th><th>Artículo</th><th>Estado Real</th><th>Acción</th></tr></thead><tbody>';
        data.colaboraciones.forEach(c => {
            const estado = c.estado_pedido || 'pendiente';
            const color = estado === 'pendiente' ? 'btn-gold' : (estado === 'Listo para entregar' ? 'btn-green' : 'btn-blue');
            html += `<tr>
                <td>${c.fecha_envio.split(' ')[0]}</td>
                <td><strong>${c.sku_articulo}</strong></td>
                <td><span class="badge-wow ${color}">${estado.toUpperCase()}</span></td>
                <td>
                    <button onclick="editarColab(${JSON.stringify(c).replace(/"/g, '&quot;')})" class="btn-icon-wow" style="color:var(--accent-gold);"><i class="fas fa-edit"></i></button>
                    <button onclick="borrarColab(${c.id})" class="btn-icon-wow" style="color:#ef4444;"><i class="fas fa-times-circle"></i></button>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        lista.innerHTML = html;
    }
    document.getElementById('modalDetalle').style.display = 'flex';
}

function editarColab(colab) {
    document.getElementById('sel_art').value = colab.sku_articulo;
    document.getElementById('envio_notas').value = colab.notas;
    const btn = document.querySelector('#det-envio button');
    btn.innerText = '💾 ACTUALIZAR ENVÍO';
    btn.onclick = async () => {
        await fetch('api/index.php?ruta=influencers&accion=edit_colab', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                colab_id: colab.id,
                sku_articulo: document.getElementById('sel_art').value,
                notas: document.getElementById('envio_notas').value
            })
        });
        verInfluencer(currentInfId);
        switchDetTab('det-historial', document.querySelector('.tab-link-wow'));
        btn.innerText = '🚀 LANZAR PEDIDO PROMO';
        btn.onclick = crearColaboracion;
    };
    switchDetTab('det-envio', document.querySelectorAll('.tab-link-wow')[1]);
}

async function borrarColab(id) {
    if(confirm('¿Cancelar este envío y borrar el pedido del taller?')) {
        await fetch(`api/index.php?ruta=influencers&accion=delete_colab&id=${id}`, { method: 'POST' });
        verInfluencer(currentInfId);
    }
}

function switchDetTab(tabId, el) {
    document.querySelectorAll('.det-tab').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-link-wow').forEach(l => l.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    el.classList.add('active');
}

async function crearColaboracion() {
    await fetch('api/index.php?ruta=influencers&accion=add_colab', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            influencer_id: currentInfId,
            sku_articulo: document.getElementById('sel_art').value,
            notas: document.getElementById('envio_notas').value
        })
    });
    verInfluencer(currentInfId);
    switchDetTab('det-historial', document.querySelector('.tab-link-wow'));
}
</script>

<?php include('../includes/footer.php'); ?>
