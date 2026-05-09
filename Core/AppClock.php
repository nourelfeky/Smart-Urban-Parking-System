<?php

declare(strict_types=1);

/**
 * Single source of truth for "now" in the application layer.
 * Requires bootstrap to call date_default_timezone_set(APP_TIMEZONE).
 */
final class AppClock
{
    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC');
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    /** MySQL DATETIME string in the application timezone (matches SESSION time_zone on PDO). */
    public static function nowSql(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function timestamp(): int
    {
        return self::now()->getTimestamp();
    }

    /**
     * Parse naive DATETIME from MySQL as wall-clock in APP_TIMEZONE (same as stored values).
     */
    public static function parseSqlDatetime(string $sql): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $sql, self::timezone());
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
        // Fallback for alternate formatting from drivers
        return new DateTimeImmutable($sql, self::timezone());
    }
}
