<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

/**
 * Singleton global configuration (class diagram: ParkingSystemConfig).
 */
final class ParkingSystemConfig
{
    private static ?self $instance = null;

    public float $baseHourlyRate;
    public float $taxRate;
    public int $spotCount;

    private function __construct()
    {
        $pdo = Database::getConnection();
        $this->spotCount = (int)$pdo->query('SELECT COUNT(*) FROM parking_spots')->fetchColumn();
        $avg = $pdo->query('SELECT AVG(base_rate) FROM parking_spots')->fetchColumn();
        $this->baseHourlyRate = $avg !== null ? round((float)$avg, 2) : 25.0;

        try {
            $taxRow = $pdo->query('SELECT vat_rate FROM tax_engine ORDER BY tax_id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $taxRow = null;
        }
        if ($taxRow && isset($taxRow['vat_rate'])) {
            $this->taxRate = (float)$taxRow['vat_rate'];
        } else {
            $this->taxRate = 0.14;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
