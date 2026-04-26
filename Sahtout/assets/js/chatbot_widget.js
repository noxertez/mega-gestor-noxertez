/**
 * chatbot_widget.js
 * Widget de chatbot público para noxertez.com
 * Se inyecta en el footer de todas las páginas públicas.
 */
(function () {
    'use strict';

    // Configuración global desde el atributo data-base del contenedor
    const root    = document.getElementById('nox-chatbot-root');
    const BASE    = (root && root.dataset.base) ? root.dataset.base : '/';
    const API_URL = BASE + 'api/chatbot_api.php';

    let isOpen    = false;
    let isSending = false;

    // ============================================================
    // CONSTRUIR LA UI DEL WIDGET
    // ============================================================
    function buildWidget() {
        // Botón flotante
        const toggleBtn = document.createElement('button');
        toggleBtn.id            = 'nox-chat-toggle';
        toggleBtn.setAttribute('aria-label', 'Abrir chat de asistencia');
        toggleBtn.innerHTML = `
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
            </svg>`;

        // Ventana de chat
        const chatWindow = document.createElement('div');
        chatWindow.id = 'nox-chatbot-window';
        chatWindow.setAttribute('role', 'dialog');
        chatWindow.setAttribute('aria-label', 'Chat Asistente Noxertez');
        chatWindow.innerHTML = `
            <div id="nox-chat-header">
                <div class="nox-chat-avatar">🪵</div>
                <div class="nox-chat-header-info">
                    <p class="nox-chat-header-name">Asistente Noxertez</p>
                    <p class="nox-chat-header-status">En línea</p>
                </div>
                <button id="nox-chat-close" aria-label="Cerrar chat">✕</button>
            </div>
            <div id="nox-chat-messages" role="log" aria-live="polite"></div>
            <div class="nox-suggestions" id="nox-suggestions">
                <button class="nox-suggestion-btn" data-msg="¿Qué productos tenéis?">🛒 Ver catálogo</button>
                <button class="nox-suggestion-btn" data-msg="¿Cuánto tarda el envío?">📦 Tiempo de envío</button>
                <button class="nox-suggestion-btn" data-msg="¿A dónde enviáis?">🗺️ Zonas de envío</button>
                <button class="nox-suggestion-btn" data-msg="¿Cuánto cuesta el envío?">💶 Precio envío</button>
            </div>
            <div id="nox-chat-input-area">
                <textarea
                    id="nox-chat-input"
                    placeholder="Escribe tu pregunta..."
                    rows="1"
                    maxlength="500"
                    aria-label="Escribe tu mensaje"
                ></textarea>
                <button id="nox-chat-send" aria-label="Enviar mensaje">
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
        chatWindow.querySelector('#nox-chat-close').addEventListener('click', closeChat);

        const input   = chatWindow.querySelector('#nox-chat-input');
        const sendBtn = chatWindow.querySelector('#nox-chat-send');

        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        // Auto-resize del textarea
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 80) + 'px';
        });

        // Sugerencias rápidas
        chatWindow.querySelectorAll('.nox-suggestion-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const msg = btn.dataset.msg;
                if (msg) {
                    input.value = msg;
                    sendMessage();
                }
            });
        });

        // Cerrar al hacer clic fuera (solo en desktop)
        document.addEventListener('click', (e) => {
            if (isOpen && !chatWindow.contains(e.target) && e.target !== toggleBtn) {
                closeChat();
            }
        });
    }

    // ============================================================
    // ABRIR / CERRAR
    // ============================================================
    function toggleChat() {
        isOpen ? closeChat() : openChat();
    }

    function openChat() {
        isOpen = true;
        const toggleBtn  = document.getElementById('nox-chat-toggle');
        const chatWindow = document.getElementById('nox-chatbot-window');

        toggleBtn.classList.add('is-open');
        chatWindow.classList.add('is-visible');

        // Mostrar saludo inicial si no hay mensajes
        const msgsDiv = document.getElementById('nox-chat-messages');
        if (msgsDiv.children.length === 0) {
            setTimeout(() => {
                appendBotMessage('¡Hola! 👋 Soy el Asistente Noxertez. Puedo ayudarte con información sobre nuestros productos artesanales de madera, stock, precios y envíos. ¿En qué te puedo ayudar hoy?');
            }, 400);
        }

        // Focus en el input
        setTimeout(() => {
            const input = document.getElementById('nox-chat-input');
            if (input) input.focus();
        }, 350);
    }

    function closeChat() {
        isOpen = false;
        document.getElementById('nox-chat-toggle')?.classList.remove('is-open');
        document.getElementById('nox-chatbot-window')?.classList.remove('is-visible');
    }

    // ============================================================
    // ENVIAR MENSAJE
    // ============================================================
    function sendMessage() {
        if (isSending) return;

        const input = document.getElementById('nox-chat-input');
        const msg   = input.value.trim();
        if (!msg) return;

        // Ocultar sugerencias después del primer mensaje
        const suggestionsDiv = document.getElementById('nox-suggestions');
        if (suggestionsDiv) suggestionsDiv.style.display = 'none';

        // Mostrar mensaje del usuario
        appendUserMessage(msg);
        input.value      = '';
        input.style.height = 'auto';

        // Deshabilitar envío
        isSending = true;
        const sendBtn = document.getElementById('nox-chat-send');
        if (sendBtn) sendBtn.disabled = true;

        // Indicador de carga
        const typingId = showTyping();

        // Llamada a la API
        fetch(API_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: msg }),
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            removeTyping(typingId);
            if (data.error) {
                appendBotMessage('Lo siento, ha ocurrido un error. Por favor, inténtalo de nuevo.', false, null, true);
            } else {
                appendBotMessage(data.respuesta, true, data.whatsapp_url);
            }
        })
        .catch(() => {
            removeTyping(typingId);
            appendBotMessage(
                'Vaya, no he podido conectar con el servidor. Pero puedes contactarnos directamente por WhatsApp. 💬',
                true,
                'https://wa.me/34693326269?text=Hola%2C%20necesito%20información%20sobre%20vuestros%20productos'
            );
        })
        .finally(() => {
            isSending = false;
            if (sendBtn) sendBtn.disabled = false;
            const inputEl = document.getElementById('nox-chat-input');
            if (inputEl) inputEl.focus();
        });
    }

    // ============================================================
    // RENDERIZADO DE MENSAJES
    // ============================================================
    function appendUserMessage(text) {
        const msgsDiv = document.getElementById('nox-chat-messages');
        const msgEl   = document.createElement('div');
        msgEl.className = 'nox-msg user';
        msgEl.innerHTML = `
            <div class="nox-msg-avatar">👤</div>
            <div class="nox-msg-bubble">${escapeHtml(text)}</div>
        `;
        msgsDiv.appendChild(msgEl);
        scrollToBottom(msgsDiv);
    }

    function appendBotMessage(text, showWhatsApp = false, waUrl = null, isError = false) {
        const msgsDiv   = document.getElementById('nox-chat-messages');
        const msgEl     = document.createElement('div');
        msgEl.className = 'nox-msg bot' + (isError ? ' nox-msg-error' : '');

        // Formatear texto: convertir saltos de línea y negrita básica
        const formattedText = formatBotText(text);

        let waHtml = '';
        if (showWhatsApp && waUrl) {
            waHtml = `
                <br>
                <a href="${escapeAttr(waUrl)}" target="_blank" rel="noopener noreferrer" class="nox-wa-btn">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M11.5 2C6.253 2 2 6.253 2 11.5c0 1.85.507 3.58 1.385 5.06L2 22l5.538-1.366A9.456 9.456 0 0011.5 21c5.247 0 9.5-4.253 9.5-9.5S16.747 2 11.5 2zm0 17.25A7.74 7.74 0 017.3 17.8L4.2 18.65l.875-3.03A7.745 7.745 0 013.75 11.5c0-4.273 3.477-7.75 7.75-7.75s7.75 3.477 7.75 7.75-3.477 7.75-7.75 7.75z"/>
                    </svg>
                    Contactar por WhatsApp
                </a>`;
        }

        msgEl.innerHTML = `
            <div class="nox-msg-avatar">🪵</div>
            <div class="nox-msg-bubble">${formattedText}${waHtml}</div>
        `;
        msgsDiv.appendChild(msgEl);
        scrollToBottom(msgsDiv);
    }

    function showTyping() {
        const msgsDiv = document.getElementById('nox-chat-messages');
        const id      = 'nox-typing-' + Date.now();
        const el      = document.createElement('div');
        el.className  = 'nox-typing';
        el.id         = id;
        el.innerHTML  = `
            <div class="nox-msg-avatar">🪵</div>
            <div class="nox-typing-dots">
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
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttr(str) {
        return str.replace(/"/g, '&quot;');
    }

    function formatBotText(text) {
        if (!text) return '';
        
        // 1. Escapar HTML básico para seguridad
        let safe = escapeHtml(text);
        
        // 2. Convertir imágenes Markdown: ![alt](url)
        // Limpiamos posibles residuos de escape de HTML en la URL (&lt;, &gt;, &amp;)
        safe = safe.replace(/!\[(.*?)\]\s*\((.*?)\)/g, (match, alt, url) => {
            const cleanUrl = url.replace(/&lt;/g, '').replace(/&gt;/g, '').replace(/&amp;/g, '&').trim();
            return `<div class="nox-chat-img-container">
                        <img src="${cleanUrl}" alt="${alt}" class="nox-chat-img" onclick="window.open('${cleanUrl}', '_blank')">
                    </div>`;
        });

        // 3. Convertir URLs restantes en botones "Ver en web"
        // Buscamos URLs que no sean parte de un atributo src de imagen
        const urlRegex = /(?<!src=")(https?:\/\/[^\s<"']+)/g;
        safe = safe.replace(urlRegex, (match) => {
            let cleanUrl = match.replace(/&gt;$/, '').replace(/&lt;$/, '').replace(/&amp;/g, '&').trim();
            // Quitar puntuación final accidental
            cleanUrl = cleanUrl.replace(/[.,;]$/, '');
            
            return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" class="nox-chat-link">
                        <i class="fas fa-shopping-cart"></i> Ver en web
                    </a>`;
        });

        // 4. Saltos de línea
        safe = safe.replace(/\n/g, '<br>');
        
        // 5. Negrita
        safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        return safe;
    }

    // ============================================================
    // INICIALIZAR cuando el DOM esté listo
    // ============================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildWidget);
    } else {
        buildWidget();
    }

})();
