<?php

require_once __DIR__ . '/../Core/SimplePdf.php';

final class OwnerReportModel
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *   month: string,
     *   spot_count: int,
     *   booked_minutes: int,
     *   available_minutes: int,
     *   occupancy_rate: float,
     *   top_slots: array<int, array{hour: int, sessions: int}>
     * }
     */
    public function getMonthlyOwnerMetrics(int $ownerId, string $month): array
    {
        // month format: YYYY-MM
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');

        $spotStmt = $this->pdo->prepare('SELECT COUNT(*) FROM parking_spots WHERE owner_id = ?');
        $spotStmt->execute([$ownerId]);
        $spotCount = (int)$spotStmt->fetchColumn();

        // Total booked minutes (simple assumption: reservations are within month).
        $bookedStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)), 0)
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?'
        );
        $bookedStmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $bookedMinutes = (int)$bookedStmt->fetchColumn();

        // Available minutes = spots * minutes in month (simple, ignores availability_start/end).
        $days = (int)$monthStart->diff($monthEnd)->days;
        $availableMinutes = max(0, $spotCount * $days * 24 * 60);
        $occ = $availableMinutes > 0 ? ($bookedMinutes / $availableMinutes) : 0.0;

        $topStmt = $this->pdo->prepare(
            'SELECT HOUR(r.start_time) AS hour, COUNT(*) AS sessions
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?
             GROUP BY hour
             ORDER BY sessions DESC
             LIMIT 5'
        );
        $topStmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $top = $topStmt->fetchAll() ?: [];

        $topSlots = array_map(
            static fn(array $row): array => ['hour' => (int)$row['hour'], 'sessions' => (int)$row['sessions']],
            $top
        );

        return [
            'month' => $month,
            'spot_count' => $spotCount,
            'booked_minutes' => $bookedMinutes,
            'available_minutes' => $availableMinutes,
            'occupancy_rate' => round($occ * 100, 2),
            'top_slots' => $topSlots,
        ];
    }

    public function downloadMonthlyPdf(int $ownerId, string $month, string $ownerName): void
    {
        $m = $this->getMonthlyOwnerMetrics($ownerId, $month);

        $pdf = new SimplePdf("Owner Monthly Report - {$month}");
        $pdf->addLine("Owner: {$ownerName}");
        $pdf->addLine("Spots: " . $m['spot_count']);
        $pdf->addLine("Occupancy rate: " . number_format($m['occupancy_rate'], 2) . "%");
        $pdf->addLine("Booked minutes: " . $m['booked_minutes']);
        $pdf->addLine("");
        $pdf->addLine("Top time slots (by booking start hour):");
        if (empty($m['top_slots'])) {
            $pdf->addLine("  - No bookings in this month.");
        } else {
            foreach ($m['top_slots'] as $row) {
                $h = str_pad((string)$row['hour'], 2, '0', STR_PAD_LEFT);
                $pdf->addLine("  - {$h}:00 — {$row['sessions']} sessions");
            }
        }

        $pdf->outputDownload("owner_report_{$month}.pdf");
    }
}

