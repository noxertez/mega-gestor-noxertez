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
/* ─── Widget Voz Flotante ──────────────────────────────────────────── */
#voz-widget-flotante {
    position: fixed;
    bottom: 90px;
    right: 22px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    font-family: 'Inter', 'Outfit', sans-serif;
}

#voz-burbuja {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #1a1a2e;
    border: 2px solid #c9a84c;
    color: #c9a84c;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    user-select: none;
}
#voz-burbuja:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 28px rgba(201,168,76,0.35);
}
#voz-burbuja.widget-escuchando {
    border-color: #ef4444;
    color: #ef4444;
    animation: widgetPulse 1.2s ease-in-out infinite;
}
@keyframes widgetPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
    50%       { box-shadow: 0 0 0 10px rgba(239,68,68,0); }
}

#voz-panel {
    background: rgba(15,15,30,0.92);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(201,168,76,0.35);
    border-radius: 14px;
    padding: 14px 16px;
    max-width: 260px;
    min-width: 200px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    position: relative;
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
#voz-status {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.5;
    color: #fff;
    margin-bottom: 6px;
}
#voz-transcript {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.6);
    min-height: 1.1em;
    font-style: italic;
    margin-bottom: 8px;
}
#voz-respuesta {
    font-size: 0.95rem;
    color: #c9a84c;
    font-weight: 600;
    line-height: 1.4;
}
</style>

<div id="voz-widget-flotante">
    <div id="voz-panel" style="display:none">
        <div id="voz-status">Pulsa para hablar</div>
        <div id="voz-transcript"></div>
        <div id="voz-respuesta">¿En qué te ayudo?</div>
    </div>
    <div id="voz-burbuja" onclick="widgetToggleVoice()" title="Asistente de voz">
        <i class="fas fa-microphone"></i>
    </div>
</div>

<script>
(function() {
    // ─── Prefixing: no conflictos con asistente_voz.php ─────────────────────
    if (window.location.pathname.includes('asistente_voz')) return;

    let widgetRec = null;
    let widgetListening = false;
    let widgetSilenceTimer = null;
    let widgetCollapseTimer = null;
    let widgetVoices = [];

    // Cargar voces disponibles
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
        utt.rate = 1;
        utt.pitch = 1;
        const voz = widgetVoices.find(v => v.lang.startsWith('es') && v.name.toLowerCase().includes('fem'))
                 || widgetVoices.find(v => v.lang.startsWith('es'))
                 || null;
        if (voz) utt.voice = voz;
        synth.cancel();
        synth.speak(utt);
    }

    function widgetSetStatus(txt) {
        document.getElementById('voz-status').innerText = txt;
    }
    function widgetSetTranscript(txt) {
        document.getElementById('voz-transcript').innerText = txt;
    }
    function widgetSetRespuesta(txt) {
        document.getElementById('voz-respuesta').innerText = txt;
    }

    // Inicializar reconocimiento
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        widgetRec = new SR();
        widgetRec.lang = 'es-ES';
        widgetRec.continuous = true;
        widgetRec.interimResults = true;

        widgetRec.onstart = () => {
            widgetListening = true;
            document.getElementById('voz-burbuja').classList.add('widget-escuchando');
            widgetSetStatus('Escuchando...');
            widgetResetSilence();
        };

        widgetRec.onresult = (event) => {
            let final = '', interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) final += event.results[i][0].transcript;
                else interim += event.results[i][0].transcript;
            }
            widgetSetTranscript(final || interim);
            if (final || interim) widgetResetSilence();
        };

        widgetRec.onend = () => {
            widgetListening = false;
            document.getElementById('voz-burbuja').classList.remove('widget-escuchando');
            widgetSetStatus('Procesando...');
            clearTimeout(widgetSilenceTimer);
            const texto = document.getElementById('voz-transcript').innerText.trim();
            if (texto.length > 2) widgetProcesar(texto);
            else widgetColapsar();
        };

        widgetRec.onerror = () => {
            widgetListening = false;
            document.getElementById('voz-burbuja').classList.remove('widget-escuchando');
            widgetSetStatus('Error de micrófono');
        };
    }

    function widgetResetSilence() {
        clearTimeout(widgetSilenceTimer);
        widgetSilenceTimer = setTimeout(() => {
            if (widgetListening && widgetRec) widgetRec.stop();
        }, 4000);
    }

    window.widgetToggleVoice = function() {
        const panel = document.getElementById('voz-panel');

        if (widgetListening) {
            if (widgetRec) widgetRec.stop();
            widgetColapsar();
            return;
        }

        // Abrir panel
        panel.style.display = 'block';
        clearTimeout(widgetCollapseTimer);
        widgetSetTranscript('');
        widgetSetRespuesta('¿En qué te ayudo?');
        widgetSetStatus('Pulsa para hablar');

        if (!widgetRec) {
            widgetSetStatus('Micrófono no disponible');
            return;
        }
        try { widgetRec.start(); } catch(e) {}
    };

    function widgetColapsar() {
        clearTimeout(widgetCollapseTimer);
        widgetCollapseTimer = setTimeout(() => {
            document.getElementById('voz-panel').style.display = 'none';
        }, 6000);
    }

    async function widgetProcesar(texto) {
        widgetSetStatus('Consultando asistente...');
        try {
            const res = await fetch('http://localhost:5678/webhook/asistente', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ texto })
            });
            const data = await res.json();
            widgetSetTranscript('');
            widgetSetRespuesta(data.respuesta || '—');
            widgetSetStatus('Asistente');
            widgetHablar(data.respuesta);
        } catch(e) {
            widgetSetRespuesta('Error conectando con el asistente.');
            widgetSetStatus('Error');
        }
        widgetColapsar();
    }
})();
</script>
<?php endif; ?>
