<?php
require_once __DIR__ . '/../api/config.php';

function testGemini($apiKey) {
    echo "Probando Gemini...\n";
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    $payload = json_encode([
        'contents' => [['parts' => [['text' => "Hola, responde solo 'OK' si recibes esto."]]]]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $code\n";
    echo "Response: $res\n";
}

testGemini(GEMINI_API_KEY);
