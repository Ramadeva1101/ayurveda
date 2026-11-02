<?php
declare(strict_types=1);

// Every email goes into email_queue first and is sent by Mailer::processQueue()
// (right after a booking, and by cron). A failed send stays visible in
// Admin > Emails and is retried up to MAX_ATTEMPTS times.
final class Mailer
{
    private const MAX_ATTEMPTS = 5;

    public static function queue(?int $bookingId, string $template, string $to, string $subject, string $html): void
    {
        Db::run(
            'INSERT INTO email_queue (booking_id, template, to_email, subject, body_html) VALUES (?, ?, ?, ?, ?)',
            [$bookingId, $template, $to, $subject, $html]
        );
    }

    public static function processQueue(int $limit = 20): array
    {
        $rows = Db::all(
            "SELECT * FROM email_queue
             WHERE status IN ('pending','failed') AND attempts < ? AND send_after <= NOW()
             ORDER BY id LIMIT $limit",
            [self::MAX_ATTEMPTS]
        );
        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            // Claim the row so two runs never send the same email twice.
            $claimed = Db::run(
                'UPDATE email_queue SET attempts = attempts + 1 WHERE id = ? AND attempts = ?',
                [$row['id'], $row['attempts']]
            );
            if (!$claimed) {
                continue;
            }
            $error = self::send($row['to_email'], $row['subject'], $row['body_html']);
            if ($error === null) {
                Db::run("UPDATE email_queue SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ?", [$row['id']]);
                $sent++;
            } else {
                Db::run("UPDATE email_queue SET status = 'failed', last_error = ? WHERE id = ?", [mb_substr($error, 0, 1000), $row['id']]);
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    // Returns null on success or the error text.
    private static function send(string $to, string $subject, string $html): ?string
    {
        $m = config('mail') ?? [];
        $driver = $m['driver'] ?? 'log';

        if ($driver === 'log') {
            return null; // local testing: kept in the queue table, viewable in Admin > Emails
        }

        $base = APP_DIR . '/vendor/PHPMailer/src/';
        if (!is_file($base . 'PHPMailer.php')) {
            return 'PHPMailer is missing in app/vendor/PHPMailer.';
        }
        require_once $base . 'Exception.php';
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $m['smtp_host'];
            $mail->Port = (int) $m['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = $m['smtp_secure'];
            $mail->Username = $m['smtp_user'];
            $mail->Password = $m['smtp_pass'];
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($m['from_email'], $m['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</tr>'], "\n", $html))));
            $mail->send();
            return null;
        } catch (Throwable $ex) {
            return $mail->ErrorInfo ?: $ex->getMessage();
        }
    }
}
