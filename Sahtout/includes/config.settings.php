<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// Site Title (Editable from Admin Panel)
// Site Title
$site_title_name = 'Noxertez Artesanía';

// Logo
$site_logo = 'img/logo.png';

// Social links
$social_links = [
    'facebook' => '',
    'twitter' => '',
    'tiktok' => 'https://www.tiktok.com/@noxertez',
    'youtube' => '',
    'discord' => '',
    'twitch' => '',
    'kick' => '',
    'instagram' => 'https://www.instagram.com/noxertez/',
    'whatsapp' => '693326269',
    'github' => '',
    'linkedin' => '',
    'trendioff' => '',
    'etsy' => '',
];

// Brand specific settings (Slogans and Colors)
$brand_settings = [
    'NOXERTEZ' => [
        'slogan' => 'ESTO NO ES UNA MARCA, ES UN ESTILO DE VIDA',
        'primary' => '#1a1a1a',
        'accent' => '#d4af37',
        'db_name' => 'noxertez',
        'social_links' => [
            'instagram' => 'https://www.instagram.com/noxertez/',
            'tiktok' => 'https://www.tiktok.com/@noxertez',
            'whatsapp' => '693326269',
            'trendioff' => 'https://trendioff.com/vendedor/noxertez',
            'etsy' => '',
        ]
    ],
    'CANDLE HOLDER OF THE SOUL' => [
        'slogan' => 'ILUMINA TU ALMA CON NUESTROS DISEÑOS',
        'primary' => '#2c3e50',
        'accent' => '#e67e22',
        'db_name' => 'CANDLEHOLDER',
        'social_links' => [
            'instagram' => '',
            'tiktok' => '',
            'whatsapp' => '',
            'trendioff' => '',
            'etsy' => '',
        ]
    ],
    'THE SECRET ZEN GARDEN' => [
        'slogan' => 'EL EQUILIBRIO DE LA NATURALEZA EN PIEDRAS DE MADERA ANCESTRALES',
        'primary' => '#27ae60',
        'accent' => '#8e44ad',
        'db_name' => 'THE SECRET ZEN GARDEN',
        'db_values' => ['THE SECRET ZEN GARDEN', 'ZEN GARDEN', 'zen', 'ZEN'],
        'social_links' => [
            'instagram' => '',
            'tiktok' => '',
            'whatsapp' => '',
            'trendioff' => '',
            'etsy' => '',
        ]
    ],
];
