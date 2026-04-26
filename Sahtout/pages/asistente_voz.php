<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../api/config.php';
$db = conectar();

// 1. Fetch Tareas (Notas Pendientes)
$tareas_pendientes = $db->query("SELECT * FROM tareas WHERE completada = 0 ORDER BY fecha_creacion DESC")->fetchAll();

// 2. Fetch Historial (Notas Completadas)
$historial_notas = $db->query("SELECT * FROM tareas WHERE completada = 1 ORDER BY fecha_creacion DESC LIMIT 15")->fetchAll();

// 3. Fetch Pedidos (Pendientes)
$pedidos = $db->query("SELECT * FROM pedidos WHERE estado != 'Entregado' AND estado != 'Cancelado' ORDER BY fecha_pedido DESC LIMIT 10")->fetchAll();

// 4. Inventario General
$stock_total = $db->query("SELECT * FROM articulos WHERE activo = 1 ORDER BY nombre ASC LIMIT 20")->fetchAll();

// 5. Resumen (Stats)
$total_v_hoy = $db->query("SELECT SUM(importe) as t FROM ventas WHERE DATE(fecha) = CURDATE()")->fetch();
$total_v_mes = $db->query("SELECT SUM(importe) as t FROM ventas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())")->fetch();

$page_class = "management-page";
include('../includes/header.php');

$current_tab = $_GET['tab'] ?? 'bloc';
?>

<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/management_style.css?v=1.6">

