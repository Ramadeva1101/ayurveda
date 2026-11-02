<?php
declare(strict_types=1);

// cPanel Cron Job, every 15 minutes:
//   php /home/USER/awh-app/cron/run.php
// Sends queued emails (and retries failed ones) and clears old rate-limit rows.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

$result = Mailer::processQueue(50);
Db::run('DELETE FROM rate_hits WHERE created_at < NOW() - INTERVAL 1 DAY');
Db::run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 30 DAY');

echo date('c') . " sent={$result['sent']} failed={$result['failed']}\n";
