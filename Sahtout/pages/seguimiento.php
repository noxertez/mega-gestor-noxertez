<?php
define('ALLOWED_ACCESS', true);
require_once '../includes/paths.php';
require_once '../includes/header.php'; // Usa la cabecera del sitio para estilo y coherencia
require_once '../api/config.php';

$db = conectar();

// 1. LIMITACIÓN DE TASA (Rate Limiting)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limit_reached = false;

try {
    $stmt = $db->prepare("SELECT consultas, UNHEX(UNIX_TIMESTAMP(ultima_consulta)) as ts FROM tracking_rate_limits WHERE ip = ?");
    $stmt->execute([$ip]);
    $rate = $stmt->fetch();
    $now = time();

    if ($rate) {
        $stmt_time = $db->prepare("SELECT UNIX_TIMESTAMP(ultima_consulta) FROM tracking_rate_limits WHERE ip = ?");
        $stmt_time->execute([$ip]);
        $last_ts = $stmt_time->fetchColumn();

        if (($now - $last_ts) > 600) {
            $db->prepare("UPDATE tracking_rate_limits SET consultas = 1, ultima_consulta = NOW() WHERE ip = ?")->execute([$ip]);
        } else {
            if ($rate['consultas'] >= 10) $limit_reached = true;
            else $db->prepare("UPDATE tracking_rate_limits SET consultas = consultas + 1 WHERE ip = ?")->execute([$ip]);
        }
    } else {
        $db->prepare("INSERT INTO tracking_rate_limits (ip, consultas, ultima_consulta) VALUES (?, 1, NOW())")->execute([$ip]);
    }
} catch (Exception $e) {
    // Si falla la tabla de logs, continuamos sin limitación
}

$code = $_GET['code'] ?? '';
$pedido = null;
$error_msg = '';

