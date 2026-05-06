<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/ParkingBookingValidator.php';

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
        $rows = $this->candidateSpotsNearby($referenceSpot, 100);

        foreach ($rows as &$row) {
            $row['distance_km'] = $this->distanceFromReferenceSpot($referenceSpot, $row);
        }
        unset($row);

        usort($rows, static fn(array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);
        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * Nearest alternatives that are free for the same one-time booking window (incl. buffer rules).
     * Each row includes fits_requested_window=true.
     *
     * @param array<string,mixed>|null $vehicle height_cm, width_cm, is_ev_capable or null = skip checks
     * @return list<array<string,mixed>>
     */
    public function getAlternativeSpotsForWindow(
        array $referenceSpot,
        string $start,
        string $end,
        int $limit = 5,
        ?array $vehicle = null
    ): array {
        $rows = $this->candidateSpotsDetailed($referenceSpot, 160);
        foreach ($rows as &$row) {
            $row['distance_km'] = $this->distanceFromReferenceSpot($referenceSpot, $row);
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);

        $out = [];
        foreach ($rows as $row) {
            if ($vehicle !== null && !$this->spotFitsVehicle($row, $vehicle)) {
                continue;
            }
            if (
                ParkingBookingValidator::reservationFitsOwnerAvailability(
                    $start,
                    $end,
                    $row['availability_start'] ?? null,
                    $row['availability_end'] ?? null
                ) !== null
            ) {
                continue;
            }
            $buff = max(0, (int)($row['buffer_duration_mins'] ?? 10));
            if ($this->hasBufferedConflict((int)$row['spot_id'], $start, $end, $buff)) {
                continue;
            }
            $row['fits_requested_window'] = true;
            $out[] = $this->alternativeRowPublic($row);
            if (count($out) >= max(1, $limit)) {
                break;
            }
        }

        return $out;
    }

    /**
     * Same spot free for every generated subscription slot (owner hours + buffers).
     *
     * @param list<array{start:string,end:string}> $slots
     * @return list<array<string,mixed>>
     */
    public function getAlternativeSpotsForSubscriptionSlots(
        array $referenceSpot,
        array $slots,
        int $limit = 5,
        ?array $vehicle = null
    ): array {
        if ($slots === []) {
            return [];
        }

        $rows = $this->candidateSpotsDetailed($referenceSpot, 160);
        foreach ($rows as &$row) {
            $row['distance_km'] = $this->distanceFromReferenceSpot($referenceSpot, $row);
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);

        $out = [];
        foreach ($rows as $row) {
            if ($vehicle !== null && !$this->spotFitsVehicle($row, $vehicle)) {
                continue;
            }
            $buff = max(0, (int)($row['buffer_duration_mins'] ?? 10));
            $ok = true;
            foreach ($slots as $slot) {
                if (
                    ParkingBookingValidator::reservationFitsOwnerAvailability(
                        $slot['start'],
                        $slot['end'],
                        $row['availability_start'] ?? null,
                        $row['availability_end'] ?? null
                    ) !== null
                ) {
                    $ok = false;
                    break;
                }
                if ($this->hasBufferedConflict((int)$row['spot_id'], $slot['start'], $slot['end'], $buff)) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                continue;
            }
            $row['fits_requested_window'] = true;
            $out[] = $this->alternativeRowPublic($row);
            if (count($out) >= max(1, $limit)) {
                break;
            }
        }

        return $out;
    }

    /**
     * Prefer alternatives that fit the requested window/time; pad with plain nearby spots.
     *
     * @param list<array{start:string,end:string}>|null $subscriptionSlots
     * @return list<array<string,mixed>>
     */
    public function getRankedAlternatives(
        array $referenceSpot,
        ?string $oneTimeStart,
        ?string $oneTimeEnd,
        ?array $subscriptionSlots,
        int $limit = 5,
        ?array $vehicle = null
    ): array {
        $limit = max(1, $limit);
        /** @var list<array<string,mixed>> $ordered */
        $ordered = [];
        $seen = [(int)$referenceSpot['spot_id'] => true];

        if (
            $oneTimeStart !== null && $oneTimeEnd !== null
            && $oneTimeStart !== '' && $oneTimeEnd !== ''
            && ($subscriptionSlots === null || $subscriptionSlots === [])
        ) {
            foreach (
                $this->getAlternativeSpotsForWindow(
                    $referenceSpot,
                    $oneTimeStart,
                    $oneTimeEnd,
                    max($limit * 4, $limit),
                    $vehicle
                ) as $r
            ) {
                $id = (int)$r['spot_id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $ordered[] = $r;
                if (count($ordered) >= $limit) {
                    return $ordered;
                }
            }
        }

        if ($subscriptionSlots !== null && $subscriptionSlots !== []) {
            foreach (
                $this->getAlternativeSpotsForSubscriptionSlots(
                    $referenceSpot,
                    $subscriptionSlots,
                    max($limit * 4, $limit),
                    $vehicle
                ) as $r
            ) {
                $id = (int)$r['spot_id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $ordered[] = $r;
                if (count($ordered) >= $limit) {
                    return $ordered;
                }
            }
        }

        foreach ($this->getAlternativeSpots($referenceSpot, 80) as $r) {
            $id = (int)$r['spot_id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $r['fits_requested_window'] = false;
            $ordered[] = $r;
            if (count($ordered) >= $limit) {
                break;
            }
        }

        return $ordered;
    }

    /** @param array<string,mixed> $row */
    private function alternativeRowPublic(array $row): array
    {
        return [
            'spot_id' => (int)$row['spot_id'],
            'address' => (string)$row['address'],
            'base_rate' => (float)$row['base_rate'],
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'distance_km' => (float)$row['distance_km'],
            'fits_requested_window' => !empty($row['fits_requested_window']),
        ];
    }

    /**
     * @param array<string,mixed>|null $veh from vehicle_profiles
     */
    private function spotFitsVehicle(array $spotRow, array $veh): bool
    {
        $vh = $veh['height_cm'] ?? null;
        $vw = $veh['width_cm'] ?? null;
        $ev = !empty($veh['is_ev_capable']);

        $sh = $spotRow['height_cm'] ?? null;
        $sw = $spotRow['width_cm'] ?? null;

        if ($vh && $sh && (float)$vh > (float)$sh) {
            return false;
        }
        if ($vw && $sw && (float)$vw > (float)$sw) {
            return false;
        }
        if ($ev && empty($spotRow['has_ev_charger'])) {
            return false;
        }

        return true;
    }

    private function distanceFromReferenceSpot(array $referenceSpot, array $candidateRow): float
    {
        $refLat = $this->coordinateOrMock($referenceSpot['latitude'] ?? null, (int)$referenceSpot['spot_id'], 0);
        $refLng = $this->coordinateOrMock($referenceSpot['longitude'] ?? null, (int)$referenceSpot['spot_id'], 1);
        $lat = $this->coordinateOrMock($candidateRow['latitude'] ?? null, (int)$candidateRow['spot_id'], 0);
        $lng = $this->coordinateOrMock($candidateRow['longitude'] ?? null, (int)$candidateRow['spot_id'], 1);

        return $this->distanceKm($refLat, $refLng, $lat, $lng);
    }

    /** @return list<array<string,mixed>> */
    private function candidateSpotsNearby(array $referenceSpot, int $maxRows): array
    {
        $exclude = (int)$referenceSpot['spot_id'];
        $stmt = $this->pdo->prepare(
            'SELECT ps.spot_id, ps.address, ps.base_rate, ps.latitude, ps.longitude
             FROM parking_spots ps
             LEFT JOIN zones z ON z.zone_id = ps.zone_id
             WHERE ps.status = "available"
               AND ps.spot_id != ?
               AND (z.status = "active" OR ps.zone_id IS NULL)
             LIMIT ' . max(10, min(200, $maxRows))
        );
        $stmt->execute([$exclude]);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function candidateSpotsDetailed(array $referenceSpot, int $maxRows): array
    {
        $exclude = (int)$referenceSpot['spot_id'];
        $stmt = $this->pdo->prepare(
            'SELECT ps.spot_id, ps.address, ps.base_rate, ps.latitude, ps.longitude,
                    ps.height_cm, ps.width_cm, ps.has_ev_charger,
                    ps.availability_start, ps.availability_end,
                    COALESCE(bm.buffer_duration_mins, 10) AS buffer_duration_mins
             FROM parking_spots ps
             LEFT JOIN zones z ON z.zone_id = ps.zone_id
             LEFT JOIN buffer_manager bm ON bm.spot_id = ps.spot_id
             WHERE ps.status = "available"
               AND ps.spot_id != ?
               AND (z.status = "active" OR ps.zone_id IS NULL)
             LIMIT ' . max(10, min(200, $maxRows))
        );
        $stmt->execute([$exclude]);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll() ?: [];
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
