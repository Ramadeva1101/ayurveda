<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

// Everything the Book page needs before the guest picks a date.

if (!booking_enabled()) {
    json_out(['enabled' => false]);
}

$services = array_map(fn ($s) => [
    'slug' => $s['slug'],
    'type' => $s['type'],
    'name' => $s['name'],
    'duration_minutes' => $s['duration_minutes'] === null ? null : (int) $s['duration_minutes'],
    'duration_days' => $s['duration_days'] === null ? null : (int) $s['duration_days'],
    'price_idr' => Pricing::forService($s)['price'],
    'price_regular' => Pricing::forService($s)['regular'],
    'offer_percent' => Pricing::forService($s)['offer'],
    'image' => $s['image'],
], Db::all(
    "SELECT slug, type, name, duration_minutes, duration_days, price_idr, image FROM services
     WHERE active = 1 AND bookable_online = 1
     ORDER BY FIELD(type, 'experience', 'treatment', 'program'), sort_order, name"
));

json_out([
    'enabled' => true,
    'services' => $services,
    'max_days_ahead' => (int) Settings::get('max_days_ahead', '180'),
    'turnstile_site_key' => (config('turnstile')['site_key'] ?? '') ?: null,
    'health' => [
        'pregnant' => Health::PREGNANT_OPTIONS,
        'conditions' => Health::CONDITIONS,
    ],
]);
