<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

// GET -> the package offer active today (set in Admin > Settings), for the
// website's package page. {"percent": 30, "until": "2026-11-30"} or {"percent": null}.

$pct = Pricing::offerPercent();
json_out($pct === null
    ? ['percent' => null]
    : ['percent' => $pct, 'until' => Settings::get('offer_until')]);
