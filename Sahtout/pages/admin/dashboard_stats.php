<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
require_once '../../api/config.php';
$db = conectar();

$page_class = 'management-page';
$base_path = '../../';
include('../../includes/header.php');

if (!isset($_SESSION['user_id'])) { header('Location:' . $base_path . 'pages/login.php'); exit; }
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/css/management_style.css?v=2.1">
<link rel="stylesheet" href="<?= $base_path ?>assets/css/dashboard_stats.css?v=2.1">
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="stats-container">
    <div class="stats-header">
        <h1><i class="fas fa-chart-line"></i> Análisis de Negocio</h1>
        <div class="stats-filters">
            <button class="filter-btn active" id="btnMes" onclick="seleccionarPeriodo('mes')">30 días</button>
            <button class="filter-btn" id="btnAnio" onclick="seleccionarPeriodo('anio')">Año</button>
            <button class="filter-btn" id="btnGenerar" style="background:#10b981;color:#fff;border:none;font-weight:700;margin-left:10px" onclick="cargarEstadisticas()">
                <i class="fas fa-sync" id="iconGenerar"></i> <span id="textGenerar">GENERAR INFORME</span>
            </button>
        </div>
    </div>

    <!-- KPIs Rápidos -->
    <div class="stats-grid-top">
        <div class="kpi-card">
            <span class="label">Pedidos Totales</span>
            <span class="value" id="kpiPedidos">—</span>
            <span class="trend" id="kpiTrendPedidos"><i class="fas fa-arrow-up"></i> +5% vs prev.</span>
        </div>
        <div class="kpi-card">
            <span class="label">Ingresos Estimados</span>
            <span class="value" id="kpiIngresos">—</span>
            <span class="trend" id="kpiTrendIngresos">Estadística mensual</span>
        </div>
        <div class="kpi-card">
            <span class="label">Top Categoría</span>
            <span class="value" id="kpiTopCat">—</span>
            <span class="trend">Más popular</span>
        </div>
        <div class="kpi-card">
            <span class="label">Visitantes Hoy</span>
            <span class="value" id="kpiVisHoy">—</span>
            <span class="trend" id="kpiTrendVisHoy">Únicos del día</span>
        </div>
        <div class="kpi-card">
            <span class="label">Visitantes Mes</span>
            <span class="value" id="kpiVisMes">—</span>
            <span class="trend" id="kpiTrendVisMes">Mes actual</span>
        </div>
        <div class="kpi-card">
            <span class="label">Visitantes Totales</span>
            <span class="value" id="kpiVisTotal">—</span>
            <span class="trend">Desde inicio</span>
        </div>
    </div>

    <!-- Rejilla de Gráficos -->
    <div class="stats-grid-charts">
        
        <!-- TOP PRODUCTOS -->
        <div class="chart-card">
            <h3><i class="fas fa-trophy" style="color:#C89B3C"></i> Top 10 Artículos Más Vendidos</h3>
            <div class="chart-container">
                <canvas id="chartTopArticulos"></canvas>
            </div>
        </div>

        <!-- CATEGORÍAS -->
        <div class="chart-card">
            <h3><i class="fas fa-tags" style="color:#3b82f6"></i> Distribución por Categoría</h3>
            <div class="chart-container">
                <canvas id="chartCategorias"></canvas>
            </div>
        </div>

        <!-- HISTÓRICO VENTAS -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-area" style="color:#10b981"></i> Evolución de Pedidos</h3>
            <div class="chart-container">
                <canvas id="chartVentasHist"></canvas>
            </div>
        </div>

        <!-- GRÁFICO VISITANTES -->
        <div class="chart-card">
            <h3><i class="fas fa-users" style="color:#6366f1"></i> Tráfico de Visitantes</h3>
            <div class="chart-container">
                <canvas id="chartVisHist"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
const BASE = '<?= $base_path ?>';
let charts = {};
let periodoActual = 'mes';

document.addEventListener('DOMContentLoaded', () => {
    // No cargamos automáticamente para ahorrar recursos
    console.log("Panel de estadísticas listo. Pulsa 'Generar' para cargar datos.");
});

async function cargarEstadisticas() {
    const btn = document.getElementById('btnGenerar');
    const icon = document.getElementById('iconGenerar');
    const text = document.getElementById('textGenerar');
    const periodo = periodoActual;

    try {
        btn.disabled = true;
        icon.classList.add('fa-spin');
        text.innerText = 'GENERANDO...';

        console.log("Generando estadísticas para:", periodo);
        const res = await fetch(`${BASE}api/index.php?ruta=flujo_dashboard_stats&periodo=${periodo}`);
        if (!res.ok) throw new Error("Error en la conexión a la API (" + res.status + ")");
        
        const statsPayload = await res.json();
        console.log("📊 API Stats Payload:", statsPayload);

        if (!statsPayload) {
            throw new Error("La API devolvió un resultado nulo o vacío.");
        }
        if (typeof statsPayload !== 'object') {
            throw new Error("Formato de API inválido: " + (typeof statsPayload));
        }
        if (statsPayload.error) {
            throw new Error("Error de API: " + statsPayload.error);
        }
        
        actualizarKPIs(statsPayload);
        renderCharts(statsPayload);
    } catch (e) {
        console.error("❌ Error de Carga:", e);
        alert("❌ Error de Análisis: " + e.message + "\n\n(Prueba Ctrl+F5)");
    } finally {
        btn.disabled = false;
        icon.classList.remove('fa-spin');
        text.innerText = 'GENERAR INFORME';
    }
}

