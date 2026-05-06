<?php

final class WaitlistModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $t = $this->pdo->query("SHOW TABLES LIKE 'zone_watchlist'");
        if (!$t->fetch()) {
            $this->pdo->exec(
                "CREATE TABLE zone_watchlist (
                    watch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    zone_id INT UNSIGNED NOT NULL,
                    driver_id INT UNSIGNED NOT NULL,
                    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_zone_driver (zone_id, driver_id),
                    FOREIGN KEY (zone_id) REFERENCES zones(zone_id) ON DELETE CASCADE,
                    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }
    }

    public function joinZoneWatch(int $driverId, int $zoneId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO zone_watchlist (zone_id, driver_id) VALUES (?, ?)');
        $stmt->execute([$zoneId, $driverId]);
    }

    public function leaveZoneWatch(int $driverId, int $zoneId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM zone_watchlist WHERE zone_id=? AND driver_id=?');
        $stmt->execute([$zoneId, $driverId]);
    }

    /**
     * Notify watchers when a spot becomes available.
     * This is called from checkout / owner toggles.
     */
    public function notifySpotAvailable(int $spotId): void
    {
        $spotStmt = $this->pdo->prepare(
            'SELECT ps.spot_id, ps.zone_id, ps.address
             FROM parking_spots ps
             WHERE ps.spot_id = ?
             LIMIT 1'
        );
        $spotStmt->execute([$spotId]);
        $spot = $spotStmt->fetch();
        if (!$spot) {
            return;
        }

        // 1) Spot-specific waitlist (existing table)
        $w = $this->pdo->prepare('SELECT driver_id FROM waitlist WHERE spot_id=? ORDER BY joined_at ASC');
        $w->execute([$spotId]);
        $drivers = $w->fetchAll();
        foreach ($drivers as $row) {
            $this->pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')
                ->execute([(int)$row['driver_id'], 'in_app', "A spot you watched is now available: {$spot['address']}.", 'waitlist', 'sent']);
        }

        // 2) Zone watchlist (new)
        if (!empty($spot['zone_id'])) {
            $zw = $this->pdo->prepare('SELECT driver_id FROM zone_watchlist WHERE zone_id=? ORDER BY joined_at ASC');
            $zw->execute([(int)$spot['zone_id']]);
            $zdrivers = $zw->fetchAll();
            foreach ($zdrivers as $row) {
                $this->pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')
                    ->execute([(int)$row['driver_id'], 'in_app', "A spot is now available in a zone you watched: {$spot['address']}.", 'waitlist', 'sent']);
            }
        }
    }
}

