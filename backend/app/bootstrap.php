<?php
declare(strict_types=1);

// Shared entry point for admin pages, API endpoints and cron.

define('APP_DIR', __DIR__);

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing awh-app/config.php. Copy config.example.php and fill it in.');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set(config('timezone') ?? 'Asia/Makassar');

require APP_DIR . '/lib/Db.php';
require APP_DIR . '/lib/Csrf.php';
require APP_DIR . '/lib/Auth.php';
require APP_DIR . '/lib/Settings.php';
require APP_DIR . '/lib/Health.php';
require APP_DIR . '/lib/Pricing.php';
require APP_DIR . '/lib/Availability.php';
require APP_DIR . '/lib/Booking.php';
require APP_DIR . '/lib/Mailer.php';
require APP_DIR . '/lib/Emails.php';

function config(string $key)
{
    return $GLOBALS['config'][$key] ?? null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// Cloudflare's published edge ranges (https://www.cloudflare.com/ips/).
const CLOUDFLARE_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

function ip_in_range(string $ip, string $cidr): bool
{
    [$net, $bits] = explode('/', $cidr);
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $rest)) & 0xFF);
    return (($ipBin[$bytes] & $mask) === ($netBin[$bytes] & $mask));
}

function client_ip(): string
{
    // The visitor IP header from Cloudflare is used only when the request
    // really comes from Cloudflare. Anyone reaching the server directly could
    // otherwise fake it to get around the rate limit and login lockout.
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = $remote;
    $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '') {
        foreach (CLOUDFLARE_RANGES as $range) {
            if (ip_in_range($remote, $range)) {
                $ip = $cf;
                break;
            }
        }
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_production(): bool
{
    return config('env') !== 'local';
}

function idr(?int $amount): string
{
    return $amount === null ? 'to be confirmed' : 'IDR ' . number_format($amount, 0, ',', '.');
}

// Allow at most $max hits per $minutes for one bucket (e.g. per IP).
function rate_limited(string $bucket, int $max, int $minutes): bool
{
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $count = (int) Db::value('SELECT COUNT(*) FROM rate_hits WHERE bucket = ? AND created_at > ?', [$bucket, $since]);
    if ($count >= $max) {
        return true;
    }
    Db::run('INSERT INTO rate_hits (bucket) VALUES (?)', [$bucket]);
    return false;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('awh_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => (bool) config('cookie_secure'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
