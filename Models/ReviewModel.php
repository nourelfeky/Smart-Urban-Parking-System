<?php

final class ReviewModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        // owner_reviews table
        $t = $this->pdo->query("SHOW TABLES LIKE 'owner_reviews'");
        if (!$t->fetch()) {
            $this->pdo->exec(
                "CREATE TABLE owner_reviews (
                    review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    owner_id INT UNSIGNED NOT NULL,
                    driver_id INT UNSIGNED NOT NULL,
                    reservation_id INT UNSIGNED DEFAULT NULL,
                    rating TINYINT UNSIGNED NOT NULL,
                    comment TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_owner_driver_res (owner_id, driver_id, reservation_id),
                    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // trust_score column on space_owners
        $col = $this->pdo->query("SHOW COLUMNS FROM space_owners LIKE 'trust_score'");
        if (!$col->fetch()) {
            $this->pdo->exec("ALTER TABLE space_owners ADD COLUMN trust_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER earnings_balance");
        }

        $col = $this->pdo->query("SHOW COLUMNS FROM space_owners LIKE 'trust_score_updated_at'");
        if (!$col->fetch()) {
            $this->pdo->exec("ALTER TABLE space_owners ADD COLUMN trust_score_updated_at DATETIME DEFAULT NULL AFTER trust_score");
        }
    }

    public function hasReviewForReservation(int $ownerId, int $driverId, int $reservationId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM owner_reviews WHERE owner_id=? AND driver_id=? AND reservation_id=? LIMIT 1');
        $stmt->execute([$ownerId, $driverId, $reservationId]);
        return (bool)$stmt->fetchColumn();
    }

    public function addOwnerReview(int $ownerId, int $driverId, int $reservationId, int $rating, string $comment = ''): void
    {
        $rating = max(1, min(5, $rating));
        $comment = trim($comment);
        $stmt = $this->pdo->prepare('INSERT INTO owner_reviews (owner_id, driver_id, reservation_id, rating, comment) VALUES (?,?,?,?,?)');
        $stmt->execute([$ownerId, $driverId, $reservationId, $rating, $comment ?: null]);
    }

    /**
     * Weighted trust score (0..100):
     * - driver activity weight: 1 + log10(1 + completed_bookings_by_driver), capped at 3
     * - recency weight: exp(-days_since_review / 180)
     * - final score: weighted avg rating (1..5) * 20
     */
    public function recomputeOwnerTrustScore(int $ownerId): float
    {
        $stmt = $this->pdo->prepare('SELECT driver_id, rating, created_at FROM owner_reviews WHERE owner_id=?');
        $stmt->execute([$ownerId]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            $this->pdo->prepare('UPDATE space_owners SET trust_score=0, trust_score_updated_at=NOW() WHERE owner_id=?')
                ->execute([$ownerId]);
            return 0.0;
        }

        $driverIds = array_values(array_unique(array_map(static fn($r) => (int)$r['driver_id'], $rows)));
        $activity = $this->getDriverCompletedBookingCounts($driverIds);

        $wSum = 0.0;
        $xSum = 0.0;
        $now = new DateTime();

        foreach ($rows as $r) {
            $did = (int)$r['driver_id'];
            $rating = (int)$r['rating'];
            $created = new DateTime((string)$r['created_at']);
            $days = max(0, (int)$created->diff($now)->days);

            $actCount = (int)($activity[$did] ?? 0);
            $activityWeight = min(3.0, 1.0 + log10(1.0 + $actCount));
            $recencyWeight = exp(-$days / 180.0);
            $w = $activityWeight * $recencyWeight;

            $wSum += $w;
            $xSum += $w * $rating;
        }

        $avg = $wSum > 0 ? ($xSum / $wSum) : 0.0; // 1..5
        $score = round(max(0.0, min(100.0, $avg * 20.0)), 2);

        $this->pdo->prepare('UPDATE space_owners SET trust_score=?, trust_score_updated_at=NOW() WHERE owner_id=?')
            ->execute([$score, $ownerId]);

        return $score;
    }

    /**
     * @param list<int> $driverIds
     * @return array<int,int> map driver_id => completed bookings count
     */
    private function getDriverCompletedBookingCounts(array $driverIds): array
    {
        if ($driverIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($driverIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT driver_id, COUNT(*) AS c
             FROM reservations
             WHERE status='completed' AND driver_id IN ($placeholders)
             GROUP BY driver_id"
        );
        $stmt->execute($driverIds);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['driver_id']] = (int)$row['c'];
        }
        return $out;
    }
}