if ($code && !$limit_reached) {
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE tracking_code = ?");
    $stmt->execute([$code]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $error_msg = 'code_not_found';
    } elseif ($pedido['tracking_activo'] == 0) {
        $error_msg = 'not_active';
    } else {
        // Cargar workflow
        $stmtF = $db->prepare("
            SELECT fnp.nombre, pn.estado, pn.fecha_fin, fnp.orden
            FROM pedido_nodos pn
            JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
            WHERE pn.id_pedido = ?
            ORDER BY fnp.orden ASC
        ");
        $stmtF->execute([$pedido['id']]);
        $pedido['workflow'] = $stmtF->fetchAll();

        // Mapeo friendly
        $mapeo = [
            'Pedido Entrante'   => 'Pedido confirmado',
            'Pedido Recibido'   => 'Pedido confirmado',
            'Materiales'        => 'Preparando materiales',
            'En Proceso'        => 'En el taller, haciéndose',
            'Producción'        => 'En el taller, haciéndose',
            'Montado'           => 'Últimos detalles',
            'Tintado'           => 'Últimos detalles',
            'Barnizado'         => 'Últimos detalles',
            'Control Calidad'   => 'Revisión final',
            'Embalaje'          => 'Empaquetando',
            'Envío'             => 'En camino',
            'Entregado'         => '¡Entregado!'
        ];
        
        $items = json_decode($pedido['items_json'] ?? '[]', true);
        $nombres = array_map(function($it) { return $it['nombre'] ?? ''; }, $items);
        $pedido['items_display'] = implode(', ', array_filter($nombres));
    }
}

$carrier_urls = [
    'Correos' => 'https://www.correos.es/es/es/herramientas/localizador/envios/detalle?numero=',
    'SEUR'    => 'https://www.seur.com/livetracking/pages/seguimiento-online-busqueda.do?recoId=',
    'MRW'     => 'https://www.mrw.es/seguimiento_envios/MRW_resultado_consultas.asp?numexp=',
    'GLS'     => 'https://www.gls-spain.es/es/ayuda/seguimiento-envio/?p=',
    'DHL'     => 'https://www.dhl.com/es-es/home/tracking/tracking-express.html?submit=1&tracking-id='
];
?>

<style>
    .tracking-page-container {
        max-width: 550px;
        margin: 40px auto;
        padding: 20px;
    }
    .tracking-card-wow {
        background: #1e293b;
        border: 1px solid rgba(212,175,55,0.2);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    .tracking-title-wow { color: #d4af37; text-align: center; margin-bottom: 25px; }
    
    .timeline-wow { margin-top: 30px; position: relative; padding-left: 35px; border-left: 2px solid #334155; }
    .timeline-node-wow { position: relative; margin-bottom: 30px; }
    .node-dot-wow {
        position: absolute; left: -42px; top: 0; width: 14px; height: 14px;
        background: #0f172a; border: 2px solid #334155; border-radius: 50%;
    }
    .node-dot-wow.completed { background: #d4af37; border-color: #d4af37; box-shadow: 0 0 10px rgba(212,175,55,0.4); }
    .node-dot-wow.active { background: #fbbf24; border-color: #fbbf24; animation: pulse-gold 2s infinite; }
    
    @keyframes pulse-gold {
        0% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(251, 191, 36, 0); }
        100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
    }

    .node-title-wow { font-weight: bold; color: #fff; font-size: 1.1rem; }
    .node-time-wow { color: #94a3b8; font-size: 0.85rem; }
    
    .tracking-input-wow {
        background: #0f172a; border: 1px solid #d4af37; border-radius: 8px;
        padding: 15px; color: #fff; width: 100%; text-align: center;
        font-size: 1.2rem; font-family: monospace; text-transform: uppercase;
        margin-bottom: 20px;
    }
    .btn-gold-wow {
        background: #d4af37; color: #000; font-weight: bold; padding: 15px;
        border-radius: 8px; width: 100%; text-align: center; text-decoration: none;
        display: block; transition: 0.3s;
    }
    .btn-gold-wow:hover { background: #fbbf24; transform: translateY(-2px); }
</style>

<div class="tracking-page-container">
    <div class="tracking-card-wow">
        <?php if ($limit_reached): ?>
            <div style="text-align: center; color: #94a3b8;">
                <i class="fas fa-hand-stop" style="font-size: 3rem; margin-bottom: 20px;"></i>
                <h3>Demasiadas consultas</h3>
                <p>Por favor, espera unos minutos e inténtalo de nuevo.</p>
            </div>
        <?php elseif (!$pedido): ?>
            <h1 class="tracking-title-wow">Consultar Pedido</h1>
            <p style="color: #94a3b8; text-align: center; margin-bottom: 25px;">Introduce el código NXT-XXXXXX que recibiste.</p>
            
            <?php if ($error_msg == 'code_not_found'): ?>
                <p style="color: #ef4444; background: rgba(239,68,68,0.1); padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 20px;">Código no encontrado. Revisa que sea correcto.</p>
            <?php elseif ($error_msg == 'not_active'): ?>
                <p style="color: #fbbf24; background: rgba(251,191,36,0.1); padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 20px;">El seguimiento para este pedido aún no está público.</p>
            <?php endif; ?>

            <form action="seguimiento" method="GET">
                <input type="text" name="code" class="tracking-input-wow" placeholder="NXT-XXXXXX" maxlength="10" required>
                <button type="submit" class="btn-gold-wow">RASTREAR PEDIDO</button>
            </form>
        <?php else: ?>
            <!-- VISTA DETALLE PEDIDO -->
            <div style="text-align: center; margin-bottom: 30px;">
                <span style="color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">Estado del Pedido</span>
                <h2 style="color: #d4af37; font-size: 1.8rem;"><?= htmlspecialchars($pedido['numero_pedido']) ?></h2>
            </div>

            <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; margin-bottom: 30px;">
                <p style="color: #fff; font-weight: bold;"><?= htmlspecialchars($pedido['items_display'] ?: 'Artículo Noxertez') ?></p>
                <p style="color: #94a3b8; font-size: 0.85rem;">Pedido realizado el <?= date('d/m/Y', strtotime($pedido['fecha_pedido'])) ?></p>
            </div>

            <?php if ($pedido['fecha_estimada_entrega']): ?>
                <div style="border: 1px solid rgba(212,175,55,0.3); padding: 15px; border-radius: 10px; display: flex; align-items: center; gap: 15px; margin-bottom: 30px;">
                    <i class="fas fa-calendar-check" style="color: #d4af37; font-size: 1.5rem;"></i>
                    <div>
                        <p style="color: #94a3b8; font-size: 0.75rem; text-transform: uppercase;">Entrega Estimada</p>
                        <p style="color: #fff; font-weight: bold;"><?= date('d M Y', strtotime($pedido['fecha_estimada_entrega'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TIMELINE -->
            <div class="timeline-wow">
                <?php 
                $workflow = $pedido['workflow'];
                $last_completed_idx = -1;
                foreach($workflow as $idx => $node) if ($node['estado'] == 'completado') $last_completed_idx = $idx;
                
                foreach($workflow as $idx => $node): 
                    $is_completed = ($node['estado'] == 'completado');
                    $is_active = (!$is_completed && $idx == $last_completed_idx + 1);
                    $dot_class = $is_completed ? 'completed' : ($is_active ? 'active' : '');
                ?>
                <div class="timeline-node-wow">
                    <div class="node-dot-wow <?= $dot_class ?>"></div>
                    <div class="node-title-wow" style="<?= !$is_completed && !$is_active ? 'color: #475569;' : '' ?>">
                        <?= htmlspecialchars($mapeo[$node['nombre']] ?? $node['nombre']) ?>
                    </div>
                    <?php if ($node['fecha_fin']): ?>
                        <div class="node-time-wow">Completado el <?= date('d/m/Y', strtotime($node['fecha_fin'])) ?></div>
                    <?php elseif ($is_active): ?>
                        <div style="color: #d4af37; font-size: 0.8rem;"><i class="fas fa-hammer fa-spin"></i> En curso...</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pedido['tracking_envio']): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #334155;">
                    <p style="color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 10px;">Seguimiento Transportista</p>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" readonly value="<?= htmlspecialchars($pedido['tracking_envio']) ?>" style="flex-grow: 1; background: #0f172a; border: 1px solid #334155; padding: 10px; border-radius: 6px; color: #fff; font-family: monospace;">
                        <?php $url = $carrier_urls[$pedido['transportista']] ?? ''; if ($url): ?>
                            <a href="<?= $url . $pedido['tracking_envio'] ?>" target="_blank" class="btn-gold-wow" style="width: auto; padding: 10px 20px;">Rastrear</a>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top: 5px; font-size: 0.8rem; color: #d4af37; font-weight: bold;"><?= htmlspecialchars($pedido['transportista']) ?></p>
                </div>
            <?php endif; ?>

            <a href="seguimiento" style="display: block; text-align: center; margin-top: 40px; color: #94a3b8; text-decoration: none; font-size: 0.9rem;">
                <i class="fas fa-search"></i> Consultar otro código
            </a>
        <?php endif; ?>
    </div>
</div>

<div style="height: 60px;"></div>

<!-- Botón de WhatsApp flotante mejorado -->
<a href="https://wa.me/34600000000?text=Hola,%20tengo%20una%20duda%20sobre%20mi%20pedido%20<?= $pedido['numero_pedido'] ?? $code ?>" 
   style="position: fixed; bottom: 20px; right: 20px; background: #25d366; color: #fff; padding: 15px 25px; border-radius: 50px; text-decoration: none; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 30px rgba(37,211,102,0.3); z-index: 1000; font-weight: bold;">
    <i class="fab fa-whatsapp" style="font-size: 1.5rem;"></i> ¿Dudas? Escríbenos
</a>

<?php require_once '../includes/footer.php'; ?>
