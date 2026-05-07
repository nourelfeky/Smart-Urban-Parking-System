<?php

require_once __DIR__ . '/../Core/SimplePdf.php';

final class OwnerReportModel
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Deterministic pseudo-random int for "fake" reports (no global RNG state).
     */
    private function prandInt(string $seed, int $min, int $max): int
    {
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }
        $hex = substr(hash('sha256', $seed), 0, 8);
        $n = hexdec($hex);
        $range = max(1, $max - $min + 1);
        return $min + ($n % $range);
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
    public function getFakeMonthlyOwnerMetrics(int $ownerId, string $month): array
    {
        // month format: YYYY-MM
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');
        $days = (int)$monthStart->diff($monthEnd)->days;

        // Prefer real spot count for credibility; if owner has none, still show a fake report.
        $spotStmt = $this->pdo->prepare('SELECT COUNT(*) FROM parking_spots WHERE owner_id = ?');
        $spotStmt->execute([$ownerId]);
        $spotCount = (int)$spotStmt->fetchColumn();
        if ($spotCount <= 0) {
            $spotCount = $this->prandInt("{$ownerId}|{$month}|spots", 3, 9);
        }

        $availableMinutes = max(0, $spotCount * $days * 24 * 60);

        // "Realistic" occupancy: 18%..78%
        $occPercent = (float)$this->prandInt("{$ownerId}|{$month}|occ", 18, 78);
        $bookedMinutes = (int)round(($occPercent / 100.0) * $availableMinutes);

        // Fake top slots: 5 hours, descending sessions.
        $baseSessions = $this->prandInt("{$ownerId}|{$month}|baseSessions", 18, 95);
        $hoursPool = range(6, 22);
        $topSlots = [];
        for ($i = 0; $i < 5; $i++) {
            $hIdx = $this->prandInt("{$ownerId}|{$month}|hour|{$i}", 0, count($hoursPool) - 1);
            $hour = $hoursPool[$hIdx];
            unset($hoursPool[$hIdx]);
            $hoursPool = array_values($hoursPool);

            $sessions = max(1, $baseSessions - ($i * $this->prandInt("{$ownerId}|{$month}|drop|{$i}", 4, 14)));
            $topSlots[] = ['hour' => (int)$hour, 'sessions' => (int)$sessions];
        }

        usort(
            $topSlots,
            static fn(array $a, array $b): int => ($b['sessions'] <=> $a['sessions']) ?: ($a['hour'] <=> $b['hour'])
        );

        return [
            'month' => $month,
            'spot_count' => $spotCount,
            'booked_minutes' => $bookedMinutes,
            'available_minutes' => $availableMinutes,
            'occupancy_rate' => round($occPercent, 2),
            'top_slots' => $topSlots,
        ];
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

    /**
     * @return array<int, array{date: string, sessions: int, gross: float}>
     */
    public function getDailySessions(int $ownerId, string $month): array
    {
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');

        $stmt = $this->pdo->prepare(
            'SELECT DATE(r.start_time) AS day,
                    COUNT(*) AS sessions,
                    COALESCE(SUM(r.final_cost), 0) AS gross
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?
             GROUP BY day
             ORDER BY day ASC'
        );
        $stmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll() ?: [];

        // Fill missing days with zeros so the line chart is continuous.
        $map = [];
        foreach ($rows as $r) {
            $d = (string)($r['day'] ?? '');
            if ($d !== '') {
                $map[$d] = [
                    'date' => $d,
                    'sessions' => (int)($r['sessions'] ?? 0),
                    'gross' => (float)($r['gross'] ?? 0),
                ];
            }
        }

        $out = [];
        $cursor = clone $monthStart;
        while ($cursor < $monthEnd) {
            $d = $cursor->format('Y-m-d');
            $out[] = $map[$d] ?? ['date' => $d, 'sessions' => 0, 'gross' => 0.0];
            $cursor->modify('+1 day');
        }

        return $out;
    }

    /**
     * @return array<int, array{hour: int, sessions: int}>
     */
    public function getHourlySessions(int $ownerId, string $month): array
    {
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');

        $stmt = $this->pdo->prepare(
            'SELECT HOUR(r.start_time) AS hour,
                    COUNT(*) AS sessions
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?
             GROUP BY hour
             ORDER BY hour ASC'
        );
        $stmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $r) {
            $h = (int)($r['hour'] ?? 0);
            $map[$h] = (int)($r['sessions'] ?? 0);
        }

        $out = [];
        for ($h = 0; $h <= 23; $h++) {
            $out[] = ['hour' => $h, 'sessions' => (int)($map[$h] ?? 0)];
        }
        return $out;
    }

    /**
     * @param array{
     *   month: string,
     *   spot_count: int,
     *   booked_minutes: int,
     *   available_minutes: int,
     *   occupancy_rate: float,
     *   top_slots: array<int, array{hour: int, sessions: int}>
     * } $metrics
     * @param array<int, array{date: string, sessions: int, gross: float}> $daily
     * @param array<int, array{hour: int, sessions: int}> $hourly
     */
    public function downloadMonthlyPdf(string $month, string $ownerName, array $metrics, array $daily, array $hourly): void
    {
        $cTitle = [15, 23, 42];
        $cSection = [30, 64, 175];
        $cAccent = [2, 132, 199];
        $cBody = [31, 41, 55];
        $cMuted = [71, 85, 105];

        $line = str_repeat('-', 74);
        $generatedAt = date('Y-m-d H:i:s');
        $monthLabel = DateTime::createFromFormat('Y-m', $month)?->format('F Y') ?? $month;

        $pdf = new SimplePdf("Owner Monthly Report - {$month}");
        $pdf->addLine($line, $cMuted);
        $pdf->addLine("SMART URBAN PARKING SYSTEM - MONTHLY OWNER REPORT", $cTitle, 12);
        $pdf->addLine($line, $cMuted);
        $pdf->addLine("Report Month      : {$monthLabel}", $cBody);
        $pdf->addLine("Space Owner       : {$ownerName}", $cBody);
        $pdf->addLine("Generated At      : {$generatedAt}", $cBody);
        $pdf->addLine("Data Source       : Live statistics and analytics", $cBody);
        $pdf->addLine("");
        $pdf->addLine("1) EXECUTIVE SUMMARY", $cSection, 12);
        $pdf->addLine($line, $cMuted);

        $totalSessions = 0;
        $totalGross = 0.0;
        $bestDay = null;
        foreach ($daily as $row) {
            $sessions = (int)($row['sessions'] ?? 0);
            $gross = (float)($row['gross'] ?? 0);
            $totalSessions += $sessions;
            $totalGross += $gross;
            if ($bestDay === null || $sessions > $bestDay['sessions']) {
                $bestDay = ['date' => (string)$row['date'], 'sessions' => $sessions];
            }
        }

        $peakHour = ['hour' => 0, 'sessions' => 0];
        foreach ($hourly as $row) {
            $sessions = (int)($row['sessions'] ?? 0);
            if ($sessions > $peakHour['sessions']) {
                $peakHour = ['hour' => (int)$row['hour'], 'sessions' => $sessions];
            }
        }

        $occupancyRate = (float)($metrics['occupancy_rate'] ?? 0.0);
        $performanceBand = 'Low';
        if ($occupancyRate >= 60.0) {
            $performanceBand = 'Excellent';
        } elseif ($occupancyRate >= 40.0) {
            $performanceBand = 'Good';
        } elseif ($occupancyRate >= 25.0) {
            $performanceBand = 'Moderate';
        }

        $pdf->addLine("This month recorded {$totalSessions} sessions with "
            . number_format($totalGross, 2) . " EGP in gross revenue.", $cBody);
        $pdf->addLine("Overall utilization is " . number_format($occupancyRate, 2)
            . "% ({$performanceBand} performance band).", $cAccent);

        $pdf->addLine("");
        $pdf->addLine("2) KEY PERFORMANCE INDICATORS", $cSection, 12);
        $pdf->addLine($line, $cMuted);
        $pdf->addLine("Active Spaces      : " . number_format((int)($metrics['spot_count'] ?? 0)), $cBody);
        $pdf->addLine("Booked Minutes     : " . number_format((int)($metrics['booked_minutes'] ?? 0)), $cBody);
        $pdf->addLine("Available Minutes  : " . number_format((int)($metrics['available_minutes'] ?? 0)), $cBody);
        $pdf->addLine("Occupancy Rate     : " . number_format($occupancyRate, 2) . "%", $cAccent);
        $pdf->addLine("Gross Revenue      : " . number_format($totalGross, 2) . " EGP", $cAccent);

        $pdf->addLine("");
        $pdf->addLine("3) DEMAND INSIGHTS", $cSection, 12);
        $pdf->addLine($line, $cMuted);
        if ($bestDay !== null && $bestDay['date'] !== '') {
            $pdf->addLine("Busiest Day        : {$bestDay['date']} ({$bestDay['sessions']} sessions)", $cBody);
        } else {
            $pdf->addLine("Busiest Day        : No completed session data", $cBody);
        }
        $peakHourLabel = str_pad((string)$peakHour['hour'], 2, '0', STR_PAD_LEFT) . ':00';
        $pdf->addLine("Peak Start Hour    : {$peakHourLabel} ({$peakHour['sessions']} sessions)", $cBody);

        $pdf->addLine("Top Time Slots     :", $cAccent);
        if (empty($metrics['top_slots'])) {
            $pdf->addLine("  - No bookings in this month.", $cMuted);
        } else {
            foreach ($metrics['top_slots'] as $row) {
                $h = str_pad((string)$row['hour'], 2, '0', STR_PAD_LEFT);
                $pdf->addLine("  - {$h}:00  |  " . (int)$row['sessions'] . " sessions", $cBody);
            }
        }

        $topDays = $daily;
        usort($topDays, static fn(array $a, array $b): int => ((int)$b['sessions']) <=> ((int)$a['sessions']));
        $topDays = array_values(array_filter($topDays, static fn(array $r): bool => (int)($r['sessions'] ?? 0) > 0));
        $topDays = array_slice($topDays, 0, 3);

        if ($topDays !== []) {
            $pdf->addLine("Top Days           :", $cAccent);
            foreach ($topDays as $d) {
                $pdf->addLine("  - {$d['date']}  |  " . (int)$d['sessions']
                    . " sessions | " . number_format((float)$d['gross'], 2) . " EGP", $cBody);
            }
        }

        $pdf->addLine("");
        $pdf->addLine("4) RECOMMENDED ACTIONS", $cSection, 12);
        $pdf->addLine($line, $cMuted);
        $pdf->addLine("- Keep higher availability during peak hours to capture more demand.", $cBody);
        $pdf->addLine("- Consider dynamic pricing for top-performing hours and days.", $cBody);
        $pdf->addLine("- Review low-demand periods and run promotions to improve occupancy.", $cBody);

        $pdf->addLine("");
        $pdf->addLine($line, $cMuted);
        $pdf->addLine("End of Report", $cMuted);

        $pdf->outputDownload("owner_report_{$month}.pdf");
    }
}

