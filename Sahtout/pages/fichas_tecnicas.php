<?php
ob_start();
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config.php';
$db = conectar();

// Procesar Guardado de Ficha
if (isset($_POST['guardar_ficha'])) {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre_interno'];
    $titulo = $_POST['titulo_publico'];
    $materiales = $_POST['materiales'];
    $elaboracion = $_POST['elaboracion'];
    $observaciones = $_POST['observaciones'];
    $mantenimiento = $_POST['mantenimiento'];
    $sostenibilidad = $_POST['sostenibilidad'];

    if ($id) {
        $stmt = $db->prepare("UPDATE fichas_tecnicas SET nombre_interno=?, titulo_publico=?, materiales=?, elaboracion=?, observaciones=?, mantenimiento=?, sostenibilidad=? WHERE id=?");
        $stmt->execute([$nombre, $titulo, $materiales, $elaboracion, $observaciones, $mantenimiento, $sostenibilidad, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO fichas_tecnicas (nombre_interno, titulo_publico, materiales, elaboracion, observaciones, mantenimiento, sostenibilidad) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $titulo, $materiales, $elaboracion, $observaciones, $mantenimiento, $sostenibilidad]);
    }
    header("Location: fichas_tecnicas.php?success=1&tab=1");
    exit;
}

// Procesar Asociación (Vincular)
if (isset($_POST['asociar_ficha'])) {
    $sku = $_POST['sku'];
    $ficha_id = $_POST['ficha_id'];
    $stmt = $db->prepare("UPDATE articulos SET ficha_tecnica_id=? WHERE referencia=?");
    $stmt->execute([$ficha_id, $sku]);
    header("Location: fichas_tecnicas.php?success=2&tab=3");
    exit;
}

$page_class = 'management-page';
include(__DIR__ . '/../includes/header.php');

// Eliminar
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM fichas_tecnicas WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: fichas_tecnicas.php?deleted=1&tab=1");
    exit;
}

$fichas = $db->query("SELECT * FROM fichas_tecnicas ORDER BY id DESC")->fetchAll();
$current_tab = $_GET['tab'] ?? '1';

// Para la pestaña de asociación (Buscador y listado)
$articulos = $db->query("SELECT referencia, nombre, categoria, foto_portada, ficha_tecnica_id FROM articulos ORDER BY id DESC LIMIT 100")->fetchAll();
?>

