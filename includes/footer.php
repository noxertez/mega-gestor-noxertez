<?php
require_once __DIR__ . '/paths.php';

if (!defined('ALLOWED_ACCESS')) {
    if (file_exists($project_root . 'languages/language.php')) {
        require_once $project_root . 'languages/language.php';
    }
    header('HTTP/1.1 403 Forbidden');
    exit(function_exists('translate') ? translate('error_direct_access', 'Direct access to this file is not allowed.') : 'Direct access to this file is not allowed.');
}

// Load settings (if exists)
if (file_exists($project_root . 'includes/config.settings.php')) {
    require_once $project_root . 'includes/config.settings.php';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($base_path); ?>assets/css/footer.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<footer>
  <div class="footer-container">
    <!-- Logo -->
    <div class="footer-logo">
      <a href="<?php echo htmlspecialchars($base_path); ?>">
        <img src="<?php echo htmlspecialchars($base_path . ltrim($site_logo ?? 'img/logo.png', '/')); ?>"
             alt="<?php echo htmlspecialchars(translate('footer_logo_alt', 'Sahtout Server Logo')); ?>"
             class="footer-logo-img">
      </a>
    </div>

    <!-- Copyright -->
    <div class="footer-center">
      <p>© <?php echo date('Y') ." ". $site_title_name ;?>  by SahtoutCMS. All rights reserved.</p>
    </div>

    <!-- Socials -->
    <div class="footer-socials">
      <?php if (!empty($social_links['facebook'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['facebook']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('facebook_alt', 'Facebook')); ?>">
          <i class="fab fa-facebook-f"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['twitter'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['twitter']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('twitter_alt', 'Twitter (X)')); ?>">
          <i class="fab fa-x-twitter"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['tiktok'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['tiktok']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('tiktok_alt', 'TikTok')); ?>">
          <i class="fab fa-tiktok"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['youtube'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['youtube']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('youtube_alt', 'YouTube')); ?>">
          <i class="fab fa-youtube"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['discord'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['discord']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('discord_alt', 'Discord')); ?>">
          <i class="fab fa-discord"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['twitch'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['twitch']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('twitch_alt', 'Twitch')); ?>">
          <i class="fab fa-twitch"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['kick'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['kick']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('kick_alt', 'Kick')); ?>">
          <img src="<?php echo htmlspecialchars($base_path . 'img/icons/kick-logo.png'); ?>"
               alt="<?php echo htmlspecialchars(translate('kick_alt', 'Kick')); ?>"
               class="kick-icon">
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['instagram'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['instagram']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('instagram_alt', 'Instagram')); ?>">
          <i class="fab fa-instagram"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['whatsapp'])): ?>
        <a href="https://wa.me/<?php echo htmlspecialchars(str_replace(' ', '', $social_links['whatsapp'])); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('whatsapp_alt', 'WhatsApp')); ?>">
          <i class="fab fa-whatsapp"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['trendioff'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['trendioff']); ?>" target="_blank" aria-label="Trendioff">
          <i class="fas fa-shopping-cart"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['etsy'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['etsy']); ?>" target="_blank" aria-label="Etsy">
          <i class="fab fa-etsy"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['github'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['github']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('github_alt', 'GitHub')); ?>">
          <i class="fab fa-github"></i>
        </a>
      <?php endif; ?>

      <?php if (!empty($social_links['linkedin'])): ?>
        <a href="<?php echo htmlspecialchars($social_links['linkedin']); ?>" target="_blank" aria-label="<?php echo htmlspecialchars(translate('linkedin_alt', 'LinkedIn')); ?>">
          <i class="fab fa-linkedin-in"></i>
        </a>
      <?php endif; ?>
    </div>

    <!-- Legal Links -->
    <div class="footer-legal">
      <a href="<?php echo htmlspecialchars($base_path); ?>pages/aviso-legal.php">Aviso Legal</a>
      <span>|</span>
      <a href="<?php echo htmlspecialchars($base_path); ?>pages/politica-privacidad.php">Política de Privacidad</a>
      <span>|</span>
      <a href="<?php echo htmlspecialchars($base_path); ?>pages/politica-cookies.php">Política de Cookies</a>
      <span>|</span>
      <a href="<?php echo htmlspecialchars($base_path); ?>pages/terminos-condiciones.php">Términos y Condiciones</a>
    </div>
  </div>

  <!-- Back to Top Button -->
  <button id="backToTop" title="<?php echo htmlspecialchars(translate('back_to_top', 'Back to Top')); ?>">
    <i class="fas fa-arrow-up"></i>
  </button>
</footer>

<!-- Back to Top Script -->
 <script src="<?php echo $base_path; ?>assets/js/includes/footer.js"></script>

<?php
// ============================================================
// CHATBOT PÚBLICO — Solo para visitantes no admin
// ============================================================
$chatbot_mostrar = false;
if (defined('ALLOWED_ACCESS')) {
    // Verificar si el chatbot está activo y si el usuario no es admin
    $es_admin_footer = false;
    if (!empty($_SESSION['user_id'])) {
        try {
            // Reusar conexión site_db si está disponible
            if (isset($site_db)) {
                $stmt_role_f = $site_db->prepare("SELECT role FROM user_currencies WHERE account_id = ?");
                $stmt_role_f->bind_param('i', $_SESSION['user_id']);
                $stmt_role_f->execute();
                $result_role_f = $stmt_role_f->get_result();
                if ($result_role_f->num_rows > 0) {
                    $role_f = $result_role_f->fetch_assoc()['role'];
                    $es_admin_footer = in_array($role_f, ['admin', 'moderator']);
                }
                $stmt_role_f->close();
            }
        } catch (Exception $e) { /* silencioso */ }
    }

    if (!$es_admin_footer) {
        // Verificar toggle en DB
        try {
            if (isset($site_db)) {
                $stmt_cb = $site_db->prepare("SELECT valor FROM chatbot_config WHERE clave = 'chatbot_activo' LIMIT 1");
                $stmt_cb->execute();
                $result_cb = $stmt_cb->get_result();
                if ($result_cb->num_rows > 0) {
                    $chatbot_mostrar = $result_cb->fetch_assoc()['valor'] === '1';
                }
                $stmt_cb->close();
            }
        } catch (Exception $e) {
            $chatbot_mostrar = false; // Si falla la tabla, no mostrar
        }
    }
}
?>

<?php if ($chatbot_mostrar): ?>
<!-- Chatbot Asistente Noxertez (Público) -->
<div id="nox-chatbot-root" data-base="<?php echo htmlspecialchars($base_path); ?>"></div>
<script src="<?php echo htmlspecialchars($base_path); ?>assets/js/chatbot_widget.js"></script>
<?php endif; ?>

<?php
// ============================================================
// WIDGET FLOTANTE DE VOZ — Solo para admins
// ============================================================
$mostrar_widget_voz = false;
if (defined('ALLOWED_ACCESS') && !empty($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $mostrar_widget_voz = in_array($_SESSION['role'], ['admin', 'moderator']);
}
?>
<?php if ($mostrar_widget_voz): ?>
<style>
/* ─── Widget Voz Flotante (Dual Mode) ──────────────────────────────── */
#voz-widget-flotante {
    position: fixed;
    bottom: 90px;
    right: 22px;
    z-index: 9999;
    display: flex;
    flex-direction: column-reverse; /* Apila hacia arriba */
    align-items: flex-end;
    gap: 12px;
    font-family: 'Inter', 'Outfit', sans-serif;
}

.voz-burbuja-base {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    font-size: 20px;
    border: 2px solid;
}

#voz-burbuja-general {
    background: #1a1a2e;
    border-color: #c9a84c;
    color: #c9a84c;
}

