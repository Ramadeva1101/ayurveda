<?php
declare(strict_types=1);

final class Booking
{
    public const STATUS_LABELS = [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked in',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ];

    // Allowed status moves from the admin.
    public const TRANSITIONS = [
        'requested' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    // "+62 812-3456 789" -> "+628123456789"; "0812..." -> "+62812..."; null if invalid.
    public static function normaliseWhatsapp(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with(trim($raw), '0') && !str_starts_with(trim($raw), '00')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? '+' . $digits : null;
    }

    // Validates a guest request and stores it. Returns ['code' => ..] or ['error' => ..].
    public static function createFromRequest(array $in): array
    {
        $service = Db::one(
            'SELECT * FROM services WHERE slug = ? AND active = 1 AND bookable_online = 1',
            [(string) ($in['service'] ?? '')]
        );
        if (!$service) {
            return ['error' => 'Please choose a service.'];
        }

        $name = trim(mb_substr((string) ($in['name'] ?? ''), 0, 190));
        $whatsapp = self::normaliseWhatsapp((string) ($in['whatsapp'] ?? ''));
        $email = trim((string) ($in['email'] ?? ''));
        $language = ($in['language'] ?? '') === 'id' ? 'id' : 'en';
        $guests = (int) ($in['guests_count'] ?? 1);
        $stayingAt = trim(mb_substr((string) ($in['staying_at'] ?? ''), 0, 190)) ?: null;
        $message = trim(mb_substr((string) ($in['message'] ?? ''), 0, 2000)) ?: null;
        $date = (string) ($in['date'] ?? '');
        $time = $service['type'] === 'program' ? null : (string) ($in['time'] ?? '');

        if ($name === '') {
            return ['error' => 'Please enter your name.'];
        }
        if ($whatsapp === null) {
            return ['error' => 'Please enter a WhatsApp number with country code, e.g. +62 812 3456 7890.'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'That email address does not look right.'];
        }
        if ($guests < 1 || $guests > 2) {
            return ['error' => 'Guests must be 1 or 2.'];
        }
        [$health, $err] = Health::parse($in['health'] ?? null);
        if ($err) {
            return ['error' => $err];
        }

        $pdo = Db::pdo();
        // Serialise bookings for the same slot so two guests cannot take the last place.
        $lock = 'awh:' . $date . ':' . ($time ?? 'day');
        if ((int) Db::value('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            return ['error' => 'The booking system is busy. Please try again.'];
        }
        try {
            $check = Availability::check($service, $date, $time);
            if (!$check['ok']) {
                return ['error' => $check['reason']];
            }

            $pricing = Pricing::forService($service);
            $pdo->beginTransaction();
            $guest = Db::one('SELECT id, email FROM guests WHERE whatsapp = ? ORDER BY id LIMIT 1', [$whatsapp]);
            if ($guest) {
                $guestId = (int) $guest['id'];
                if ($email !== '' && !$guest['email']) {
                    Db::run('UPDATE guests SET email = ? WHERE id = ?', [$email, $guestId]);
                }
            } else {
                Db::run(
                    'INSERT INTO guests (name, whatsapp, email, language) VALUES (?, ?, ?, ?)',
                    [$name, $whatsapp, $email ?: null, $language]
                );
                $guestId = (int) $pdo->lastInsertId();
            }

            Db::run(
                "INSERT INTO bookings (code, guest_id, service_id, start_date, start_time, guests_count,
                   staying_at, message, status, source, price_idr_snapshot, offer_percent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'requested', 'website', ?, ?)",
                ['TMP-' . bin2hex(random_bytes(6)), $guestId, $service['id'], $date, $time, $guests, $stayingAt, $message, $pricing['price'], $pricing['offer']]
            );
            $id = (int) $pdo->lastInsertId();
            $code = 'AWH-' . (1000 + $id);
            Db::run('UPDATE bookings SET code = ? WHERE id = ?', [$code, $id]);
            Db::run('INSERT INTO health_checks (booking_id, answers) VALUES (?, ?)', [$id, json_encode($health)]);
            self::log($id, null, 'created', 'Request from website');

            // Use the language picked on this request for emails about it.
            $booking = self::find($id);
            $booking['language'] = $language;
            $booking['email'] = $email ?: $booking['email'];
            Emails::bookingReceived($booking);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        } finally {
            Db::value('SELECT RELEASE_LOCK(?)', [$lock]);
        }

        return ['code' => $code];
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            "SELECT b.*, g.name, g.whatsapp, g.email, g.language, g.country,
                    s.name AS service_name, s.type AS service_type,
                    s.duration_minutes, s.duration_days, s.slug AS service_slug
             FROM bookings b
             JOIN guests g ON g.id = b.guest_id
             JOIN services s ON s.id = b.service_id
             WHERE b.id = ?",
            [$id]
        );
    }

    // Admin status change. Returns null on success or an error message.
    public static function changeStatus(int $id, string $to, int $userId, bool $notify): ?string
    {
        $b = self::find($id);
        if (!$b) {
            return 'Booking not found.';
        }
        if (!in_array($to, self::TRANSITIONS[$b['status']] ?? [], true)) {
            return 'Cannot move from ' . self::STATUS_LABELS[$b['status']] . ' to ' . (self::STATUS_LABELS[$to] ?? $to) . '.';
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        if ($to === 'confirmed') {
            Db::run('UPDATE bookings SET status = ?, confirmed_at = NOW(), confirmed_by = ? WHERE id = ?', [$to, $userId, $id]);
            self::syncAppointment($b);
        } else {
            Db::run('UPDATE bookings SET status = ? WHERE id = ?', [$to, $id]);
        }
        if ($to === 'cancelled') {
            Db::run("DELETE FROM appointments WHERE booking_id = ? AND kind = 'session'", [$id]);
        }
        self::log($id, $userId, 'status:' . $to, $notify ? 'Guest emailed' : null);

        if ($notify) {
            $fresh = self::find($id);
            if ($to === 'confirmed') {
                Emails::bookingConfirmed($fresh);
            } elseif ($to === 'cancelled') {
                Emails::bookingCancelled($fresh);
            }
        }
        $pdo->commit();
        return null;
    }

    // Admin moves a booking to another date/time. Capacity is checked unless forced.
    public static function reschedule(int $id, string $date, ?string $time, int $userId, bool $notify, bool $force): ?string
    {
        $b = self::find($id);
        if (!$b || !in_array($b['status'], ['requested', 'confirmed'], true)) {
            return 'Only requested or confirmed bookings can be moved.';
        }
        $service = Db::one('SELECT * FROM services WHERE id = ?', [$b['service_id']]);
        if ($service['type'] !== 'program') {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time)) {
                return 'Enter the new time as HH:MM.';
            }
        } else {
            $time = null;
        }
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return 'Enter a valid date.';
        }

