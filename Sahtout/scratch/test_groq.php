<?php
require_once __DIR__ . '/../api/config.php';

function testGroq($apiKey) {
    echo "Probando Groq...\n";
    $url = "https://api.groq.com/openai/v1/chat/completions";
    
    $payload = json_encode([
        'model' => 'mixtral-8x7b-32768',
        'messages' => [['role' => 'user', 'content' => "Hola, responde solo 'OK'"]]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $code\n";
    echo "Response: $res\n";
}

testGroq(GROQ_API_KEY);