<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/management_style.css">
<style>
    .tab-nav-nox { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 1px solid rgba(212, 175, 55, 0.2); padding-bottom: 10px; }
    .tab-link { color: #888; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    .tab-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
    .tab-link.active { color: var(--accent-gold); background: rgba(212, 175, 55, 0.1); border: 1px solid rgba(212, 175, 55, 0.3); }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease; }

    .card-ficha { background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass); border-radius: 15px; padding: 20px; transition: all 0.3s; }
    .card-ficha:hover { border-color: var(--accent-gold); transform: translateY(-5px); background: rgba(255,255,255,0.05); }
    .label-ficha { font-size: 0.75rem; color: var(--accent-gold); text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 5px; display: block; }
    
    .editor-container { background: rgba(10, 10, 10, 0.4); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); }
    
    .table-asociar { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .table-asociar tr { background: rgba(255,255,255,0.02); transition: 0.2s; }
    .table-asociar tr:hover { background: rgba(255,255,255,0.05); }
    .table-asociar td { padding: 15px; vertical-align: middle; }
    .table-asociar td:first-child { border-radius: 10px 0 0 10px; }
    .table-asociar td:last-child { border-radius: 0 10px 10px 0; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="panel-management">
    <div class="panel-header-wow">
        <div>
            <h1>Gestión de Fichas Técnicas</h1>
            <p style="opacity: 0.6;">Organiza y vincula la información de calidad de tus productos.</p>
        </div>
        <button onclick="switchTab(2); resetEditor();" class="btn-premium-wow btn-gold">
            <i class="fas fa-plus"></i> Nueva Ficha
        </button>
    </div>

    <!-- Navegación por Pestañas -->
    <div class="tab-nav-nox">
        <div class="tab-link <?= $current_tab == '1' ? 'active' : '' ?>" onclick="switchTab(1)">🔍 EXPLORAR</div>
        <div class="tab-link <?= $current_tab == '2' ? 'active' : '' ?>" onclick="switchTab(2)">✍️ EDITOR</div>
        <div class="tab-link <?= $current_tab == '3' ? 'active' : '' ?>" onclick="switchTab(3)">🔗 ASOCIAR</div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success bg-dark text-success border-success mb-4" style="border-radius: 10px;">
            <i class="fas fa-check-circle"></i> 
            <?php echo $_GET['success'] == '1' ? 'Ficha técnica guardada correctamente.' : 'Vínculo actualizado correctamente.'; ?>
        </div>
    <?php endif; ?>

    <!-- PESTAÑA 1: EXPLORAR -->
    <div id="tab-1" class="tab-content <?= $current_tab == '1' ? 'active' : '' ?>">
        <div class="row">
            <?php foreach($fichas as $f): 
                // Buscar una imagen de ejemplo de un artículo que use esta ficha
                $stmtImg = $db->prepare("SELECT foto_portada FROM articulos WHERE ficha_tecnica_id = ? LIMIT 1");
                $stmtImg->execute([$f['id']]);
                $ejemplo = $stmtImg->fetch();
                $imgUrl = ($ejemplo && $ejemplo['foto_portada']) ? '../' . $ejemplo['foto_portada'] : '../img/logo.png';
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card-ficha h-100 d-flex flex-column">
                        <div style="width:100%; height:120px; background:#000; border-radius:10px; margin-bottom:15px; overflow:hidden; border:1px solid rgba(255,255,255,0.05);">
                            <img src="<?= $imgUrl ?>" style="width:100%; height:100%; object-fit:cover; opacity:0.8;">
                        </div>
                        <span class="label-ficha">ID: #<?php echo $f['id']; ?></span>
                        <h3 style="color: #fff; margin-bottom: 10px;"><?php echo htmlspecialchars($f['nombre_interno']); ?></h3>
                        <p style="font-size: 0.85rem; opacity: 0.7; flex-grow: 1;">
                            <strong>Título Público:</strong> <?php echo htmlspecialchars($f['titulo_publico']); ?><br><br>
                            <?php echo substr(strip_tags($f['materiales']), 0, 100); ?>...
                        </p>
                        <div class="d-flex gap-2 mt-3">
                            <button onclick='cargarParaEditar(<?php echo json_encode($f); ?>)' class="btn-premium-wow" style="background: rgba(255,255,255,0.1); font-size: 0.75rem;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <a href="?delete=<?php echo $f['id']; ?>" onclick="return confirm('¿Eliminar esta ficha?')" class="btn-premium-wow text-danger" style="background: rgba(220,53,69,0.1); font-size: 0.75rem;">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PESTAÑA 2: EDITOR -->
    <div id="tab-2" class="tab-content <?= $current_tab == '2' ? 'active' : '' ?>">
        <div class="editor-container">
            <h2 id="editorTitle" style="color: var(--accent-gold); margin-bottom: 25px; font-weight: 800;">Configurar Ficha Técnica</h2>
            <form method="POST">
                <input type="hidden" name="id" id="f_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="nox-form-group">
                            <label>Nombre Interno (Solo Admin)</label>
                            <input type="text" name="nombre_interno" id="f_nombre" class="input-wow" placeholder="Ej: Madera Palet Premium" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nox-form-group">
                            <label>Título Público (Cliente)</label>
                            <input type="text" name="titulo_publico" id="f_titulo" class="input-wow" placeholder="Ej: Sobre nuestra madera y procesos">
                        </div>
                    </div>
                </div>

                <div class="nox-form-group">
                    <label>Materiales y Origen</label>
                    <textarea name="materiales" id="f_materiales" class="input-wow" rows="8"></textarea>
                </div>

                <div class="nox-form-group">
                    <label>Procesos y Acabados (Artesanía)</label>
                    <textarea name="elaboracion" id="f_elaboracion" class="input-wow" rows="8"></textarea>
                </div>

                <div class="nox-form-group">
                    <label>Observaciones de Calidad (Clavos, marcas...)</label>
                    <textarea name="observaciones" id="f_observaciones" class="input-wow" rows="5"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="nox-form-group">
                            <label>Mantenimiento Sugerido</label>
                            <textarea name="mantenimiento" id="f_mantenimiento" class="input-wow" rows="5"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="nox-form-group">
                            <label>Compromiso Sostenible</label>
                            <textarea name="sostenibilidad" id="f_sostenibilidad" class="input-wow" rows="5"></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-4">
                    <button type="submit" name="guardar_ficha" class="btn-premium-wow btn-gold py-3 px-5" style="flex-grow: 1; justify-content: center;">
                        <i class="fas fa-save"></i> GUARDAR CAMBIOS EN FICHA
                    </button>
                    <button type="button" onclick="switchTab(1)" class="btn-premium-wow" style="background: rgba(255,255,255,0.05);">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- PESTAÑA 3: ASOCIAR -->
    <div id="tab-3" class="tab-content <?= $current_tab == '3' ? 'active' : '' ?>">
        <div class="editor-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 style="color: #fff; font-weight: 800; margin: 0;">Vincular Fichas a Artículos</h2>
                <input type="text" id="searchArt" placeholder="🔍 Buscar por nombre o referencia..." class="input-wow" style="max-width: 400px; padding: 10px 20px;">
            </div>
            
            <div class="scroll-x-wow" style="max-height: 600px; overflow-y: auto;">
                <table class="table-asociar">
                    <thead>
                        <tr style="background: none; color: var(--accent-gold); font-size: 0.7rem; text-transform: uppercase;">
                            <th style="padding: 10px;">Producto / Referencia</th>
                            <th style="padding: 10px;">Categoría</th>
                            <th style="padding: 10px;">Ficha Técnica Actual</th>
                            <th style="padding: 10px; text-align: right;">Acción de Vínculo</th>
                        </tr>
                    </thead>
                    <tbody id="artTableBody">
                        <?php foreach($articulos as $a): ?>
                            <tr class="articulo-row">
                                <td style="display:flex; align-items:center; gap:15px;">
                                    <img src="<?= $a['foto_portada'] ? '../'.$a['foto_portada'] : '../img/logo.png' ?>" style="width:40px; height:40px; object-fit:cover; border-radius:6px; background:#000;">
                                    <div>
                                        <strong style="color: #fff;"><?php echo htmlspecialchars($a['nombre']); ?></strong><br>
                                        <small style="color: #888;"><?php echo $a['referencia']; ?></small>
                                    </div>
                                </td>
                                <td><span class="badge bg-dark border border-secondary"><?php echo $a['categoria']; ?></span></td>
                                <td>
                                    <?php 
                                        $nombre_ficha = "❌ Ninguna";
                                        foreach($fichas as $f) {
                                            if($f['id'] == $a['ficha_tecnica_id']) {
                                                $nombre_ficha = "✅ <span style='color:var(--accent-gold)'>" . htmlspecialchars($f['nombre_interno']) . "</span>";
                                                break;
                                            }
                                        }
                                        echo $nombre_ficha;
                                    ?>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" class="d-flex gap-2 justify-content-end align-items-center">
                                        <input type="hidden" name="sku" value="<?php echo $a['referencia']; ?>">
                                        <select name="ficha_id" class="input-wow" style="padding: 6px; font-size: 0.8rem; width: 200px;">
                                            <option value="0">-- Sin Ficha --</option>
                                            <?php foreach($fichas as $f): ?>
                                                <option value="<?php echo $f['id']; ?>" <?php echo $f['id'] == $a['ficha_tecnica_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($f['nombre_interno']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="asociar_ficha" class="btn-premium-wow btn-gold" style="padding: 6px 15px; font-size: 0.75rem;">
                                            Vincular
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top: 20px; font-size: 0.8rem; opacity: 0.5;">* Mostrando los últimos 100 artículos. Usa el buscador para encontrar cualquier producto.</p>
        </div>
    </div>
</div>

<script>
function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
    
    document.getElementById('tab-' + id).classList.add('active');
    // Ajustar el índice para los botones
    document.querySelectorAll('.tab-link').forEach((l, index) => {
        if((index + 1) == id) l.classList.add('active');
    });
}

function resetEditor() {
    document.getElementById('editorTitle').innerText = 'Crear Nueva Ficha Técnica';
    document.getElementById('f_id').value = '';
    document.getElementById('f_nombre').value = '';
    document.getElementById('f_titulo').value = '';
    document.getElementById('f_materiales').value = '';
    document.getElementById('f_elaboracion').value = '';
    document.getElementById('f_observaciones').value = '';
    document.getElementById('f_mantenimiento').value = '';
    document.getElementById('f_sostenibilidad').value = '';
}

function cargarParaEditar(ficha) {
    document.getElementById('editorTitle').innerText = 'Editando Ficha: ' + ficha.nombre_interno;
    document.getElementById('f_id').value = ficha.id;
    document.getElementById('f_nombre').value = ficha.nombre_interno;
    document.getElementById('f_titulo').value = ficha.titulo_publico;
    document.getElementById('f_materiales').value = ficha.materiales;
    document.getElementById('f_elaboracion').value = ficha.elaboracion;
    document.getElementById('f_observaciones').value = ficha.observaciones;
    document.getElementById('f_mantenimiento').value = ficha.mantenimiento;
    document.getElementById('f_sostenibilidad').value = ficha.sostenibilidad;
    
    switchTab(2); // Ir a la pestaña de editor
}

// Buscador dinámico para la pestaña Asociar
document.getElementById('searchArt').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let rows = document.querySelector("#artTableBody").rows;
    for (let i = 0; i < rows.length; i++) {
        let text = rows[i].textContent.toUpperCase();
        rows[i].style.display = text.indexOf(filter) > -1 ? "" : "none";
    }
});
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
