<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

// POST (JSON) -> creates a booking request. Status starts as "requested";
// the clinic confirms it in the dashboard.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Method not allowed.'], 405);
}
if (!booking_enabled()) {
    json_out(['error' => 'Online booking is not open. Please contact us on WhatsApp.'], 403);
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_out(['error' => 'Invalid request.'], 400);
}

// Honeypot: real visitors never see or fill this field.
if (!empty($in['website'])) {
    json_out(['error' => 'Invalid request.'], 400);
}

$turnstile = config('turnstile') ?? [];
if (!empty($turnstile['secret'])) {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $turnstile['secret'],
            'response' => (string) ($in['turnstile_token'] ?? ''),
            'remoteip' => client_ip(),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    if (empty($res['success'])) {
        json_out(['error' => 'Spam check failed. Please reload the page and try again.'], 400);
    }
} elseif (is_production()) {
    error_log('[awh api] Turnstile secret missing in production config');
    json_out(['error' => 'Online booking is temporarily unavailable. Please contact us on WhatsApp.'], 503);
}

if (rate_limited('booking:' . client_ip(), 5, 60)) {
    json_out(['error' => 'Too many requests from this connection. Please contact us on WhatsApp.'], 429);
}

$result = Booking::createFromRequest($in);
if (isset($result['error'])) {
    json_out($result, 422);
}

// Send the two emails now instead of waiting for cron.
Mailer::processQueue(5);

json_out(['ok' => true, 'code' => $result['code']]);
