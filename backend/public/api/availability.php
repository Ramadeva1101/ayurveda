<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

// GET ?service=slug&month=YYYY-MM -> free days and times for that month.

if (!booking_enabled()) {
    json_out(['error' => 'Online booking is not open.'], 403);
}

$service = Db::one(
    'SELECT * FROM services WHERE slug = ? AND active = 1 AND bookable_online = 1',
    [(string) ($_GET['service'] ?? '')]
);
$month = (string) ($_GET['month'] ?? '');
if (!$service || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    json_out(['error' => 'Unknown service or month.'], 400);
}

json_out(['month' => $month, 'days' => Availability::forMonth($service, $month)]);
