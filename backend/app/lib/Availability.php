<?php
declare(strict_types=1);

// Works out which dates and start times guests can request.
// Rules come only from Admin > Settings: opening days, slot times, closed dates,
// capacity per slot, minimum notice and how far ahead. Clinic-wide blocks in
// the calendar (kind = 'block', no practitioner) also close overlapping slots.
final class Availability
{
    public const HOLDING = ['requested', 'confirmed', 'checked_in'];

    // Returns ['ok' => bool, 'reason' => string|null] for a date (and time).
    public static function check(array $service, string $date, ?string $time): array
    {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return ['ok' => false, 'reason' => 'Invalid date.'];
        }

        $today = new DateTime('today');
        $lastDay = (clone $today)->modify('+' . (int) Settings::get('max_days_ahead', '180') . ' days');
        if ($d < $today || $d > $lastDay) {
            return ['ok' => false, 'reason' => 'That date cannot be booked online.'];
        }
        if (Db::value('SELECT 1 FROM closed_dates WHERE date = ?', [$date])) {
            return ['ok' => false, 'reason' => 'The clinic is closed on that date.'];
        }
        $day = Db::one('SELECT is_open, slot_times FROM opening_hours WHERE weekday = ?', [(int) $d->format('N')]);
        if (!$day || !$day['is_open']) {
            return ['ok' => false, 'reason' => 'The clinic is closed on that day.'];
        }

        // Programs pick a start date only.
        if ($service['type'] === 'program') {
            $earliest = time() + (int) Settings::get('min_notice_hours', '24') * 3600;
            if ($d->getTimestamp() + 86400 <= $earliest) {
                return ['ok' => false, 'reason' => 'That date is too soon to book online.'];
            }
            return ['ok' => true, 'reason' => null];
        }

        $slots = self::slotTimes((string) $day['slot_times']);
        if ($time === null || !in_array($time, $slots, true)) {
            return ['ok' => false, 'reason' => 'Pick one of the listed times.'];
        }
        foreach (self::forDay($service, $date) as $slot) {
            if ($slot['time'] === $time) {
                return $slot['free']
                    ? ['ok' => true, 'reason' => null]
                    : ['ok' => false, 'reason' => 'That time is no longer free. Please pick another.'];
            }
        }
        return ['ok' => false, 'reason' => 'Pick one of the listed times.'];
    }

    // Slots for one open day: [['time' => '09:00', 'free' => bool], ...]
    public static function forDay(array $service, string $date): array
    {
        $weekday = (int) date('N', strtotime($date));
        $day = Db::one('SELECT is_open, slot_times FROM opening_hours WHERE weekday = ?', [$weekday]);
        if (!$day || !$day['is_open']) {
            return [];
        }
        $capacity = max(1, (int) Settings::get('slot_capacity', '1'));
        $earliest = time() + (int) Settings::get('min_notice_hours', '24') * 3600;
        $minutes = max(5, (int) ($service['duration_minutes'] ?? 60));

        $taken = [];
        foreach (Db::all(
            "SELECT TIME_FORMAT(start_time, '%H:%i') t, COUNT(*) n FROM bookings
             WHERE start_date = ? AND start_time IS NOT NULL
               AND status IN ('requested','confirmed','checked_in')
             GROUP BY start_time",
            [$date]
        ) as $row) {
            $taken[$row['t']] = (int) $row['n'];
        }
        $blocks = Db::all(
            "SELECT starts_at, ends_at FROM appointments
             WHERE kind = 'block' AND practitioner_id IS NULL
               AND starts_at < ? AND ends_at > ?",
            ["$date 23:59:59", "$date 00:00:00"]
        );

        $out = [];
        foreach (self::slotTimes((string) $day['slot_times']) as $t) {
            $start = strtotime("$date $t");
            $end = $start + $minutes * 60;
            $free = $start >= $earliest && ($taken[$t] ?? 0) < $capacity;
            foreach ($blocks as $b) {
                if ($start < strtotime($b['ends_at']) && $end > strtotime($b['starts_at'])) {
                    $free = false;
                }
            }
            $out[] = ['time' => $t, 'free' => $free];
        }
        return $out;
    }

    // Every day of a month (YYYY-MM): ['2026-11-02' => ['open' => bool, 'slots' => [...]]]
    public static function forMonth(array $service, string $month): array
    {
        $first = DateTime::createFromFormat('!Y-m', $month);
        if (!$first) {
            return [];
        }
        $out = [];
        $days = (int) $first->format('t');
        for ($i = 0; $i < $days; $i++) {
            $date = (clone $first)->modify("+$i days")->format('Y-m-d');
            $probe = self::check($service, $date, null);
            $openDay = $probe['ok'] || $probe['reason'] === 'Pick one of the listed times.';
            if (!$openDay) {
                $out[$date] = ['open' => false, 'slots' => []];
            } elseif ($service['type'] === 'program') {
                $out[$date] = ['open' => true, 'slots' => []];
            } else {
                $slots = self::forDay($service, $date);
                $any = (bool) array_filter($slots, fn ($s) => $s['free']);
                $out[$date] = ['open' => $any, 'slots' => $slots];
            }
        }
        return $out;
    }

    private static function slotTimes(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }
}
