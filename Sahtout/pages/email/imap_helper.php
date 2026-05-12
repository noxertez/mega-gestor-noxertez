<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso directo no permitido.');
}

// ============================================================
// CREDENCIALES IMAP — leídas del config central
// ============================================================
define('IMAP_HOST',  '{imap.gmail.com:993/imap/ssl}');
define('IMAP_USER',  'noxertez@gmail.com');
define('IMAP_PASS',  'gfpwdyrsqlczbrxt'); // App Password de Gmail

// Aliases válidos del sistema
define('ALIASES_VALIDOS', [
    'info'        => 'info@noxertez.com',
    'pedidos'     => 'pedidos@noxertez.com',
    'influencers' => 'influencers@noxertez.com',
    'ayuda'       => 'ayuda@noxertez.com',
]);

/**
 * Abre conexión IMAP a Gmail.
 * @return resource|false
 */
function conectar_imap() {
    if (!function_exists('imap_open')) {
        return false;
    }
    // Suprimir warnings de IMAP y capturar con try/catch lógico
    $errores_anteriores = imap_errors();
    $conn = @imap_open(IMAP_HOST . 'INBOX', IMAP_USER, IMAP_PASS, 0, 1);
    return $conn;
}

/**
 * Cierra la conexión IMAP de forma segura.
 */
function cerrar_imap($conn): void {
    if ($conn) {
        @imap_close($conn, CL_EXPUNGE);
    }
}

/**
 * Obtiene los últimos $limit emails filtrados por alias (campo TO).
 * @return array
 */
function obtener_emails_alias($conn, string $alias, int $limit = 30): array {
    if (!$conn) return [];

    $aliases = ALIASES_VALIDOS;
    $email_alias = $aliases[$alias] ?? null;
    if (!$email_alias) return [];

    $criterio = 'TO "' . $email_alias . '"';
    $uids = @imap_search($conn, $criterio, SE_UID);

    if (!$uids) {
        // Fallback: intentar búsqueda sin filtro y filtrar manualmente
        return [];
    }

    // Los más recientes primero
    rsort($uids);
    $uids = array_slice($uids, 0, $limit);

    $emails = [];
    foreach ($uids as $uid) {
        $email = _parsear_email($conn, $uid);
        if ($email) {
            $emails[] = $email;
        }
    }
    return $emails;
}

/**
 * Obtiene el detalle completo de un email por UID.
 */
function obtener_email_detalle($conn, int $uid): ?array {
    if (!$conn) return null;
    return _parsear_email($conn, $uid, true);
}

/**
 * Cuenta emails no leídos para un alias.
 */
function contar_no_leidos($conn, string $alias): int {
    if (!$conn) return 0;

    $aliases = ALIASES_VALIDOS;
    $email_alias = $aliases[$alias] ?? null;
    if (!$email_alias) return 0;

    $criterio = 'UNSEEN TO "' . $email_alias . '"';
    $uids = @imap_search($conn, $criterio, SE_UID);
    return $uids ? count($uids) : 0;
}

/**
 * Parsea un email IMAP por UID.
 * @param bool $detalle Si true, incluye cuerpo completo
 */
function _parsear_email($conn, int $uid, bool $detalle = false): ?array {
    $num = @imap_msgno($conn, $uid);
    if (!$num) return null;

    $headers  = @imap_headerinfo($conn, $num);
    if (!$headers) return null;

    $from     = isset($headers->from[0]) ? _decode_header($headers->from[0]->personal ?? '') . ' <' . ($headers->from[0]->mailbox . '@' . $headers->from[0]->host) . '>' : 'Desconocido';
    $to       = isset($headers->to[0]) ? ($headers->to[0]->mailbox . '@' . $headers->to[0]->host) : '';
    $asunto   = _decode_header($headers->subject ?? '(Sin asunto)');
    $fecha    = isset($headers->date) ? date('Y-m-d H:i:s', strtotime($headers->date)) : date('Y-m-d H:i:s');
    $leido    = (strpos($headers->Unseen ?? 'U', 'U') === false);

    $cuerpo_preview = '';
    $cuerpo_completo = '';

    if ($detalle) {
        $cuerpo_completo = _obtener_cuerpo($conn, $num);
        $cuerpo_preview  = mb_substr(strip_tags($cuerpo_completo), 0, 80) . '...';
    } else {
        // Solo preview para listado (más rápido)
        $estructura = @imap_fetchstructure($conn, $num);
        $texto_plano = @imap_fetchbody($conn, $num, '1');
        if ($estructura && isset($estructura->encoding)) {
            $texto_plano = _decodificar_cuerpo($texto_plano, $estructura->encoding);
        }
        $cuerpo_preview = mb_substr(strip_tags($texto_plano), 0, 80);
    }

    return [
        'uid'             => $uid,
        'from'            => $from,
        'to'              => $to,
        'asunto'          => $asunto,
        'fecha'           => $fecha,
        'leido'           => $leido,
        'preview'         => $cuerpo_preview,
        'cuerpo_completo' => $cuerpo_completo,
    ];
}

/**
 * Obtiene el cuerpo completo de un mensaje (HTML o texto plano).
 */
function _obtener_cuerpo($conn, int $num): string {
    $estructura = @imap_fetchstructure($conn, $num);
    if (!$estructura) return '';

    if ($estructura->type === 0) {
        // Mensaje de texto simple
        $cuerpo = @imap_fetchbody($conn, $num, '1');
        return nl2br(htmlspecialchars(_decodificar_cuerpo($cuerpo, $estructura->encoding)));
    }

    // Multipart — buscar text/html primero, luego text/plain
    if (isset($estructura->parts)) {
        $html  = '';
        $plain = '';
        foreach ($estructura->parts as $i => $parte) {
            $part_num = $i + 1;
            $subtype  = strtolower($parte->subtype ?? '');
            $raw      = @imap_fetchbody($conn, $num, (string)$part_num);
            $decoded  = _decodificar_cuerpo($raw, $parte->encoding ?? 0);

            if ($subtype === 'html' && empty($html)) {
                $html = $decoded;
            } elseif ($subtype === 'plain' && empty($plain)) {
                $plain = nl2br(htmlspecialchars($decoded));
            }
        }
        return $html ?: $plain ?: '<em>(Sin contenido legible)</em>';
    }

    return '<em>(Sin contenido legible)</em>';
}

/**
 * Decodifica el cuerpo según el encoding IMAP.
 */
function _decodificar_cuerpo(string $raw, int $encoding): string {
    switch ($encoding) {
        case 3: // BASE64
            return base64_decode($raw);
        case 4: // QUOTED-PRINTABLE
            return quoted_printable_decode($raw);
        default:
            return $raw;
    }
}

/**
 * Decodifica cabeceras MIME (asunto, nombre remitente).
 */
function _decode_header(string $header): string {
    $decoded = imap_mime_header_decode($header);
    $result  = '';
    foreach ($decoded as $part) {
        $charset = $part->charset === 'default' ? 'UTF-8' : $part->charset;
        $result .= mb_convert_encoding($part->text, 'UTF-8', $charset);
    }
    return $result;
}
