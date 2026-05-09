<?php

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = 'localhost';
            $db = 'parking_db';
            $user = 'root';
            $pass = '';
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $db);
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::syncMysqlSessionTimeZone(self::$connection);
        }
        return self::$connection;
    }

    /** Align NOW() / CURRENT_TIMESTAMP in SQL with PHP's APP_TIMEZONE. */
    private static function syncMysqlSessionTimeZone(PDO $pdo): void
    {
        if (!defined('APP_TIMEZONE')) {
            return;
        }
        try {
            $tz = new DateTimeZone(APP_TIMEZONE);
            $now = new DateTimeImmutable('now', $tz);
            $offsetSeconds = $tz->getOffset($now);
            $sign = $offsetSeconds >= 0 ? '+' : '-';
            $abs = abs($offsetSeconds);
            $h = intdiv($abs, 3600);
            $m = intdiv($abs % 3600, 60);
            $pdo->exec(sprintf("SET time_zone = '%s%02d:%02d'", $sign, $h, $m));
        } catch (Throwable) {
            // Leave server default if offset fails.
        }
    }
}
