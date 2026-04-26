<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
include('../includes/header.php');

// Filtros
$prioridad = $_GET['prioridad'] ?? '';
$url = 'http://localhost/noxertez/api/index.php?ruta=tareas';
$tareas = json_decode(file_get_contents($url), true) ?: [];

// Separar completadas/pendientes para la UI
$pendientes = array_filter($tareas, fn($t) => ($t['completada'] ?? 0) == 0);
?>

<style>
.tareas-container { max-width: 900px; margin: 0 auto; padding: 20px; }
.panel-titulo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.panel-titulo h1 { color: #fff; font-size: 1.8rem; }
.filtros-prioridad { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.btn-filtro { padding: 6px 14px; border-radius: 20px; border: 2px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.05); color: #ccc; cursor: pointer; text-decoration: none;
    font-size: 0.85rem; transition: all 0.2s; }
.btn-filtro:hover, .btn-filtro.activo { background: #7c3aed; border-color: #7c3aed; color: #fff; }
.tarea-card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px; padding: 16px 20px; margin-bottom: 12px;
    display: flex; align-items: center; gap: 16px; transition: all 0.2s; }
.tarea-card:hover { background: rgba(255,255,255,0.1); transform: translateX(4px); }
.tarea-check { width: 22px; height: 22px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.3);
    cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; }
.tarea-check:hover { border-color: #22c55e; background: rgba(34,197,94,0.2); }
.tarea-info { flex: 1; }
.tarea-desc { color: #fff; font-size: 1rem; margin-bottom: 4px; }
.tarea-meta { font-size: 0.78rem; color: #888; }
.tarea-fecha { color: #f59e0b; font-size: 0.78rem; }
.prioridad-badge { display: inline-block; padding: 2px 10px; border-radius: 10px;
    font-size: 0.72rem; font-weight: 600; text-transform: uppercase; margin-left: 8px; }
.prioridad-alta { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid #f87171; }
.prioridad-media { background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid #fbbf24; }
.prioridad-baja { background: rgba(34,197,94,0.2); color: #4ade80; border: 1px solid #4ade80; }
.btn-wow { padding: 10px 22px; background: linear-gradient(135deg, #7c3aed, #4f46e5);
    color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem;
    font-weight: 600; transition: all 0.2s; }
.btn-wow:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124,58,237,0.4); }
.vacio-msg { text-align: center; color: #888; padding: 60px 20px; font-size: 1.1rem; }
.nueva-tarea-form { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px; padding: 20px; margin-bottom: 24px; display: none; }
.nueva-tarea-form.visible { display: block; }
.form-row { display: flex; gap: 12px; flex-wrap: wrap; }
.form-row input, .form-row select { flex: 1; min-width: 150px; padding: 10px 14px;
    background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px; color: #fff; font-size: 0.9rem; }
.form-row input::placeholder { color: #888; }
</style>

<div class="tareas-container">
    <div class="panel-titulo">
        <h1>📋 Lista de Tareas</h1>
        <button class="btn-wow" onclick="toggleFormTarea()">+ Nueva Tarea</button>
    </div>

    <!-- Formulario nueva tarea -->
    <div class="nueva-tarea-form" id="formNuevaTarea">
        <div class="form-row">
            <input type="text" id="nuevaDesc" placeholder="Descripción de la tarea..." style="flex:3">
            <select id="nuevaPrioridad">
                <option value="alta">🔴 Alta</option>
                <option value="media" selected>🟡 Media</option>
                <option value="baja">🟢 Baja</option>
            </select>
            <input type="date" id="nuevaFecha" placeholder="Fecha límite (opcional)">
            <button class="btn-wow" onclick="crearTarea()" style="flex-shrink:0">Guardar</button>
        </div>
    </div>

    <!-- Filtros de prioridad -->
    <div class="filtros-prioridad">
        <a href="?" class="btn-filtro <?= !$prioridad ? 'activo' : '' ?>">Todas</a>
        <a href="?prioridad=alta" class="btn-filtro <?= $prioridad==='alta' ? 'activo' : '' ?>">🔴 Alta</a>
        <a href="?prioridad=media" class="btn-filtro <?= $prioridad==='media' ? 'activo' : '' ?>">🟡 Media</a>
        <a href="?prioridad=baja" class="btn-filtro <?= $prioridad==='baja' ? 'activo' : '' ?>">🟢 Baja</a>
    </div>

    <!-- Lista de tareas -->
    <div id="listaTareas">
    <?php
if (empty($pendientes)): ?>
        <div class="vacio-msg">✅ No hay tareas pendientes. ¡Todo al día!</div>
    <?php
else: ?>
        <?php
foreach ($pendientes as $t): ?>
            <?php
if ($prioridad && $t['prioridad'] !== $prioridad) continue; ?>
            <div class="tarea-card" id="tarea-<?= $t['id'] ?>">
                <div class="tarea-check" onclick="completarTarea(<?= $t['id'] ?>)" title="Marcar como completada">✓</div>
                <div class="tarea-info">
                    <div class="tarea-desc">
                        <?= htmlspecialchars($t['descripcion']) ?>
                        <span class="prioridad-badge prioridad-<?= $t['prioridad'] ?>"><?= $t['prioridad'] ?></span>
                    </div>
                    <div class="tarea-meta">
                        Creada: <?= date('d/m/Y H:i', strtotime($t['fecha_creacion'])) ?>
                        <?php
if ($t['fecha_limite']): ?>
                            · <span class="tarea-fecha">📅 Límite: <?= date('d/m/Y', strtotime($t['fecha_limite'])) ?></span>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>
        <?php
endforeach; ?>
    <?php
endif; ?>
    </div>
</div>

<script>
function toggleFormTarea() {
    document.getElementById('formNuevaTarea').classList.toggle('visible');
    document.getElementById('nuevaDesc').focus();
}

async function crearTarea() {
    const desc = document.getElementById('nuevaDesc').value.trim();
    if (!desc) { alert('Escribe una descripción'); return; }
    const res = await fetch('/noxertez/api/index.php?ruta=tareas', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            descripcion: desc,
            prioridad: document.getElementById('nuevaPrioridad').value,
            fecha_limite: document.getElementById('nuevaFecha').value || null
        })
    });
    const data = await res.json();
    if (data.ok) { location.reload(); } else { alert('Error al guardar tarea'); }
}

async function completarTarea(id) {
    if (!confirm('¿Marcar esta tarea como completada?')) return;
    await fetch('/noxertez/api/index.php?ruta=tareas&id=' + id, { method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ completada: 1 }) });
    const card = document.getElementById('tarea-' + id);
    card.style.opacity = '0.4';
    card.style.textDecoration = 'line-through';
    setTimeout(() => card.remove(), 600);
}
</script>

<?php
include('../includes/footer.php'); ?>



