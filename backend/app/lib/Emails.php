<?php
declare(strict_types=1);

// Email texts. Guest emails follow the language the guest picked (en/id).
// All wording must be approved by the clinic before going live.
final class Emails
{
    public static function bookingReceived(array $b): void
    {
        $alertTo = Settings::get('notify_email');
        if ($alertTo) {
            $hasHealth = Health::needsAttention(self::health((int) $b['id']));
            $rows = [
                'Reference' => $b['code'],
                'Service' => $b['service_name'],
                'When' => Booking::whenText($b),
                'Guests' => (string) $b['guests_count'],
                'Name' => $b['name'],
                'WhatsApp' => $b['whatsapp'],
                'Email' => $b['email'] ?: '–',
                'Staying at' => $b['staying_at'] ?: '–',
                'Messages in' => $b['language'] === 'id' ? 'Bahasa Indonesia' : 'English',
                'Health check' => $hasHealth ? 'Has answers to review: open the dashboard' : 'Nothing reported',
            ];
            $html = self::layout(
                '<p>A new booking request came in from the website. It is not confirmed yet.</p>'
                . self::table($rows)
                . ($b['message'] ? '<p><b>Message from guest</b><br>' . nl2br(e($b['message'])) . '</p>' : '')
                . self::button(rtrim((string) config('site_url'), '/') . '/admin/booking.php?id=' . (int) $b['id'], 'Open in dashboard')
            );
            Mailer::queue((int) $b['id'], 'booking_alert_clinic', $alertTo,
                "New booking request {$b['code']}: {$b['name']}, {$b['service_name']}", $html);
        }

        if ($b['email']) {
            $id = $b['language'] === 'id';
            $rows = $id
                ? ['Kode' => $b['code'], 'Layanan' => $b['service_name'], 'Waktu' => self::whenId($b),
                   'Jumlah tamu' => (string) $b['guests_count'], 'Harga' => self::priceId($b)]
                : ['Reference' => $b['code'], 'Service' => $b['service_name'], 'When' => Booking::whenText($b),
                   'Guests' => (string) $b['guests_count'], 'Price' => self::priceEn($b)];
            $intro = $id
                ? '<p>Halo ' . e($b['name']) . ',</p><p>Terima kasih. Kami sudah menerima permintaan booking Anda. '
                  . '<b>Ini masih permintaan, belum terkonfirmasi.</b> Kami akan mengecek jadwal dan menghubungi Anda lewat WhatsApp di '
                  . e($b['whatsapp']) . ' untuk konfirmasi.</p>'
                : '<p>Hello ' . e($b['name']) . ',</p><p>Thank you. We have received your booking request. '
                  . '<b>This is a request and is not confirmed yet.</b> We will check the schedule and contact you on WhatsApp at '
                  . e($b['whatsapp']) . ' to confirm.</p>';
            Mailer::queue((int) $b['id'], 'booking_received_guest', $b['email'],
                $id ? "Permintaan booking Anda sudah kami terima ({$b['code']})" : "We received your booking request ({$b['code']})",
                self::layout($intro . self::table($rows)));
        }
    }

    public static function bookingConfirmed(array $b): void
    {
        self::guestNotice($b, 'booking_confirmed_guest',
            "Your booking is confirmed ({$b['code']})",
            "Booking Anda sudah terkonfirmasi ({$b['code']})",
            'Your booking is confirmed.',
            'Booking Anda sudah terkonfirmasi.');
    }

    public static function bookingCancelled(array $b): void
    {
        self::guestNotice($b, 'booking_cancelled_guest',
            "Your booking has been cancelled ({$b['code']})",
            "Booking Anda dibatalkan ({$b['code']})",
            'This booking has been cancelled. If you did not expect this, or would like another time, reply to this email or message us on WhatsApp.',
            'Booking ini dibatalkan. Jika Anda tidak mengharapkan ini, atau ingin memilih waktu lain, balas email ini atau hubungi kami lewat WhatsApp.');
    }

    public static function bookingRescheduled(array $b): void
    {
        self::guestNotice($b, 'booking_rescheduled_guest',
            "Your booking time has changed ({$b['code']})",
            "Waktu booking Anda berubah ({$b['code']})",
            'The time of your booking has changed. The new details are below.',
            'Waktu booking Anda berubah. Detail terbaru ada di bawah.');
    }

    private static function guestNotice(array $b, string $template, string $subEn, string $subId, string $lineEn, string $lineId): void
    {
        if (!$b['email']) {
            return;
        }
        $id = $b['language'] === 'id';
        $rows = $id
            ? ['Kode' => $b['code'], 'Layanan' => $b['service_name'], 'Waktu' => self::whenId($b), 'Harga' => self::priceId($b)]
            : ['Reference' => $b['code'], 'Service' => $b['service_name'], 'When' => Booking::whenText($b), 'Price' => self::priceEn($b)];
        $html = self::layout(
            '<p>' . ($id ? 'Halo ' : 'Hello ') . e($b['name']) . ',</p><p>' . e($id ? $lineId : $lineEn) . '</p>' . self::table($rows)
        );
        Mailer::queue((int) $b['id'], $template, $b['email'], $id ? $subId : $subEn, $html);
    }

    private static function price(array $b): ?int
    {
        return $b['price_idr_snapshot'] === null ? null : (int) $b['price_idr_snapshot'];
    }

    // "++" = tax and service are added, as on the website price list.
    private static function priceEn(array $b): string
    {
        $p = self::price($b);
        return $p === null ? 'to be confirmed' : idr($p) . '++ (tax & service added)'
            . ($b['offer_percent'] ? ', includes ' . (int) $b['offer_percent'] . '% package offer' : '');
    }

    private static function priceId(array $b): string
    {
        $p = self::price($b);
        return $p === null ? 'akan dikonfirmasi' : idr($p) . '++ (belum termasuk pajak & servis)'
            . ($b['offer_percent'] ? ', sudah termasuk diskon paket ' . (int) $b['offer_percent'] . '%' : '');
    }

    private static function whenId(array $b): string
    {
        $days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $ts = strtotime($b['start_date']);
        $date = $days[(int) date('N', $ts)] . ', ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
        return $b['start_time'] ? $date . ', pukul ' . substr($b['start_time'], 0, 5) . ' WITA' : 'Mulai ' . $date;
    }

    private static function health(int $bookingId): array
    {
        $json = Db::value('SELECT answers FROM health_checks WHERE booking_id = ?', [$bookingId]);
        return $json ? (json_decode((string) $json, true) ?: []) : [];
    }

    private static function table(array $rows): string
    {
        $out = '<table style="border-collapse:collapse;margin:16px 0">';
        foreach ($rows as $k => $v) {
            $out .= '<tr><td style="padding:4px 16px 4px 0;color:#6b746c">' . e($k) . '</td><td style="padding:4px 0">' . e($v) . '</td></tr>';
        }
        return $out . '</table>';
    }

    private static function button(string $href, string $label): string
    {
        return '<p><a href="' . e($href) . '" style="display:inline-block;background:#3F4A32;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">' . e($label) . '</a></p>';
    }

    private static function layout(string $inner): string
    {
        return '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#1f2a22;max-width:560px">'
            . $inner
            . '<p style="margin-top:24px;color:#6b746c">Ayurvedic Wellness House</p></div>';
    }
}
