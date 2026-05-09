<?php

declare(strict_types=1);

/**
 * Driver wallet credits (refunds, dispute payouts). Balance shown on driver dashboard.
 */
final class DriverWalletModel
{
    public static function ensureSchema(PDO $pdo): void
    {
        $col = $pdo->query("SHOW COLUMNS FROM drivers LIKE 'wallet_balance'");
        if (!$col->fetch()) {
            $pdo->exec(
                'ALTER TABLE drivers ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER push_enabled'
            );
        }
    }

    public static function credit(PDO $pdo, int $driverId, float $amount): void
    {
        if ($driverId <= 0 || $amount <= 0.00001) {
            return;
        }
        self::ensureSchema($pdo);
        $pdo->prepare('UPDATE drivers SET wallet_balance = wallet_balance + ? WHERE driver_id=?')
            ->execute([round($amount, 2), $driverId]);
    }

    public static function getBalance(PDO $pdo, int $driverId): float
    {
        self::ensureSchema($pdo);
        $st = $pdo->prepare('SELECT wallet_balance FROM drivers WHERE driver_id=? LIMIT 1');
        $st->execute([$driverId]);
        $v = $st->fetchColumn();

        return $v !== false ? (float)$v : 0.0;
    }
}