function seleccionarPeriodo(periodo) {
    periodoActual = periodo;
    document.getElementById('btnMes').classList.toggle('active', periodo === 'mes');
    document.getElementById('btnAnio').classList.toggle('active', periodo === 'anio');
}

function actualizarKPIs(stats) {
    if (!stats || typeof stats !== 'object') {
        console.warn("actualizarKPIs: No hay datos válidos.");
        return;
    }

    try {
        // Pedidos totales
        const hist = Array.isArray(stats.historico) ? stats.historico : [];
        const totalPedidos = hist.reduce((sum, item) => sum + parseInt(item.total || 0), 0);
        const elPedidos = document.getElementById('kpiPedidos');
        if (elPedidos) elPedidos.innerText = totalPedidos;
        
        // Top Categoría
        const cats = Array.isArray(stats.categorias) ? stats.categorias : [];
        const elCat = document.getElementById('kpiTopCat');
        if (elCat) {
            elCat.innerText = (cats.length > 0) ? (cats[0].categoria || '—') : '—';
        }

        // Ingresos
        const elIng = document.getElementById('kpiIngresos');
        if (elIng) elIng.innerText = (totalPedidos * 25).toLocaleString() + '€';
        
        // Visitantes
        const v = stats.visitantes || {};
        const elHoy = document.getElementById('kpiVisHoy');
        const elMes = document.getElementById('kpiVisMes');
        const elTot = document.getElementById('kpiVisTotal');
        
        if (elHoy) elHoy.innerText = v.hoy ?? 0;
        if (elMes) elMes.innerText = v.mes ?? 0;
        if (elTot) elTot.innerText = v.total ?? 0;

    } catch (err) {
        console.error("Error en actualizarKPIs:", err);
    }
}

function renderCharts(stats) {
    if (!stats || typeof stats !== 'object') return;
    
    const colors = ['#C89B3C', '#3b82f6', '#10b981', '#ef4444', '#f59e0b', '#6366f1', '#ec4899', '#84cc16'];

    const topArt = Array.isArray(stats.top_articulos) ? stats.top_articulos : [];
    const cats = Array.isArray(stats.categorias) ? stats.categorias : [];
    const hist = Array.isArray(stats.historico) ? stats.historico : [];
    const v = stats.visitantes || {};
    const vHist = Array.isArray(v.chart) ? v.chart : [];

    // 1. TOP ARTÍCULOS
    try {
        updateOrCreateChart('chartTopArticulos', 'bar', {
            labels: topArt.map(a => a.sku_articulo || 'Desc.'),
            datasets: [{
                label: 'Pedidos',
                data: topArt.map(a => a.total || 0),
                backgroundColor: colors[0],
                borderRadius: 8
            }]
        }, { indexAxis: 'y' });
    } catch(err) { console.warn("Chart 1 Error:", err); }

    // 2. CATEGORÍAS
    try {
        updateOrCreateChart('chartCategorias', 'doughnut', {
            labels: cats.map(c => c.categoria || 'Sin Cat.'),
            datasets: [{
                data: cats.map(c => c.total || 0),
                backgroundColor: colors,
                borderWidth: 0
            }]
        });
    } catch(err) { console.warn("Chart 2 Error:", err); }

    // 3. HISTÓRICO VENTAS
    try {
        updateOrCreateChart('chartVentasHist', 'line', {
            labels: hist.map(h => h.etiqueta || ''),
            datasets: [{
                label: 'Pedidos',
                data: hist.map(h => h.total || 0),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981'
            }]
        });
    } catch(err) { console.warn("Chart 3 Error:", err); }

    // 4. HISTÓRICO VISITANTES
    try {
        updateOrCreateChart('chartVisHist', 'line', {
            labels: vHist.map(vh => vh.etiqueta || ''),
            datasets: [{
                label: 'Visitantes',
                data: vHist.map(vh => vh.total || 0),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1'
            }]
        });
    } catch(err) { console.warn("Chart 4 Error:", err); }
}

function updateOrCreateChart(id, type, data, options = {}) {
    if (charts[id]) charts[id].destroy();
    
    const ctx = document.getElementById(id).getContext('2d');
    charts[id] = new Chart(ctx, {
        type: type,
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: type === 'doughnut',
                    position: 'right',
                    labels: { color: '#94a3b8', font: { size: 11 } }
                }
            },
            scales: type !== 'doughnut' ? {
                y: { grid: { color: '#1e293b' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display : false }, ticks: { color: '#94a3b8' } }
            } : {},
            ...options
        }
    });
}
</script>

<?php include('../../includes/footer.php'); ?>
