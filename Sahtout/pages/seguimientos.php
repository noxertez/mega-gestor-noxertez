<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
$page_class = 'management-page';
require_once('../includes/header.php');

// Database connection
$db = new PDO('mysql:host=localhost;dbname=noxertez;charset=utf8mb4', 'noxertez_user', 'Noxertez2024!');
$seguimientos = $db->query(
    'SELECT * FROM seguimientos_pendientes WHERE enviado = 0 ORDER BY fecha_creacion DESC'
)->fetchAll();
?>

<!-- Estilos Específicos -->
<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=1.3">
<style>
    .panel-seguimientos {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        background: rgba(255, 255, 255, 0.05);
        padding: 1.5rem 2rem;
        border-radius: 15px;
        border: 1px solid rgba(212, 175, 55, 0.3);
        backdrop-filter: blur(10px);
    }
    .panel-header h1 {
        margin: 0;
        color: var(--accent-gold);
        font-family: 'Cinzel', serif;
        font-size: 1.8rem;
    }
    .tabla-container {
        background: rgba(10, 10, 15, 0.8);
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        overflow-x: auto; /* Scroll inferior */
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    
    /* Scroll superior */
    #scroll-top-container {
        height: 18px;
        overflow-x: auto;
        overflow-y: hidden;
        width: 100%;
        margin-bottom: 5px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
    }
    #scroll-top-content {
        height: 18px;
    }
    
    /* Personalización de scroll */
    .tabla-container::-webkit-scrollbar,
    #scroll-top-container::-webkit-scrollbar {
        height: 10px;
    }
    .tabla-container::-webkit-scrollbar-track,
    #scroll-top-container::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 10px;
    }
    .tabla-container::-webkit-scrollbar-thumb,
    #scroll-top-container::-webkit-scrollbar-thumb {
        background: rgba(212, 175, 55, 0.3);
        border-radius: 10px;
    }
    .tabla-container::-webkit-scrollbar-thumb:hover,
    #scroll-top-container::-webkit-scrollbar-thumb:hover {
        background: rgba(212, 175, 55, 0.5);
    }

    .tabla-wow {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
        color: #ddd;
    }
    .tabla-wow th {
        background: rgba(212, 175, 55, 0.1);
        color: var(--accent-gold);
        text-align: left;
        padding: 1.2rem;
        font-family: 'Cinzel', serif;
        font-size: 0.9rem;
        border-bottom: 2px solid rgba(212, 175, 55, 0.3);
        white-space: nowrap;
    }
    .tabla-wow td {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-size: 0.9rem;
    }
    .tabla-wow tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }
    .btn-action {
        padding: 8px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        white-space: nowrap;
    }
    .btn-copiar {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .btn-copiar:hover { background: rgba(59, 130, 246, 0.4); transform: translateY(-2px); }
    
    .btn-editar {
        background: rgba(212, 175, 55, 0.15);
        color: var(--accent-gold);
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .btn-editar:hover { background: rgba(212, 175, 55, 0.3); transform: translateY(-2px); }

    .btn-enviado {
        background: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.3);
    }
    .btn-enviado:hover { background: rgba(34, 197, 94, 0.4); transform: translateY(-2px); }

    .badge-pedido {
        background: rgba(212, 175, 55, 0.15);
        color: var(--accent-gold);
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-family: monospace;
    }
    .texto-mensaje-cell {
        max-width: 350px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #bbb;
        cursor: help;
    }

    /* Modal de Edición */
    .nox-modal {
        display: none;
        position: fixed;
        z-index: 10002;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
        backdrop-filter: blur(5px);
    }
    .nox-modal-content {
        background: #111;
        margin: 10% auto;
        padding: 20px;
        border: 1px solid var(--accent-gold);
        width: 50%;
        max-width: 600px;
        border-radius: 12px;
        box-shadow: 0 0 30px rgba(212,175,55,0.2);
    }
    .nox-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(212,175,55,0.2);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .nox-modal-header h2 { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.2rem; margin:0; }
    .nox-modal-body textarea {
        width: 100%;
        height: 200px;
        background: #000;
        color: #fff;
        border: 1px solid #333;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        resize: vertical;
    }
    .nox-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
</style>

<div class="panel-seguimientos">
    <div class="panel-header">
        <h1><i class="fas fa-shipping-fast"></i> Seguimientos Postventa</h1>
        <div class="stats">
            <span style="color: #bbb;">Pendientes: <strong><?= count($seguimientos) ?></strong></span>
        </div>
    </div>

    <!-- Scroll superior -->
    <div id="scroll-top-container">
        <div id="scroll-top-content"></div>
    </div>

    <div class="tabla-container" id="tabla-container">
        <table class="tabla-wow" id="tabla-seguimientos">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Pedido</th>
                    <th>Mensaje</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($seguimientos)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 1rem;"></i>
                            No hay seguimientos pendientes.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($seguimientos as $s): ?>
                        <tr id="row-<?= $s['id'] ?>">
                            <td><strong><?= htmlspecialchars($s['nombre_cliente']) ?></strong></td>
                            <td>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['telefono']) ?>" target="_blank" style="color: #25D366; text-decoration: none;">
                                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($s['telefono']) ?>
                                </a>
                            </td>
                            <td><span class="badge-pedido"><?= htmlspecialchars($s['numero_pedido']) ?></span></td>
                            <td>
                                <div class="texto-mensaje-cell" id="txt-<?= $s['id'] ?>" title="<?= htmlspecialchars($s['texto_mensaje']) ?>">
                                    <?= htmlspecialchars($s['texto_mensaje']) ?>
                                </div>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($s['fecha_creacion'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="copiarMensaje('<?= addslashes($s['texto_mensaje']) ?>')" class="btn-action btn-copiar" title="Copiar al portapapeles">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button onclick="abrirEditor(<?= $s['id'] ?>)" class="btn-action btn-editar" title="Editar mensaje">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button onclick="marcarEnviado(<?= $s['id'] ?>)" class="btn-action btn-enviado">
                                        <i class="fas fa-check"></i> Enviado
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para editar mensaje -->
<div id="modalEditor" class="nox-modal">
    <div class="nox-modal-content">
        <div class="nox-modal-header">
            <h2>Editar Mensaje de Seguimiento</h2>
            <span style="cursor:pointer; color:#888;" onclick="cerrarEditor()">✕</span>
        </div>
        <div class="nox-modal-body">
            <input type="hidden" id="edit-id">
            <textarea id="edit-mensaje"></textarea>
        </div>
        <div class="nox-modal-footer">
            <button class="btn-action" style="background:#333; color:#ccc;" onclick="cerrarEditor()">Cancelar</button>
            <button class="btn-action btn-enviado" onclick="guardarEdicion()">Guardar Cambios</button>
        </div>
    </div>
</div>

<script>
let currentEditId = null;

// Sincronización de scrolls duales
document.addEventListener('DOMContentLoaded', function() {
    const topScroll = document.getElementById('scroll-top-container');
    const bottomScroll = document.getElementById('tabla-container');
    const topContent = document.getElementById('scroll-top-content');
    const table = document.getElementById('tabla-seguimientos');

    function updateTopScrollWidth() {
        if(!table) return;
        topContent.style.width = table.offsetWidth + 'px';
        if (table.offsetWidth <= bottomScroll.offsetWidth) {
            topScroll.style.display = 'none';
        } else {
            topScroll.style.display = 'block';
        }
    }

    updateTopScrollWidth();
    window.addEventListener('resize', updateTopScrollWidth);

    topScroll.onscroll = function() { bottomScroll.scrollLeft = topScroll.scrollLeft; };
    bottomScroll.onscroll = function() { topScroll.scrollLeft = bottomScroll.scrollLeft; };
});

function abrirEditor(id) {
    currentEditId = id;
    const txtDiv = document.getElementById('txt-' + id);
    const textoActual = txtDiv.getAttribute('title'); // Usamos el title que tiene el texto completo
    
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-mensaje').value = textoActual;
    document.getElementById('modalEditor').style.display = 'block';
}

function cerrarEditor() {
    document.getElementById('modalEditor').style.display = 'none';
}

function guardarEdicion() {
    const id = document.getElementById('edit-id').value;
    const mensaje = document.getElementById('edit-mensaje').value;

    const fd = new FormData();
    fd.append('accion', 'editar_mensaje');
    fd.append('id', id);
    fd.append('mensaje', mensaje);

    fetch('<?= $base_path ?>api/seguimientos.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if(d.ok) {
            // Actualizar la celda visualmente
            const txtDiv = document.getElementById('txt-' + id);
            txtDiv.textContent = mensaje;
            txtDiv.setAttribute('title', mensaje);
            cerrarEditor();
            
            // Actualizar el botón de copiar (si es inline en el PHP, habría que recargar o manejar el string)
            // Para simplicidad, se recomienda recargar o actualizar el onclick del botón copiar de esa fila
            const row = document.getElementById('row-' + id);
            const btnCopiar = row.querySelector('.btn-copiar');
            btnCopiar.setAttribute('onclick', `copiarMensaje('${mensaje.replace(/'/g, "\\'")}')`);
            
            alert('¡Mensaje actualizado!');
        } else {
            alert('Error al guardar: ' + d.error);
        }
    })
    .catch(err => alert('Error de conexión'));
}

function copiarMensaje(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        btn.style.borderColor = '#4ade80';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.borderColor = '';
        }, 2000);
    });
}

function marcarEnviado(id) {
    if(!confirm('¿Confirmas que has enviado este mensaje de seguimiento?')) return;

    const fd = new FormData();
    fd.append('accion', 'marcar_enviado');
    fd.append('id', id);

    fetch('<?= $base_path ?>api/seguimientos.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if(d.ok) {
            const row = document.getElementById('row-' + id);
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            row.style.transition = 'all 0.5s ease';
            setTimeout(() => {
                row.remove();
                const table = document.getElementById('tabla-seguimientos');
                const topContent = document.getElementById('scroll-top-content');
                if(table) topContent.style.width = table.offsetWidth + 'px';
            }, 500);
        } else {
            alert('Error: ' + (d.error || 'No se pudo actualizar'));
        }
    })
    .catch(err => alert('Error de conexión'));
}
</script>

<?php require_once('../includes/footer.php'); ?>
