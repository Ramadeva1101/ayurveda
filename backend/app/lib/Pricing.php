<?php
declare(strict_types=1);

// Package offer set in Admin > Settings (e.g. 30% off, 1–30 Nov).
// Applies to packages only: the signature experience and residential programs,
// the same items listed on the website's package page. Single treatments keep
// their normal price. The offer counts by the day the request is sent.
final class Pricing
{
    public const OFFER_TYPES = ['experience', 'program'];

    // Active offer percent today, or null.
    public static function offerPercent(): ?int
    {
        $pct = (int) Settings::get('offer_percent', '0');
        $from = (string) Settings::get('offer_from', '');
        $until = (string) Settings::get('offer_until', '');
        $today = date('Y-m-d');
        if ($pct < 1 || $pct > 90 || $from === '' || $until === '' || $today < $from || $today > $until) {
            return null;
        }
        return $pct;
    }

    // ['price' => final price or null, 'regular' => list price or null, 'offer' => percent or null]
    public static function forService(array $service): array
    {
        $regular = $service['price_idr'] === null ? null : (int) $service['price_idr'];
        $pct = in_array($service['type'], self::OFFER_TYPES, true) ? self::offerPercent() : null;
        if ($regular === null || $pct === null) {
            return ['price' => $regular, 'regular' => $regular, 'offer' => null];
        }
        return ['price' => (int) round($regular * (100 - $pct) / 100), 'regular' => $regular, 'offer' => $pct];
    }
}