#voz-burbuja-corazones {
    background: #2e1a1a;
    border-color: #ef4444;
    color: #ef4444;
}

.voz-burbuja-base:hover { transform: scale(1.1); }
.voz-burbuja-base.widget-escuchando { animation: widgetPulse 1.2s infinite; }

@keyframes widgetPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(201,168,76,0.5); }
    50%       { box-shadow: 0 0 0 10px rgba(201,168,76,0); }
}

#voz-panel {
    background: rgba(15,15,30,0.95);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(201,168,76,0.35);
    border-radius: 14px;
    padding: 14px 16px;
    max-width: 260px;
    min-width: 200px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    position: relative;
    margin-bottom: 10px;
}
#voz-panel::after {
    content: '';
    position: absolute;
    bottom: -8px;
    right: 18px;
    width: 14px;
    height: 14px;
    background: rgba(15,15,30,0.92);
    border-right: 1px solid rgba(201,168,76,0.35);
    border-bottom: 1px solid rgba(201,168,76,0.35);
    transform: rotate(45deg);
}
#voz-status { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #fff; opacity: 0.6; margin-bottom: 4px; }
#voz-transcript { font-size: 0.78rem; color: rgba(255,255,255,0.6); font-style: italic; margin-bottom: 8px; }
#voz-respuesta { font-size: 0.95rem; color: #c9a84c; font-weight: 600; line-height: 1.4; }
.modo-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; margin-bottom: 8px; }
.badge-general { background: #c9a84c; color: #000; }
.badge-corazones { background: #ef4444; color: #fff; }
</style>

<div id="voz-widget-flotante">
    <div id="voz-panel" style="display:none">
        <div id="voz-modo-display" class="modo-badge">MODO</div>
        <div id="voz-status">Pulsa para hablar</div>
        <div id="voz-transcript"></div>
        <div id="voz-respuesta">¿En qué te ayudo?</div>
    </div>
    
    <!-- Burbuja GENERAL (MODO 05) -->
    <div id="voz-burbuja-general" class="voz-burbuja-base" onclick="widgetToggleVoice('general')" title="Asistente General (05)">
        <i class="fas fa-brain"></i>
    </div>

    <!-- Burbuja CORAZONES (MODO PRO) -->
    <div id="voz-burbuja-corazones" class="voz-burbuja-base" onclick="widgetToggleVoice('corazones')" title="Buscador de Corazones (PRO)">
        <i class="fas fa-heart"></i>
    </div>
</div>

<script>
(function() {
    if (window.location.pathname.includes('asistente_voz')) return;

    let widgetRec = null;
    let widgetListening = false;
    let widgetModoActual = 'general';
    let widgetSilenceTimer = null;
    let widgetCollapseTimer = null;
    let widgetVoices = [];

    function widgetCargarVoces() {
        widgetVoices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
    }
    if (window.speechSynthesis) {
        widgetCargarVoces();
        window.speechSynthesis.onvoiceschanged = widgetCargarVoces;
    }

    function widgetHablar(texto) {
        if (!window.speechSynthesis || !texto) return;
        const synth = window.speechSynthesis;
        const utt = new SpeechSynthesisUtterance(texto);
        utt.lang = 'es-ES';
        const voz = widgetVoices.find(v => v.lang.startsWith('es') && v.name.toLowerCase().includes('fem')) || widgetVoices.find(v => v.lang.startsWith('es'));
        if (voz) utt.voice = voz;
        synth.cancel();
        synth.speak(utt);
    }

    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        widgetRec = new SR();
        widgetRec.lang = 'es-ES';
        widgetRec.continuous = true;
        widgetRec.interimResults = true;

        widgetRec.onstart = () => {
            widgetListening = true;
            document.querySelectorAll('.voz-burbuja-base').forEach(b => b.classList.remove('widget-escuchando'));
            const idBurbuja = widgetModoActual === 'general' ? 'voz-burbuja-general' : 'voz-burbuja-corazones';
            document.getElementById(idBurbuja).classList.add('widget-escuchando');
            document.getElementById('voz-status').innerText = 'Escuchando...';
        };

        widgetRec.onresult = (event) => {
            let final = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) final += event.results[i][0].transcript;
            }
            if (final) {
                document.getElementById('voz-transcript').innerText = final;
                widgetResetSilence();
            }
        };

        widgetRec.onend = () => {
            widgetListening = false;
            document.querySelectorAll('.voz-burbuja-base').forEach(b => b.classList.remove('widget-escuchando'));
            const texto = document.getElementById('voz-transcript').innerText.trim();
            if (texto.length > 2) widgetProcesar(texto);
            else widgetColapsar();
        };
    }

    function widgetResetSilence() {
        clearTimeout(widgetSilenceTimer);
        widgetSilenceTimer = setTimeout(() => { if (widgetListening) widgetRec.stop(); }, 3000);
    }

    window.widgetToggleVoice = function(modo) {
        widgetModoActual = modo;
        const panel = document.getElementById('voz-panel');
        const badge = document.getElementById('voz-modo-display');
        
        badge.innerText = modo.toUpperCase();
        badge.className = 'modo-badge ' + (modo === 'general' ? 'badge-general' : 'badge-corazones');
        
        panel.style.display = 'block';
        document.getElementById('voz-transcript').innerText = '';
        document.getElementById('voz-respuesta').innerText = '¿En qué te ayudo?';
        
        if (widgetRec) widgetRec.start();
    };

    function widgetColapsar() {
        widgetCollapseTimer = setTimeout(() => { document.getElementById('voz-panel').style.display = 'none'; }, 5000);
    }

    const API_BASE = '<?php echo rtrim($base_path, "/"); ?>';

    async function widgetProcesar(texto) {
        document.getElementById('voz-status').innerText = 'Procesando...';
        document.getElementById('voz-respuesta').innerText = '...';
        try {
            const res = await fetch(API_BASE + '/api/asistente_voz_n8n.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ texto, modo: widgetModoActual })
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            const msg = data.respuesta || 'Sin respuesta del servidor.';
            document.getElementById('voz-respuesta').innerText = msg;
            widgetHablar(msg);
        } catch(e) {
            const err = 'Error de conexión: ' + e.message;
            document.getElementById('voz-respuesta').innerText = err;
            console.error('[Asistente]', e);
        }
        widgetColapsar();
    }
})();
</script>
<?php endif; ?>
