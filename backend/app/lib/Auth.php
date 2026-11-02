<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_FAILS = 5;
    private const LOCK_MINUTES = 15;
    private const IDLE_MINUTES = 120;

    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        if (time() - ($_SESSION['last_seen'] ?? 0) > self::IDLE_MINUTES * 60) {
            self::logout();
            return null;
        }
        $_SESSION['last_seen'] = time();
        $user = Db::one('SELECT id, name, email, role FROM users WHERE id = ? AND active = 1', [$id]);
        if (!$user) {
            self::logout();
        }
        return $user;
    }

    public static function require(?string $role = null): array
    {
        $user = self::user();
        if (!$user) {
            redirect('/admin/login.php');
        }
        if ($role === 'developer' && $user['role'] !== 'developer') {
            http_response_code(403);
            exit('Only a developer account can open this page.');
        }
        return $user;
    }

    public static function isLocked(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
        $fails = (int) Db::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND created_at > ? AND (ip = ? OR email = ?)',
            [$since, client_ip(), $email]
        );
        return $fails >= self::MAX_FAILS;
    }

    // Returns null on success, or an error message.
    public static function attempt(string $email, string $password): ?string
    {
        $email = strtolower(trim($email));
        if (self::isLocked($email)) {
            return 'Too many failed attempts. Try again in ' . self::LOCK_MINUTES . ' minutes.';
        }

        $user = Db::one('SELECT id, password_hash FROM users WHERE email = ? AND active = 1', [$email]);
        $ok = $user && password_verify($password, $user['password_hash']);

        Db::run(
            'INSERT INTO login_attempts (ip, email, success) VALUES (?, ?, ?)',
            [client_ip(), $email, $ok ? 1 : 0]
        );

        if (!$ok) {
            return 'Email or password is wrong.';
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_seen'] = time();
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function validatePassword(string $password): ?string
    {
        if (strlen($password) < 12) {
            return 'Password must be at least 12 characters.';
        }
        return null;
    }
}