<style>
    .tab-container { display: none; }
    .tab-container.active { display: block; animation: fadeIn 0.3s; }

    .nav-tabs-wow { 
        display: flex; 
        gap: 8px; 
        margin-bottom: 2rem; 
        border-bottom: 1px solid var(--border-glass); 
        padding-bottom: 10px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .tab-link-wow { 
        padding: 8px 16px; 
        color: var(--text-gray); 
        cursor: pointer; 
        border-radius: 8px; 
        transition: all 0.3s; 
        font-weight: bold;
        white-space: nowrap;
        font-size: 0.85rem;
    }
    .tab-link-wow.active { background: var(--accent-gold); color: #000; }
    .tab-link-wow:hover:not(.active) { background: rgba(255,255,255,0.05); color: var(--text-white); }

    .voice-control-panel {
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-glass);
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        margin-bottom: 2rem;
    }

    .mic-btn-premium {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #ef4444, #991b1b);
        border: 3px solid var(--accent-gold);
        border-radius: 50%;
        color: white;
        font-size: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .mic-btn-premium.active { background: #10b981; box-shadow: 0 0 35px rgba(16, 185, 129, 0.5); transform: scale(1.1); }

    .data-card-wow {
        background: rgba(255,255,255,0.02);
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* ESTILO BLOC DE NOTAS MEJORADO */
    .notepad-container {
        background: #fffbe6;
        color: #333;
        padding: 40px;
        border-radius: 4px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        position: relative;
        min-height: 500px;
        font-family: 'Handlee', cursive;
        border-left: 40px solid #ff6b6b;
    }
    .notepad-container::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: linear-gradient(#e1e1e1 1px, transparent 1px);
        background-size: 100% 28px;
        pointer-events: none;
    }
    .note-item {
        position: relative;
        z-index: 1;
        margin-bottom: 28px;
        line-height: 28px;
        font-size: 1.2rem;
        border-bottom: 1px dashed rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        cursor: pointer;
        transition: background 0.2s;
    }
    .note-item:hover { background: rgba(0,0,0,0.03); }
    .note-item.completed { opacity: 0.5; text-decoration: line-through; color: #777; }
    
    .history-divider {
        border-top: 2px dashed #ff6b6b;
        margin: 40px 0 20px 0;
        position: relative;
        z-index: 1;
        text-align: center;
    }
    .history-divider span {
        background: #ff6b6b;
        color: white;
        font-family: sans-serif;
        font-size: 0.7rem;
        padding: 2px 10px;
        border-radius: 10px;
        position: relative;
        top: -12px;
    }

    /* MODAL ESTILO PREMIUM */
    .modal-note-wow {
        position: fixed;
        top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(8px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    .modal-note-content {
        background: var(--bg-modal);
        width: 90%;
        max-width: 500px;
        border-radius: 20px;
        border: 2px solid var(--accent-gold);
        padding: 25px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    .modal-note-textarea {
        width: 100%;
        background: rgba(0,0,0,0.2);
        border: 1px solid var(--border-glass);
        color: white;
        padding: 15px;
        border-radius: 12px;
        font-family: inherit;
        font-size: 1.1rem;
        margin-bottom: 20px;
        resize: none;
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=Handlee&display=swap" rel="stylesheet">

<div class="panel-management">
    
    <div class="voice-control-panel">
        <button id="mic-btn" class="mic-btn-premium" onclick="toggleVoice()">
            <i class="fas fa-microphone"></i>
        </button>
        <div style="margin-top: 15px;">
            <div id="status" style="font-size: 0.7rem; opacity: 0.5; text-transform: uppercase;">Asistente Inteligente</div>
            <div id="transcript" style="font-size: 0.9rem; opacity: 0.7; height: 1.5rem; margin-top: 5px;"></div>
            <div id="response" style="font-size: 1.2rem; color: var(--accent-gold); font-weight: bold;">¿En qué puedo ayudarte hoy?</div>
        </div>
    </div>

    <div class="nav-tabs-wow">
        <div class="tab-link-wow <?php echo ($current_tab == 'bloc' || !isset($_GET['tab'])) ? 'active' : ''; ?>" onclick="switchTab('tab-bloc', this, 'bloc')">Bloc de Notas 📝</div>
        <div class="tab-link-wow <?php echo ($current_tab == 'pedidos') ? 'active' : ''; ?>" onclick="switchTab('tab-pedidos', this, 'pedidos')">Pedidos</div>
        <div class="tab-link-wow <?php echo ($current_tab == 'stock') ? 'active' : ''; ?>" onclick="switchTab('tab-stock', this, 'stock')">Stock</div>
        <div class="tab-link-wow <?php echo ($current_tab == 'resumen') ? 'active' : ''; ?>" onclick="switchTab('tab-resumen', this, 'resumen')">Resumen</div>
        <div class="tab-link-wow <?php echo ($current_tab == 'comandos') ? 'active' : ''; ?>" onclick="switchTab('tab-comandos', this, 'comandos')">Comandos</div>
        <div class="tab-link-wow <?php echo ($current_tab == 'voz') ? 'active' : ''; ?>" onclick="switchTab('tab-instrucciones', this, 'voz')">Información 🤖</div>
    </div>

    <div class="tab-content-wow">
        
        <div id="tab-bloc" class="tab-container <?php echo ($current_tab == 'bloc' || !isset($_GET['tab'])) ? 'active' : ''; ?>">
            <div class="notepad-container">
                <h2 style="margin-top:0; color:#ff6b6b; font-family:sans-serif; text-transform:uppercase; font-size:1rem; border-bottom:2px solid #ff6b6b; padding-bottom:5px;">Notas de Voz Rápidas</h2>
                <div id="notas-pendientes">
                    <?php if (empty($tareas_pendientes)): ?>
                        <div class="note-item">No hay notas pendientes.</div>
                    <?php else: ?>
                        <?php foreach($tareas_pendientes as $t): ?>
                            <div class="note-item" onclick="openNoteModal(<?php echo $t['id']; ?>, '<?php echo addslashes($t['descripcion']); ?>', false)">
                                <span class="note-text"><?php echo htmlspecialchars($t['descripcion']); ?></span>
                                <span class="note-date"><?php echo date('d M', strtotime($t['fecha_creacion'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($historial_notas)): ?>
                    <div class="history-divider"><span>HISTORIAL</span></div>
                    <div id="notas-historial">
                        <?php foreach($historial_notas as $t): ?>
                            <div class="note-item completed" onclick="openNoteModal(<?php echo $t['id']; ?>, '<?php echo addslashes($t['descripcion']); ?>', true)">
                                <span class="note-text"><?php echo htmlspecialchars($t['descripcion']); ?></span>
                                <span class="note-date"><?php echo date('d M', strtotime($t['fecha_creacion'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="tab-pedidos" class="tab-container <?php echo ($current_tab == 'pedidos') ? 'active' : ''; ?>">
            <h3 style="color: var(--accent-gold); margin-bottom: 15px;">Próximas Entregas</h3>
            <?php foreach($pedidos as $p): ?>
                <div class="data-card-wow">
                    <div>
                        <div style="font-weight: bold;"><?php echo $p['numero_pedido']; ?> - <?php echo htmlspecialchars($p['nombre_cliente']); ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.5;"><?php echo $p['fecha_pedido']; ?></div>
                    </div>
                    <span style="font-size: 0.8rem; background: rgba(59,130,246,0.2); color: #60a5fa; padding: 2px 10px; border-radius: 10px;"><?php echo $p['estado']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="tab-stock" class="tab-container <?php echo ($current_tab == 'stock') ? 'active' : ''; ?>">
            <h3 style="color: var(--accent-gold); margin-bottom: 15px;">Stock Rápido</h3>
            <?php foreach($stock_total as $s): ?>
                <div class="data-card-wow">
                    <div><?php echo htmlspecialchars($s['nombre']); ?></div>
                    <div style="font-weight: bold; color: var(--accent-gold);"><?php echo $s['stock']; ?> uds</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="tab-resumen" class="tab-container <?php echo ($current_tab == 'resumen') ? 'active' : ''; ?>">
            <h3 style="color: var(--accent-gold); margin-bottom: 20px;">Estadísticas de Ventas</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="data-card-wow" style="flex-direction:column; align-items:flex-start; padding:20px;">
                    <span style="font-size: 0.8rem; opacity: 0.6;">HOY</span>
                    <div style="font-size: 1.8rem; color: #10b981; font-weight: bold;"><?php echo number_format($total_v_hoy['t'] ?? 0, 2); ?>€</div>
                </div>
                <div class="data-card-wow" style="flex-direction:column; align-items:flex-start; padding:20px;">
                    <span style="font-size: 0.8rem; opacity: 0.6;">MES ACTUAL</span>
                    <div style="font-size: 1.8rem; color: var(--accent-gold); font-weight: bold;"><?php echo number_format($total_v_mes['t'] ?? 0, 2); ?>€</div>
                </div>
            </div>
        </div>

        <div id="tab-instrucciones" class="tab-container <?php echo ($current_tab == 'voz') ? 'active' : ''; ?>">
            <div style="text-align:center; padding: 2rem; opacity: 0.6;">
                <i class="fas fa-robot" style="font-size: 3rem; margin-bottom: 1rem; color: var(--accent-gold);"></i>
                <h3>Sistema Operativo Noxertez</h3>
                <p>Gestiona tu taller con la voz y el bloc de notas interactivo.</p>
            </div>
        </div>

        <div id="tab-comandos" class="tab-container <?php echo ($current_tab == 'comandos') ? 'active' : ''; ?>">
            <h3 style="color: var(--accent-gold); margin-bottom: 15px;">Comandos Disponibles</h3>
            <div style="background:rgba(0,0,0,0.2); padding:10px; border-radius:12px;">
                <div style="padding:10px; border-bottom:1px solid var(--border-glass);">"Dime las ventas de hoy"</div>
                <div style="padding:10px; border-bottom:1px solid var(--border-glass);">"Anotar nota: [texto]"</div>
                <div style="padding:10px; border-bottom:1px solid var(--border-glass);">"¿Qué pedidos tengo pendientes?"</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE EDICIÓN DE NOTA -->
<div id="modal-note-edit" class="modal-note-wow">
    <div class="modal-note-content">
        <h3 id="modal-title" style="color:var(--accent-gold); margin-top:0;">Editar Nota</h3>
        <textarea id="modal-note-text" class="modal-note-textarea" rows="4"></textarea>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button id="btn-save-note" class="btn-premium-wow btn-gold">GUARDAR</button>
            <button id="btn-done-note" class="btn-premium-wow btn-green">MARCAR HECHO</button>
            <button id="btn-delete-note" class="btn-premium-wow btn-red">BORRAR</button>
            <button onclick="closeNoteModal()" class="btn-premium-wow" style="background:#444;">CANCELAR</button>
        </div>
    </div>
</div>

<script>
let currentEditingId = null;

function switchTab(paneId, el, tabName) {
    document.querySelectorAll('.tab-container').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-link-wow').forEach(l => l.classList.remove('active'));
    document.getElementById(paneId).classList.add('active');
    el.classList.add('active');
    if (tabName) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }
}

function openNoteModal(id, text, isHistory) {
    currentEditingId = id;
    document.getElementById('modal-note-text').value = text;
    document.getElementById('modal-note-edit').style.display = 'flex';
    document.getElementById('btn-done-note').style.display = isHistory ? 'none' : 'inline-flex';
    document.getElementById('modal-title').innerText = isHistory ? 'Historial de Nota' : 'Gestionar Nota';
}

function closeNoteModal() {
    document.getElementById('modal-note-edit').style.display = 'none';
}

document.getElementById('btn-save-note').onclick = async () => {
    const text = document.getElementById('modal-note-text').value;
    await fetch(`<?php echo $base_path; ?>api/index.php?ruta=tareas&id=${currentEditingId}`, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ descripcion: text })
    });
    location.reload();
};

document.getElementById('btn-done-note').onclick = async () => {
    await fetch(`<?php echo $base_path; ?>api/index.php?ruta=tareas&id=${currentEditingId}`, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ completada: 1 })
    });
    location.reload();
};

document.getElementById('btn-delete-note').onclick = async () => {
    if(confirm('¿Borrar permanentemente?')) {
        await fetch(`<?php echo $base_path; ?>api/index.php?ruta=tareas&accion=borrar&id=${currentEditingId}`, { method: 'POST' });
        location.reload();
    }
};

let recognition;
let isListening = false;
let silenceTimer;

if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.lang = 'es-ES';
    recognition.continuous = true; // Permite pausas largas sin detenerse
    recognition.interimResults = true; // Feedback en tiempo real

    recognition.onstart = () => { 
        isListening = true;
        document.getElementById('mic-btn').classList.add('active'); 
        document.getElementById('status').innerText = 'ESCUCHANDO... (Más tiempo activado)'; 
        resetSilenceTimer();
    };

    recognition.onresult = (event) => { 
        let interimTranscript = '';
        let finalTranscript = '';

        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                finalTranscript += event.results[i][0].transcript;
            } else {
                interimTranscript += event.results[i][0].transcript;
            }
        }

        const currentText = finalTranscript || interimTranscript;
        document.getElementById('transcript').innerText = currentText;
        
        if (finalTranscript || interimTranscript) {
            resetSilenceTimer(); // Reiniciamos el tiempo cada vez que detecta voz
        }
    };

    recognition.onend = () => { 
        isListening = false;
        document.getElementById('mic-btn').classList.remove('active'); 
        document.getElementById('status').innerText = 'ASISTENTE'; 
        clearTimeout(silenceTimer);
        
        const textoFinal = document.getElementById('transcript').innerText;
        if (textoFinal.trim().length > 2) {
            procesarVoz(textoFinal); 
        }
    };

    recognition.onerror = (event) => {
        console.error('Error de reconocimiento:', event.error);
        isListening = false;
        document.getElementById('mic-btn').classList.remove('active');
        document.getElementById('status').innerText = 'ERROR';
    };
}

function resetSilenceTimer() {
    clearTimeout(silenceTimer);
    // Damos 4 segundos de margen de silencio antes de procesar automáticamente
    silenceTimer = setTimeout(() => {
        if (isListening && recognition) {
            recognition.stop();
        }
    }, 4000); 
}

function toggleVoice() { 
    if (!recognition) return;
    if (isListening) {
        recognition.stop();
    } else {
        recognition.start(); 
    }
}

async function procesarVoz(texto) {
    if(!texto.trim()) return;
    document.getElementById('response').innerText = "Procesando...";
    
    try {
        const res = await fetch('<?php echo $base_path; ?>api/index.php?ruta=asistente', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ texto })
        });
        const data = await res.json();
        document.getElementById('response').innerText = data.respuesta;
        document.getElementById('transcript').innerText = ""; // Limpiar tras procesar
        if (data.accion === 'reload_tasks') {
            // Recargar solo la parte de las tareas sin F5 total para mejor UX
            setTimeout(() => location.reload(), 1500);
        }
    } catch (e) {
        document.getElementById('response').innerText = "Error en el servidor.";
    }
}
</script>

<?php include('../includes/footer.php'); ?>
