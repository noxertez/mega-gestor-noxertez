<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);

// pages/catalogo_publico.php
include('../includes/header.php');

// Detección automática de la URL actual para evitar errores 404 con <base href> o .htaccess
$self = strtok($_SERVER['REQUEST_URI'], '?'); 

// Usar la base de datos noxertez
require_once('../api/config.php');
$db = conectar();

// Filtros del buscador
$busq  = (isset($_GET['busq'])) ? $_GET['busq'] : '';
$cat   = (isset($_GET['cat'])) ? $_GET['cat'] : '';
$marca = (isset($_GET['marca'])) ? $_GET['marca'] : 'NOXERTEZ';

// Configuración de Marcas Oficiales (Mapeo de UI a Valores de DB)
$marcas_config = [
    'NOXERTEZ' => [
        'primary' => '#581c87',
        'accent'  => '#c5a059',
        'db_values' => ['NOXERTEZ']
    ],
    'CANDLE HOLDER OF THE SOUL' => [
        'primary' => '#78350f',
        'accent'  => '#d6d3d1',
        'db_values' => ['CANDLE HOLDER OF THE SOUL', 'CANDLEHOLDER', 'CANDLE']
    ],
    'THE SECRET ZEN GARDEN' => [
        'primary' => '#064e3b',
        'accent'  => '#a3e635',
        'db_values' => ['THE SECRET ZEN GARDEN', 'ZEN GARDEN', 'zen', 'ZEN']
    ]
];

// Estilo activo y mapeo
$config = $marcas_config[$marca] ?? $marcas_config['NOXERTEZ'];
$db_variants = $config['db_values'];
$in_clause = str_repeat('?,', count($db_variants) - 1) . '?';

// 2. Determinar columna TrendiOff según marca activada en el catálogo
$columna_trendioff = 'trendiof_URL'; 
if ($marca === 'CANDLE HOLDER OF THE SOUL') {
    $columna_trendioff = 'trendioff_velas_URL';
} elseif ($marca === 'THE SECRET ZEN GARDEN') {
    $columna_trendioff = 'trendioff_piedras_URL';
}

// Obtener artículos con filtros (Busca en todas las variantes de nombre de la marca)
$sql = "SELECT a.*, pv.$columna_trendioff as trendiof_URL FROM articulos a LEFT JOIN plataformas_ventas pv ON a.referencia = pv.SKU_BASE WHERE a.activo=1 AND a.es_variante = 'BASE' AND a.marca IN ($in_clause)";
$params = $db_variants;

if ($busq) {
    $sql .= ' AND (nombre LIKE ? OR referencia LIKE ? OR descripcion LIKE ?)';
    $params = array_merge($params, ["%$busq%","%$busq%","%$busq%"]);
}
if ($cat) { 
    $sql .= ' AND categoria = ?'; 
    $params[] = $cat; 
}
$sql .= ' ORDER BY categoria, nombre';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$articulos = $stmt->fetchAll();

