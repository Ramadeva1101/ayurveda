<?php
// Copy to config.php and fill in. config.php holds secrets: never commit it,
// never place it inside public_html.

return [
    // 'production' on the live server, 'local' on your own computer.
    'env' => 'production',

    'db' => [
        'host' => 'localhost',
        'name' => 'cpaneluser_awh',
        'user' => 'cpaneluser_awh',
        'pass' => '',
    ],

    // Bali is UTC+8 (WITA). All dates shown in admin use this zone.
    'timezone' => 'Asia/Makassar',

    // true in production (site served over HTTPS through Cloudflare).
    // Set false only for local http:// testing.
    'cookie_secure' => true,

    // One-time token for admin/setup.php. Pick a long random string, create the
    // first developer account, then set this back to '' to disable the setup page.
    'setup_token' => '',

    // Public site address, used for links inside emails.
    'site_url' => 'https://ayurvedicwellnesshouse.com',

    // Cloudflare Turnstile (anti-spam on the Book form). Required in production.
    'turnstile' => [
        'site_key' => '',
        'secret' => '',
    ],

    'mail' => [
        // 'smtp' sends real email. 'log' only stores it in Admin > Emails
        // (for local testing; nothing leaves the computer).
        'driver' => 'smtp',
        'from_email' => 'booking@ayurvedicwellnesshouse.com',
        'from_name' => 'Ayurvedic Wellness House',
        'smtp_host' => 'mail.ayurvedicwellnesshouse.com',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',   // 'ssl' for 465, 'tls' for 587
        'smtp_user' => 'booking@ayurvedicwellnesshouse.com',
        'smtp_pass' => '',
    ],
];
