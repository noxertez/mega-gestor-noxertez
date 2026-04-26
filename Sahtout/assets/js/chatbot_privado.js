/**
 * chatbot_privado.js
 * Widget de chatbot privado para el panel de gestión de Noxertez.
 * Se carga solo cuando el usuario tiene sesión de admin.
 * Tiene comandos rápidos, tono técnico, sin botón WhatsApp.
 */
(function () {
    'use strict';

    const root    = document.querySelector('[data-chatbot-admin]') || document.body;
    const BASE    = root.dataset.base || document.querySelector('[data-base]')?.dataset.base || '/';
    const API_URL = BASE + 'api/chatbot_api.php';

    let isOpen    = false;
    let isSending = false;

    // Comandos rápidos del panel privado
    const COMANDOS = [
        { emoji: '📦', label: 'Pedidos activos',    msg: 'Dame un resumen de los pedidos pendientes' },
        { emoji: '⚠️', label: 'Stock bajo',          msg: '¿Qué artículos tienen stock bajo o en mínimos?' },
        { emoji: '💰', label: 'Ingresos del mes',    msg: '¿Cuánto hemos facturado este mes?' },
        { emoji: '🔄', label: 'En preparación',       msg: 'Muéstrame los pedidos en estado Preparando' },
        { emoji: '📬', label: 'Por empezar',         msg: 'Muéstrame los pedidos en estado Por empezar' },
        { emoji: '✅', label: 'Listos para envío',    msg: 'Muéstrame los pedidos en estado Listo para entrega' },
    ];

    // ============================================================
    // CONSTRUIR LA UI DEL WIDGET PRIVADO
    // ============================================================
    function buildWidget() {
        // Inyectar estilos específicos del widget privado
        injectStyles();

        // Botón flotante
        const toggleBtn = document.createElement('button');
        toggleBtn.id            = 'nox-admin-chat-toggle';
        toggleBtn.setAttribute('aria-label', 'Abrir asistente de gestión');
        toggleBtn.innerHTML = `
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/>
            </svg>
            <span class="nox-admin-chat-label">IA Gestor</span>`;

        // Ventana de chat
        const chatWindow = document.createElement('div');
        chatWindow.id = 'nox-admin-chat-window';
        chatWindow.setAttribute('role', 'dialog');
        chatWindow.setAttribute('aria-label', 'Asistente de Gestión Noxertez');

        // Construir chips de comandos rápidos
        const comandosHtml = COMANDOS.map(c =>
            `<button class="nox-admin-cmd" data-msg="${escapeAttr(c.msg)}">${c.emoji} ${c.label}</button>`
        ).join('');

        chatWindow.innerHTML = `
            <div id="nox-admin-chat-header">
                <div class="nox-admin-header-icon">⚙️</div>
                <div class="nox-admin-header-info">
                    <p class="nox-admin-header-name">IA Gestor Noxertez</p>
                    <p class="nox-admin-header-mode">Modo privado · Acceso completo</p>
                </div>
                <button id="nox-admin-chat-clear" title="Limpiar historial">🗑️</button>
                <button id="nox-admin-chat-close" aria-label="Cerrar asistente">✕</button>
            </div>

            <div id="nox-admin-chat-commands">
                <div class="nox-admin-cmds-label">Comandos rápidos</div>
                <div class="nox-admin-cmds-grid">${comandosHtml}</div>
            </div>

            <div id="nox-admin-chat-messages" role="log" aria-live="polite"></div>

            <div id="nox-admin-chat-input-area">
                <textarea
                    id="nox-admin-chat-input"
                    placeholder="Pregunta cualquier cosa sobre el gestor..."
                    rows="1"
                    maxlength="500"
                    aria-label="Escribe tu consulta"
                ></textarea>
                <button id="nox-admin-chat-send" aria-label="Enviar consulta">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>
        `;

        document.body.appendChild(toggleBtn);
        document.body.appendChild(chatWindow);

        // ——— EVENTOS ———
        toggleBtn.addEventListener('click', toggleChat);
        chatWindow.querySelector('#nox-admin-chat-close').addEventListener('click', closeChat);
        chatWindow.querySelector('#nox-admin-chat-clear').addEventListener('click', clearHistory);

        const input   = chatWindow.querySelector('#nox-admin-chat-input');
        const sendBtn = chatWindow.querySelector('#nox-admin-chat-send');

        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 80) + 'px';
        });

        // Comandos rápidos
        chatWindow.querySelectorAll('.nox-admin-cmd').forEach(btn => {
            btn.addEventListener('click', () => {
                const msg = btn.dataset.msg;
                if (msg) {
                    input.value = msg;
                    sendMessage();
                }
            });
        });
    }

    // ============================================================
    // ABRIR / CERRAR / LIMPIAR
    // ============================================================
    function toggleChat() {
        isOpen ? closeChat() : openChat();
    }

    function openChat() {
        isOpen = true;
        document.getElementById('nox-admin-chat-toggle')?.classList.add('is-open');
        document.getElementById('nox-admin-chat-window')?.classList.add('is-visible');

        const msgsDiv = document.getElementById('nox-admin-chat-messages');
        if (msgsDiv && msgsDiv.children.length === 0) {
            setTimeout(() => {
                appendBotMessage('👋 Hola. Soy tu asistente de gestión con acceso completo al gestor Noxertez. Puedo consultarte pedidos activos, alertas de stock, ingresos del mes, historial de clientes y más. Usa los comandos rápidos o escribe directamente.');
            }, 300);
        }

        setTimeout(() => {
            document.getElementById('nox-admin-chat-input')?.focus();
        }, 350);
    }

    function closeChat() {
        isOpen = false;
        document.getElementById('nox-admin-chat-toggle')?.classList.remove('is-open');
        document.getElementById('nox-admin-chat-window')?.classList.remove('is-visible');
    }

    function clearHistory() {
        const msgsDiv = document.getElementById('nox-admin-chat-messages');
        if (msgsDiv) msgsDiv.innerHTML = '';
        // Limpiar historial en servidor
        fetch(API_URL + '?accion=clear_history', { method: 'POST' }).catch(() => {});
        appendBotMessage('🗑️ Historial limpiado. ¿En qué puedo ayudarte?');
    }

    // ============================================================
    // ENVIAR MENSAJE
    // ============================================================
    function sendMessage() {
        if (isSending) return;

        const input = document.getElementById('nox-admin-chat-input');
        const msg   = input.value.trim();
        if (!msg) return;

        appendUserMessage(msg);
        input.value        = '';
        input.style.height = 'auto';

        isSending = true;
        const sendBtn = document.getElementById('nox-admin-chat-send');
        if (sendBtn) sendBtn.disabled = true;

        const typingId = showTyping();

        fetch(API_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: msg, modo: 'privado' }),
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            removeTyping(typingId);
            if (data.error) {
                appendBotMessage('⚠️ Error: ' + (data.error || 'Respuesta no válida'), true);
            } else {
                appendBotMessage(data.respuesta);
            }
        })
        .catch(err => {
            removeTyping(typingId);
            appendBotMessage('❌ Error de conexión con la API. Revisa que el servidor esté activo.', true);
        })
        .finally(() => {
            isSending = false;
            if (sendBtn) sendBtn.disabled = false;
            document.getElementById('nox-admin-chat-input')?.focus();
        });
    }

    // ============================================================
    // RENDERIZADO DE MENSAJES
    // ============================================================
    function appendUserMessage(text) {
        const msgsDiv = document.getElementById('nox-admin-chat-messages');
        const el      = document.createElement('div');
        el.className  = 'nox-admin-msg user';
        el.innerHTML  = `
            <div class="nox-admin-msg-avatar">👤</div>
            <div class="nox-admin-msg-bubble">${escapeHtml(text)}</div>
        `;
        msgsDiv.appendChild(el);
        scrollToBottom(msgsDiv);
    }

    function appendBotMessage(text, isError = false) {
        const msgsDiv = document.getElementById('nox-admin-chat-messages');
        const el      = document.createElement('div');
        el.className  = 'nox-admin-msg bot' + (isError ? ' is-error' : '');

        const hora    = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        el.innerHTML  = `
            <div class="nox-admin-msg-avatar">⚙️</div>
            <div class="nox-admin-msg-content">
                <div class="nox-admin-msg-bubble">${formatText(text)}</div>
                <div class="nox-admin-msg-time">${hora}</div>
            </div>
        `;
        msgsDiv.appendChild(el);
        scrollToBottom(msgsDiv);
    }

    function showTyping() {
        const msgsDiv = document.getElementById('nox-admin-chat-messages');
        const id      = 'nox-admin-typing-' + Date.now();
        const el      = document.createElement('div');
        el.className  = 'nox-admin-msg bot';
        el.id         = id;
        el.innerHTML  = `
            <div class="nox-admin-msg-avatar">⚙️</div>
            <div class="nox-admin-typing-dots">
                <span></span><span></span><span></span>
            </div>`;
        msgsDiv.appendChild(el);
        scrollToBottom(msgsDiv);
        return id;
    }

    function removeTyping(id) {
        document.getElementById(id)?.remove();
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function scrollToBottom(el) {
        requestAnimationFrame(() => { el.scrollTop = el.scrollHeight; });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttr(str) {
        return String(str).replace(/"/g, '&quot;');
    }

    function formatText(text) {
        let safe = escapeHtml(text);
        safe = safe.replace(/\n/g, '<br>');
        safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        safe = safe.replace(/`(.+?)`/g, '<code style="background:rgba(255,255,255,0.1);padding:2px 5px;border-radius:3px;font-size:0.85em;">$1</code>');
        return safe;
    }

    // ============================================================
    // ESTILOS DEL WIDGET PRIVADO (inyectados dinámicamente)
    // ============================================================
    function injectStyles() {
        if (document.getElementById('nox-admin-chat-styles')) return;
        const style = document.createElement('style');
        style.id    = 'nox-admin-chat-styles';
        style.textContent = `
            /* Widget privado — tema técnico/admin */
            #nox-admin-chat-toggle {
                position: fixed;
                bottom: 24px;
                left: 24px;
                display: flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #1e293b, #334155);
                border: 1px solid rgba(99,102,241,0.5);
                border-radius: 28px;
                padding: 10px 18px 10px 14px;
                color: #e2e8f0;
                cursor: pointer;
                z-index: 9990;
                font-size: 0.82rem;
                font-family: system-ui, sans-serif;
                font-weight: 600;
                letter-spacing: 0.03em;
                box-shadow: 0 4px 20px rgba(0,0,0,0.4);
                transition: all 0.3s ease;
                outline: none;
            }
            #nox-admin-chat-toggle:hover,
            #nox-admin-chat-toggle.is-open {
                background: linear-gradient(135deg, #2563eb, #4f46e5);
                border-color: rgba(99,102,241,0.8);
                box-shadow: 0 6px 24px rgba(79,70,229,0.4);
                transform: translateY(-2px);
            }
            #nox-admin-chat-toggle svg {
                width: 18px; height: 18px;
                fill: #818cf8;
                transition: fill 0.2s;
            }
            #nox-admin-chat-toggle:hover svg { fill: #fff; }
            .nox-admin-chat-label { line-height: 1; }

            #nox-admin-chat-window {
                position: fixed;
                bottom: 80px;
                left: 24px;
                width: 400px;
                max-height: 580px;
                background: #0f172a;
                border: 1px solid rgba(99,102,241,0.35);
                border-radius: 16px;
                box-shadow: 0 25px 60px rgba(0,0,0,0.7), 0 0 30px rgba(79,70,229,0.1);
                display: flex;
                flex-direction: column;
                z-index: 9991;
                overflow: hidden;
                transform: scale(0.9) translateY(20px);
                opacity: 0;
                pointer-events: none;
                transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            }
            #nox-admin-chat-window.is-visible {
                transform: scale(1) translateY(0);
                opacity: 1;
                pointer-events: all;
            }
            @media (max-width: 480px) {
                #nox-admin-chat-window {
                    bottom: 0; left: 0; right: 0;
                    width: 100%; max-height: 70vh;
                    border-radius: 16px 16px 0 0;
                }
                #nox-admin-chat-toggle { bottom: 16px; left: 16px; }
            }

            #nox-admin-chat-header {
                display: flex; align-items: center; gap: 10px;
                padding: 12px 16px;
                background: linear-gradient(135deg, #1e293b, #162032);
                border-bottom: 1px solid rgba(99,102,241,0.25);
                flex-shrink: 0;
            }
            .nox-admin-header-icon { font-size: 1.4rem; }
            .nox-admin-header-info { flex: 1; }
            .nox-admin-header-name {
                font-size: 0.85rem; font-weight: 700;
                color: #818cf8; margin: 0; letter-spacing: 0.03em;
                font-family: system-ui, sans-serif;
            }
            .nox-admin-header-mode {
                font-size: 0.7rem; color: #4ade80;
                margin: 2px 0 0; font-family: system-ui, sans-serif;
            }
            #nox-admin-chat-close,
            #nox-admin-chat-clear {
                background: none; border: none;
                color: #64748b; cursor: pointer;
                padding: 4px 6px; border-radius: 6px;
                font-size: 0.9rem; transition: all 0.2s;
            }
            #nox-admin-chat-close:hover { color: #f87171; background: rgba(248,113,113,0.1); }
            #nox-admin-chat-clear:hover { color: #fbbf24; background: rgba(251,191,36,0.1); }

            #nox-admin-chat-commands {
                padding: 10px 14px;
                border-bottom: 1px solid rgba(99,102,241,0.15);
                flex-shrink: 0;
                background: rgba(30,41,59,0.5);
            }
            .nox-admin-cmds-label {
                font-size: 0.68rem; color: #475569;
                text-transform: uppercase; letter-spacing: 0.08em;
                font-family: system-ui, sans-serif;
                margin-bottom: 8px;
            }
            .nox-admin-cmds-grid {
                display: flex; flex-wrap: wrap; gap: 5px;
            }
            .nox-admin-cmd {
                background: rgba(99,102,241,0.12);
                border: 1px solid rgba(99,102,241,0.3);
                color: #a5b4fc;
                border-radius: 6px; padding: 5px 10px;
                font-size: 0.73rem; font-family: system-ui, sans-serif;
                cursor: pointer; transition: all 0.2s ease;
                white-space: nowrap;
            }
            .nox-admin-cmd:hover {
                background: rgba(99,102,241,0.25);
                border-color: rgba(99,102,241,0.6);
                color: #c7d2fe;
                transform: translateY(-1px);
            }

            #nox-admin-chat-messages {
                flex: 1; overflow-y: auto;
                padding: 14px 12px;
                display: flex; flex-direction: column; gap: 12px;
                scroll-behavior: smooth;
            }
            #nox-admin-chat-messages::-webkit-scrollbar { width: 4px; }
            #nox-admin-chat-messages::-webkit-scrollbar-track { background: transparent; }
            #nox-admin-chat-messages::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.3); border-radius: 2px; }

            .nox-admin-msg {
                display: flex; align-items: flex-start;
                gap: 8px; animation: nox-admin-fade 0.3s ease;
            }
            .nox-admin-msg.user { flex-direction: row-reverse; }
            @keyframes nox-admin-fade {
                from { opacity: 0; transform: translateY(8px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .nox-admin-msg-avatar {
                width: 28px; height: 28px;
                border-radius: 6px; display: flex;
                align-items: center; justify-content: center;
                font-size: 0.85rem; flex-shrink: 0;
                background: rgba(99,102,241,0.15);
                border: 1px solid rgba(99,102,241,0.2);
            }
            .nox-admin-msg.user .nox-admin-msg-avatar {
                background: rgba(30,58,95,0.6);
                border-color: rgba(59,130,246,0.2);
            }
            .nox-admin-msg-content { display: flex; flex-direction: column; gap: 3px; max-width: 82%; }
            .nox-admin-msg-bubble {
                padding: 9px 13px;
                border-radius: 12px;
                font-family: system-ui, sans-serif;
                font-size: 0.85rem; line-height: 1.55;
                word-break: break-word;
            }
            .nox-admin-msg.bot .nox-admin-msg-bubble {
                background: #1e293b;
                border: 1px solid rgba(99,102,241,0.2);
                color: #cbd5e1;
                border-top-left-radius: 3px;
            }
            .nox-admin-msg.user .nox-admin-msg-bubble {
                background: rgba(30,58,95,0.7);
                border: 1px solid rgba(59,130,246,0.2);
                color: #bfdbfe;
                border-top-right-radius: 3px;
            }
            .nox-admin-msg.is-error .nox-admin-msg-bubble {
                background: rgba(127,29,29,0.3);
                border-color: rgba(239,68,68,0.3);
                color: #fca5a5;
            }
            .nox-admin-msg-bubble strong { color: #818cf8; }
            .nox-admin-msg-time {
                font-size: 0.65rem; color: #475569;
                font-family: system-ui, sans-serif;
                padding-left: 4px;
            }
            .nox-admin-typing-dots {
                background: #1e293b; border: 1px solid rgba(99,102,241,0.2);
                border-radius: 12px; border-top-left-radius: 3px;
                padding: 10px 14px; display: flex; gap: 5px; align-items: center;
            }
            .nox-admin-typing-dots span {
                width: 6px; height: 6px;
                background: #818cf8; border-radius: 50%;
                animation: nox-dot-bounce 1.2s infinite ease-in-out;
            }
            .nox-admin-typing-dots span:nth-child(1) { animation-delay: 0s; }
            .nox-admin-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
            .nox-admin-typing-dots span:nth-child(3) { animation-delay: 0.4s; }

            #nox-admin-chat-input-area {
                display: flex; align-items: flex-end; gap: 8px;
                padding: 10px 12px;
                background: #1e293b;
                border-top: 1px solid rgba(99,102,241,0.2);
                flex-shrink: 0;
            }
            #nox-admin-chat-input {
                flex: 1;
                background: rgba(255,255,255,0.05);
                border: 1px solid rgba(99,102,241,0.25);
                border-radius: 10px;
                color: #e2e8f0;
                font-family: system-ui, sans-serif;
                font-size: 0.85rem;
                padding: 8px 14px;
                resize: none; outline: none;
                max-height: 80px; line-height: 1.4;
                transition: border-color 0.2s;
            }
            #nox-admin-chat-input::placeholder { color: #475569; }
            #nox-admin-chat-input:focus { border-color: rgba(99,102,241,0.6); }
            #nox-admin-chat-send {
                width: 38px; height: 38px;
                background: linear-gradient(135deg, #4f46e5, #2563eb);
                border: none; border-radius: 10px;
                cursor: pointer; display: flex;
                align-items: center; justify-content: center;
                flex-shrink: 0; transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(79,70,229,0.35);
            }
            #nox-admin-chat-send:hover {
                transform: scale(1.07);
                box-shadow: 0 4px 14px rgba(79,70,229,0.5);
            }
            #nox-admin-chat-send:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
            #nox-admin-chat-send svg { width: 16px; height: 16px; fill: #fff; }
        `;
        document.head.appendChild(style);
    }

    // ============================================================
    // INICIALIZAR
    // ============================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildWidget);
    } else {
        buildWidget();
    }

})();