        if (!$force && $time !== null) {
            $capacity = max(1, (int) Settings::get('slot_capacity', '1'));
            $taken = (int) Db::value(
                "SELECT COUNT(*) FROM bookings WHERE start_date = ? AND start_time = ? AND id <> ?
                 AND status IN ('requested','confirmed','checked_in')",
                [$date, $time, $id]
            );
            if ($taken >= $capacity) {
                return 'That slot is already full. Tick "Move anyway" to override.';
            }
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        Db::run('UPDATE bookings SET start_date = ?, start_time = ? WHERE id = ?', [$date, $time, $id]);
        $fresh = self::find($id);
        if ($fresh['status'] === 'confirmed') {
            self::syncAppointment($fresh);
        }
        $from = $b['start_date'] . ($b['start_time'] ? ' ' . substr($b['start_time'], 0, 5) : '');
        self::log($id, $userId, 'rescheduled', "From $from" . ($notify ? ', guest emailed' : ''));
        if ($notify) {
            Emails::bookingRescheduled($fresh);
        }
        $pdo->commit();
        return null;
    }

    // Keeps the calendar entry of a timed booking in step with the booking.
    private static function syncAppointment(array $b): void
    {
        if ($b['start_time'] === null) {
            return; // programs: the clinic plans sessions by hand in the calendar
        }
        $start = $b['start_date'] . ' ' . $b['start_time'];
        $end = date('Y-m-d H:i:s', strtotime($start) + max(5, (int) $b['duration_minutes']) * 60);
        $title = $b['name'] . ' · ' . $b['service_name'];
        $existing = Db::value("SELECT id FROM appointments WHERE booking_id = ? AND kind = 'session' LIMIT 1", [$b['id']]);
        if ($existing) {
            Db::run('UPDATE appointments SET title = ?, starts_at = ?, ends_at = ? WHERE id = ?', [$title, $start, $end, $existing]);
        } else {
            Db::run(
                "INSERT INTO appointments (booking_id, title, starts_at, ends_at, kind) VALUES (?, ?, ?, ?, 'session')",
                [$b['id'], $title, $start, $end]
            );
        }
    }

    public static function log(int $bookingId, ?int $userId, string $action, ?string $detail = null): void
    {
        Db::run(
            'INSERT INTO booking_events (booking_id, user_id, action, detail) VALUES (?, ?, ?, ?)',
            [$bookingId, $userId, $action, $detail]
        );
    }

    public static function whenText(array $b): string
    {
        $date = date('D j M Y', strtotime($b['start_date']));
        return $b['start_time'] ? $date . ', ' . substr($b['start_time'], 0, 5) : 'Starting ' . $date;
    }
}