// Obtener categor\u00edas \u00fanicas para la marca actual (para el selector)
$stmtCats = $db->prepare("SELECT DISTINCT categoria FROM articulos WHERE activo=1 AND es_variante='BASE' AND marca IN ($in_clause) AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria ASC");
$stmtCats->execute($db_variants);
$cats = $stmtCats->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
:root {
    --brand-primary: <?= $config['primary'] ?>;
    --brand-accent: <?= $config['accent'] ?>;
}
body { background-color: #000 !important; }
.nox-content { min-height: 100vh; background: #000; }
.nox-card { border-color: var(--brand-accent) !important; }
.btn-brand-selector {
    font-family: 'Cinzel', serif;
    padding: 12px 30px;
    border-radius: 4px;
    border: 2px solid var(--brand-accent);
    color: #fff;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.85em;
    font-weight: 600;
    text-transform: uppercase;
    background: transparent;
}
.btn-brand-selector:hover, .btn-brand-selector.active {
    background: var(--brand-primary);
    border-color: var(--brand-primary);
    box-shadow: 0 0 15px var(--brand-primary);
    color: #fff;
}
.title-brand { color: var(--brand-accent); font-family: 'Cinzel', serif; border-bottom: 1px solid var(--brand-accent); padding-bottom: 10px; margin-bottom: 20px;}
.badge-brand { background: var(--brand-accent) !important; color: #000 !important; }
.btn-outline-brand { border-color: var(--brand-accent); color: var(--brand-accent); }
.btn-outline-brand:hover { background: var(--brand-accent); color: #000; }
</style>

<div class='nox-content' style="padding: 20px; color: #fff; font-family: 'Quicksand', sans-serif;">

  <!-- CABECERA CON BUSCADOR -->
  <div style='text-align:center; margin-bottom:40px;'>
    <h1 style="color:var(--brand-accent); font-size:3em; font-family: 'Cinzel', serif; text-transform: uppercase; letter-spacing: 4px;">Catálogo Noxertez</h1>
    
    <!-- SELECTOR DE MARCAS -->
    <div style='display:flex; justify-content:center; gap:15px; margin: 30px 0; flex-wrap:wrap;'>
        <?php foreach($marcas_config as $name => $c): ?>
            <a href='<?php echo $self; ?>?marca=<?php echo urlencode($name); ?>' 
               class='btn-brand-selector <?php echo ($marca === $name) ? "active" : ""; ?>' 
               style='border-color: <?php echo $c["accent"]; ?>;'>
               <?php echo $name; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method='GET' action='<?php echo $self; ?>' style='display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:20px;'>
      <!-- Mantenemos la marca seleccionada en el formulario de búsqueda -->
      <input type="hidden" name="marca" value="<?php echo htmlspecialchars($marca); ?>">
      
      <input type='text' name='busq' value="<?php echo htmlspecialchars($busq); ?>"
             placeholder="Buscar en <?php echo $marca; ?>..."
             class='form-control' style='width:320px; background: rgba(255,255,255,0.05); border: 1px solid var(--brand-accent); color: #fff;'>
      <select name='cat' class='form-control' style='width:200px; background: rgba(255,255,255,0.05); border: 1px solid var(--brand-accent); color: #fff;'>
        <option value=''>Todas las categorías</option>
        <?php foreach($cats as $c): ?>
          <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($cat === $c) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($c); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type='submit' class='btn' style="background: var(--brand-accent); color: #000; font-weight: bold;">Buscar</button>
      <a href="<?php echo $self; ?>?marca=<?php echo urlencode($marca); ?>" class='btn-brand-selector' style="font-family: 'Quicksand', sans-serif; font-size: 0.8em; padding: 10px 20px;">X Limpiar Filtros</a>
    </form>
  </div>

  <!-- CONTADOR DE RESULTADOS -->
  <p style='color:var(--brand-accent); border-bottom: 1px solid var(--brand-accent); padding-bottom: 10px; font-family: "Cinzel", serif;'>
    <i class="fas fa-layer-group"></i> <?= $marca ?> / <?= count($articulos) ?> pieza(s)
  </p>

  <!-- GRID DE TARJETAS -->
  <div style='display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:25px;'>
  <?php foreach($articulos as $art): ?>
    <div class="nox-card" style='background:rgba(255,255,255,0.03); border:1px solid var(--brand-accent);
                border-radius:8px; overflow:hidden; transition:all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.5);'>

      <!-- IMAGEN -->
      <a href="<?php echo $base_path; ?>pages/producto.php?ref=<?php echo urlencode($art['referencia']); ?>" style="text-decoration: none;">
      <div style="position: relative; height: 200px; overflow: hidden;">
        <?php 
          $img_src = $art['foto_portada'];
          $has_image = !empty($img_src);
          $rel_path = "";
          
          if ($has_image) {
              $clean = str_replace('\\', '/', $img_src);
              if (strpos($clean, '/imagenes/') !== false) {
                  $rel_path = "uploads/articulos" . substr($clean, strpos($clean, '/imagenes/'));
              } elseif (strpos($clean, ':/') !== false) {
                  $parts = explode('/', $clean);
                  $rel_path = "uploads/articulos/imagenes/" . end($parts);
              } else {
                  $rel_path = (strpos($clean, 'uploads/') === 0) ? $clean : "uploads/" . $clean;
              }
              
              // Cache busting
              $full_path_check = dirname(__DIR__) . '/' . $rel_path;
              $version = (file_exists($full_path_check)) ? filemtime($full_path_check) : time();
              $img_src = $base_path . $rel_path . "?v=" . $version;
          } else {
              $img_src = $base_path . "img/logo.png";
          }
        ?>
        <?php if ($has_image): ?>
          <img src='<?php echo htmlspecialchars($img_src); ?>'
               style='width:100%; height:100%; object-fit:cover;'>
        <?php else: ?>
          <div style='width:100%; height:100%; background: linear-gradient(135deg, #1B2A4A, #0a0a0a);
                      display:flex; align-items:center; justify-content:center;
                      color:#C89B3C; font-size:3em;'>⚒️</div>
        <?php endif; ?>
        <?php if($art['categoria']): ?>
          <span class="badge badge-brand" style="position: absolute; top: 10px; right: 10px; padding: 4px 10px; border-radius: 4px; font-size: 0.7em; font-weight: bold; text-transform: uppercase;">
            <?php echo htmlspecialchars($art['categoria']); ?>
          </span>
        <?php endif; ?>
      </div>
      </a>

      <!-- DATOS -->
      <div style='padding:18px;'>
        <div style='color:#888; font-size:0.8em; margin-bottom:6px;'>
          Ref: <?php echo htmlspecialchars($art['referencia']); ?>
        </div>
        <a href="<?php echo $base_path; ?>pages/producto.php?ref=<?php echo urlencode($art['referencia']); ?>" style="text-decoration: none;">
          <div style='color:#fff; font-weight:bold; margin-bottom:10px; font-size:1.25em; font-family: "Cinzel", serif; color: var(--brand-accent);'>
            <?php echo htmlspecialchars($art['nombre']); ?>
          </div>
        </a>
        <?php if ($art['descripcion']): ?>
          <div style='color:#AAA; font-size:0.9em; margin-bottom:15px; line-height:1.5; height: 4.5em; overflow: hidden;'>
            <?php echo nl2br(htmlspecialchars(substr($art['descripcion']??'',0,120))); ?>
            <?php echo (strlen($art['descripcion']??'')>120)?'...':''; ?>
          </div>
        <?php endif; ?>
        <div style='display: flex; justify-content: space-between; align-items: center;'>
            <div style='color:#fff; font-size:1.5em; font-weight:bold;'>
              <?php echo number_format($art['precio'],2); ?> €
            </div>
            <div style="display:flex; justify-content: flex-end;">
                <a href="<?php echo $base_path; ?>pages/producto.php?ref=<?php echo urlencode($art['referencia']); ?>" class="btn btn-sm btn-outline-brand" style="font-weight: bold; text-transform: uppercase; font-size: 0.75em;">Ver Detalles</a>
            </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <?php if (empty($articulos)): ?>
    <div style='text-align:center; color:#888; margin-top:60px; padding: 40px; background: rgba(255,255,255,0.02); border-radius: 15px; border: 1px dashed var(--brand-accent);'>
      <i class="fas fa-search" style="font-size: 3em; margin-bottom: 20px; color: var(--brand-accent);"></i>
      <p>No se encontraron piezas en la colección <b><?= $marca ?></b>.</p>
      <a href='<?= $self ?>?marca=<?= urlencode($marca) ?>' class="btn btn-brand" style="background: var(--brand-accent); color: #000; font-weight: bold;">Limpiar Filtros</a>
    </div>
  <?php endif; ?>

</div>
<?php include('../includes/footer.php'); ?>
