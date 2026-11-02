<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Find the private backend folder "awh-app" by walking up from here, so the
// same files work in /home/USER/public_html (main domain) and in an addon
// domain folder such as /home/USER/public_html/example.com.
// awh-app must sit OUTSIDE public_html, e.g. /home/USER/awh-app.
(function () {
    for ($dir = dirname(__DIR__); $dir !== dirname($dir); $dir = dirname($dir)) {
        if (is_file($dir . '/awh-app/bootstrap.php')) {
            require $dir . '/awh-app/bootstrap.php';
            return;
        }
        // Local development: the repository keeps it as backend/app (marked).
        if (is_file($dir . '/app/.awh-backend')) {
            require $dir . '/app/bootstrap.php';
            return;
        }
    }
    http_response_code(500);
    exit('Backend folder awh-app not found. Extract awh-app.zip in your home folder.');
})();

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function (Throwable $ex) {
    error_log('[awh api] ' . $ex);
    json_out(['error' => 'Something went wrong on our side. Please try again or contact us on WhatsApp.'], 500);
});

function booking_enabled(): bool
{
    return Settings::get('booking_enabled') === '1';
}
