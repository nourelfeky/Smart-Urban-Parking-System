<?php

/**
 * Spot listing approval: owner uploads documents per spot; admin approves before drivers can book.
 */
final class SpotApprovalModel
{
    public const STATUS_PENDING_DOCS = 'pending_documents';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $col = $this->pdo->query("SHOW COLUMNS FROM parking_spots LIKE 'spot_approval_status'");
        if (!$col->fetch()) {
            $this->pdo->exec(
                "ALTER TABLE parking_spots ADD COLUMN spot_approval_status VARCHAR(32) NOT NULL DEFAULT 'approved' AFTER status"
            );
        }

        $t = $this->pdo->query("SHOW TABLES LIKE 'spot_document_submissions'");
        if (!$t->fetch()) {
            $this->pdo->exec(
                "CREATE TABLE spot_document_submissions (
                    submission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    spot_id INT UNSIGNED NOT NULL,
                    owner_id INT UNSIGNED NOT NULL,
                    document_paths TEXT NOT NULL,
                    admin_note VARCHAR(512) DEFAULT NULL,
                    review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    reviewed_at DATETIME DEFAULT NULL,
                    INDEX idx_spot_status (spot_id, review_status),
                    FOREIGN KEY (spot_id) REFERENCES parking_spots(spot_id) ON DELETE CASCADE,
                    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }
    }

    /** SQL fragment: spot is visible/bookable by drivers when approved. */
    public static function isApprovedSql(string $alias = 'ps'): string
    {
        return "COALESCE(NULLIF(TRIM({$alias}.spot_approval_status), ''), 'approved') = 'approved'";
    }
}
