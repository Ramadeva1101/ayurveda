<?php
declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT key_name, value FROM settings') as $row) {
                self::$cache[$row['key_name']] = $row['value'];
            }
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Db::run(
            'INSERT INTO settings (key_name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
        self::$cache = null;
    }
}
