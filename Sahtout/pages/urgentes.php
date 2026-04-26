<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
// pages/urgentes.php  (reemplaza o complementa news.php del CMS)
include('../includes/header.php');
if(!isset($_SESSION['user_id'])&&!isset($_SESSION['username'])){
    header('Location:/noxertez/login.php');exit;
}
require_once('../api/config.php');
$db = conectar();

$items=$db->query(
    'SELECT * FROM urgentes WHERE completado=0
     ORDER BY FIELD(prioridad,"alta","media","baja"), fecha ASC'
)->fetchAll();
?>

<div class='nox-content'>
  <div style='display:flex; align-items:center; justify-content:space-between;'>
    <h1>Urgentes
      <span style='background:#FF4444; color:#fff; border-radius:12px;
             padding:2px 10px; font-size:0.6em; margin-left:10px;'>
        <?= count($items) ?>
      </span>
    </h1>
    <button class='nox-btn' onclick='abrirNuevo()'>+ Añadir</button>
  </div>

  <div style='display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin-top:20px;'>
  <?php
foreach($items as $item): ?>
    <div style='background:rgba(0,0,0,0.6);
                border-left:4px solid <?= $item['prioridad']==='alta'?'#FF4444':($item['prioridad']==='media'?'#F0A500':'#90EE90') ?>;
                border-radius:4px; padding:16px;'>
      <?php
if(!empty($item['imagen'])): ?>
        <img src='/noxertez/uploads/urgentes/<?= htmlspecialchars($item['imagen']) ?>'
             style='width:100%; height:140px; object-fit:cover; border-radius:4px; margin-bottom:10px;'>
      <?php
endif; ?>
      <div style='color:#C89B3C; font-weight:bold; font-size:1.05em;'>
        <?= htmlspecialchars($item['titulo']) ?>
      </div>
      <div style='color:#AAA; font-size:0.88em; margin:6px 0;'>
        <?= nl2br(htmlspecialchars($item['descripcion']??'')) ?>
      </div>
      <div style='display:flex; gap:8px; margin-top:10px;'>
        <button class='nox-btn' onclick='completar(<?= $item['id'] ?>)'>✓ Listo</button>
        <span style='font-size:0.8em; padding:3px 10px; border-radius:10px; color:#fff;
              background:<?= $item['prioridad']==='alta'?'#8B0000':($item['prioridad']==='media'?'#8B5000':'#1A5C38') ?>;'>
          <?= ucfirst($item['prioridad']) ?>
        </span>
      </div>
    </div>
  <?php
endforeach; ?>
  </div>
</div>

<!-- Modal Nuevo Item Urgente -->
<div id="modalNuevo" class="nox-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8);">
  <div style="background:#1a1a1a; margin:10% auto; padding:20px; border:1px solid #C89B3C; width:90%; max-width:500px; border-radius:8px;">
    <h2 style="color:#C89B3C; border-bottom:1px solid #C89B3C; padding-bottom:10px;">Nuevo Tema Urgente</h2>
    <form id="formNuevo" onsubmit="guardarUrgente(event)">
      <div style="margin-top:15px;">
        <label style="display:block; color:#AAA; margin-bottom:5px;">Título</label>
        <input type="text" name="titulo" class="nox-input" required style="width:100%; box-sizing:border-box;">
      </div>
      <div style="margin-top:15px;">
        <label style="display:block; color:#AAA; margin-bottom:5px;">Descripción</label>
        <textarea name="descripcion" class="nox-input" style="width:100%; height:80px; box-sizing:border-box;"></textarea>
      </div>
      <div style="margin-top:15px;">
        <label style="display:block; color:#AAA; margin-bottom:5px;">Prioridad</label>
        <select name="prioridad" class="nox-input" style="width:100%;">
          <option value="baja">Baja</option>
          <option value="media" selected>Media</option>
          <option value="alta">Alta (URGENTE)</option>
        </select>
      </div>
      <div style="margin-top:15px;">
        <label style="display:block; color:#AAA; margin-bottom:5px;">Imagen (Opcional)</label>
        <input type="file" name="imagen" class="nox-input" style="width:100%;">
      </div>
      <div style="margin-top:25px; display:flex; gap:10px;">
        <button type="submit" class="nox-btn" style="flex:1;">💾 Guardar</button>
        <button type="button" class="nox-btn" onclick="cerrarModal()" style="background:#444; color:#fff !important;">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirNuevo() {
  document.getElementById('modalNuevo').style.display = 'block';
}
function cerrarModal() {
  document.getElementById('modalNuevo').style.display = 'none';
}
function guardarUrgente(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  fetch('/noxertez/api/index.php?ruta=urgentes', {
    method: 'POST',
    body: formData
  }).then(r => r.json()).then(data => {
    if(data.ok) location.reload();
    else alert('Error al guardar');
  });
}
function completar(id){
  fetch('/noxertez/api/index.php?ruta=urgentes',{
    method:'PUT',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:id,completado:1})
  }).then(()=>location.reload());
}
</script>

<style>
/* Estilos básicos para el modal */
.nox-modal {
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
<?php
include('../includes/footer.php'); ?>



