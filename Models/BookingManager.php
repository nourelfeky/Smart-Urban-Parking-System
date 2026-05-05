<?php

require_once __DIR__ . '/../Core/Database.php';

class BookingManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureSubscriptionSchema(): void
    {
        // Subscription model for recurring commuter bookings.
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS commuter_subscriptions (
                subscription_id INT AUTO_INCREMENT PRIMARY KEY,
                driver_id INT NOT NULL,
                spot_id INT NOT NULL,
                vehicle_id INT NOT NULL,
                days_of_week VARCHAR(32) NOT NULL,
                start_time_of_day TIME NOT NULL,
                end_time_of_day TIME NOT NULL,
                weeks INT NOT NULL,
                discount_percent DECIMAL(5,2) NOT NULL DEFAULT 15.00,
                status VARCHAR(20) NOT NULL DEFAULT "active",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $col = $this->pdo->prepare("SHOW COLUMNS FROM reservations LIKE 'subscription_id'");
        $col->execute();
        if (!$col->fetch()) {
            $this->pdo->exec('ALTER TABLE reservations ADD COLUMN subscription_id INT NULL AFTER payment_id');
        }
    }

    public function getBufferMinutes(array $spot): int
    {
        return max(0, (int)($spot['buffer_duration_mins'] ?? 10));
    }

    /**
     * Detect double-booking vs other reservations, including OUR buffer AFTER $end:
     * we occupy [start, end + bufferMinutes], others occupy [their start, their buffer_end_time].
     * Condition for no overlap: other.buffer_end <= start OR start_time >= endWithBufferExclusiveCheck
     * i.e. other.buffer_end <= ourStart OR other.start_time >= ourEndAfterBuffer
     *
     * @param positive-int $bufferMinsAfterEnd buffer minutes appended after nominal end_time
     */
    public function hasBufferedConflict(int $spotId, string $start, string $end, int $bufferMinsAfterEnd, ?int $excludeReservationId = null): bool
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        if ($startTs === false || $endTs === false) {
            // Invalid range should never be persisted; fail closed to prevent bad inserts.
            return true;
        }
        $buffEndTs = $endTs + max(0, $bufferMinsAfterEnd) * 60;
        $bufferEndSql = date('Y-m-d H:i:s', $buffEndTs);
        $startSql = date('Y-m-d H:i:s', $startTs);

        $sql = 'SELECT COUNT(*) FROM reservations
                WHERE spot_id = ?
                AND status IN ("confirmed","active")
                AND NOT (buffer_end_time <= ? OR start_time >= ?)';
        $params = [$spotId, $startSql, $bufferEndSql];
        if ($excludeReservationId !== null) {
            $sql .= ' AND reservation_id != ?';
            $params[] = $excludeReservationId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getAlternativeSpots(array $referenceSpot, int $limit = 3): array
    {
        $refLat = $this->coordinateOrMock($referenceSpot['latitude'] ?? null, (int)$referenceSpot['spot_id'], 0);
        $refLng = $this->coordinateOrMock($referenceSpot['longitude'] ?? null, (int)$referenceSpot['spot_id'], 1);
        $exclude = (int)$referenceSpot['spot_id'];

        $stmt = $this->pdo->prepare(
            'SELECT ps.spot_id, ps.address, ps.base_rate, ps.latitude, ps.longitude
             FROM parking_spots ps
             LEFT JOIN zones z ON z.zone_id = ps.zone_id
             WHERE ps.status = "available"
               AND ps.spot_id != ?
               AND (z.status = "active" OR ps.zone_id IS NULL)
             LIMIT 100'
        );
        $stmt->execute([$exclude]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $lat = $this->coordinateOrMock($row['latitude'] ?? null, (int)$row['spot_id'], 0);
            $lng = $this->coordinateOrMock($row['longitude'] ?? null, (int)$row['spot_id'], 1);
            $row['distance_km'] = $this->distanceKm($refLat, $refLng, $lat, $lng);
        }
        unset($row);

        usort($rows, static fn(array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);
        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * Build recurring reservation slots inside [periodStartInclusive, periodEndInclusive] (calendar dates).
     *
     * @param non-empty-array<int> $allowedDaysWeekdayN ISO-8601 weekdays 1=Mon … 7=Sun
     * @param non-empty-string $startTimeHms H:i:s
     */
    public function generateSubscriptionSlots(
        string $periodStartInclusive,
        string $periodEndInclusive,
        array $allowedDaysWeekdayN,
        string $startTimeHms,
        string $endTimeHms
    ): array {
        $slots = [];
        $allowedDays = array_values(array_unique(array_map(static fn (mixed $d): int => (int)$d, $allowedDaysWeekdayN)));

        try {
            $cursor = new DateTimeImmutable($periodStartInclusive . ' 00:00:00');
            $endBound = new DateTimeImmutable($periodEndInclusive . ' 23:59:59');
        } catch (Exception) {
            return [];
        }

        while ($cursor <= $endBound) {
            $dow = (int)$cursor->format('N');
            if (in_array($dow, $allowedDays, true)) {
                $dayStr = $cursor->format('Y-m-d');
                $start = $dayStr . ' ' . $startTimeHms;
                $end = $dayStr . ' ' . $endTimeHms;
                $st = strtotime($start);
                $en = strtotime($end);
                if ($st !== false && $en !== false && $st < $en && $st >= time()) {
                    $slots[] = ['start' => date('Y-m-d H:i:s', $st), 'end' => date('Y-m-d H:i:s', $en)];
                }
            }
            try {
                $cursor = $cursor->modify('+1 day');
            } catch (Exception) {
                break;
            }
        }

        return $slots;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earth * $c, 2);
    }

    private function coordinateOrMock(mixed $coordinate, int $spotId, int $axis): float
    {
        if ($coordinate !== null && $coordinate !== '') {
            return (float)$coordinate;
        }
        // Assumption: when coordinates are missing, derive deterministic mock coordinates from spot_id.
        $base = $axis === 0 ? 30.0444 : 31.2357;
        return $base + (($spotId % 100) / 1000);
    }
}
