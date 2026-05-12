<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';

class OfficerController extends BaseController
{
    private const FINE_AMOUNT_MIN = 1.0;
    private const FINE_AMOUNT_MAX = 10000.0;

    public static function dashboard(): void
    {
        require_role('officer');
        $pdo = Database::getConnection();
        
        // Use 'fines' table as 'violations' doesn't exist.
        // Map status 'open' to 'pending' to match fines table schema.
        $det_count = $pdo->query('SELECT COUNT(*) FROM fines')->fetchColumn();
        $flagged = $pdo->query("SELECT COUNT(*) FROM fines WHERE status='pending'")->fetchColumn();
        $recent = $pdo->query('SELECT f.*, u.name AS driver_name, ps.address FROM fines f JOIN users u ON f.driver_id = u.id JOIN parking_spots ps ON f.spot_id = ps.spot_id ORDER BY f.issued_at DESC LIMIT 20')->fetchAll();

        self::render('officer/dashboard', [
            'det_count' => $det_count,
            'flagged' => $flagged,
            'recent' => $recent,
            'pageTitle' => 'Officer Dashboard',
        ]);
    }

    public static function violation(): void
    {
        require_role('officer');
        $pdo = Database::getConnection();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $driver_id = (int)($_POST['driver_id'] ?? 0);
            $spot_id = (int)($_POST['spot_id'] ?? 0);
            $type = trim($_POST['vtype'] ?? 'unauthorized');
            $allowedTypes = ['unauthorized', 'overstay'];
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'unauthorized';
            }
            $penaltyRaw = trim((string)($_POST['penalty_amount'] ?? '50'));
            $penalty = (float)$penaltyRaw;
            
            $errs = [];
            if (!$driver_id) {
                $errs[] = 'Driver is required.';
            }
            if (!$spot_id) {
                $errs[] = 'Spot is required.';
            }
            if ($penaltyRaw === '' || !is_numeric($penaltyRaw) || $penalty < self::FINE_AMOUNT_MIN || $penalty > self::FINE_AMOUNT_MAX) {
                $errs[] = 'Penalty amount must be between ' . self::FINE_AMOUNT_MIN . ' and ' . self::FINE_AMOUNT_MAX . '.';
            }

            if ($errs === []) {
                // Use 'fines' table
                $pdo->prepare('INSERT INTO fines (driver_id, spot_id, type, penalty_amount, status) VALUES (?,?,?,?,?)')
                    ->execute([$driver_id, $spot_id, $type, $penalty, 'pending']);
                
                // Mirror admin behavior: recompute pending fines and suspend/blacklist at 3+.
                $unpaid = $pdo->prepare("SELECT COUNT(*) FROM fines WHERE driver_id=? AND status='pending'");
                $unpaid->execute([$driver_id]);
                $cnt = (int)$unpaid->fetchColumn();
                if ($cnt >= 3) {
                    $pdo->prepare('UPDATE drivers SET can_book=0, unpaid_fines=? WHERE driver_id=?')->execute([$cnt, $driver_id]);
                    $bl = $pdo->prepare('SELECT COUNT(*) FROM blacklist WHERE driver_id=?');
                    $bl->execute([$driver_id]);
                    if (!(int)$bl->fetchColumn()) {
                        $pdo->prepare('INSERT INTO blacklist (driver_id) VALUES (?)')->execute([$driver_id]);
                    }
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')
                        ->execute([$driver_id, 'in_app', 'Your account has been suspended due to 3+ unpaid fines.', 'suspension', 'sent']);
                }
                $pdo->prepare('UPDATE drivers SET unpaid_fines=? WHERE driver_id=?')->execute([$cnt, $driver_id]);
                
                flash('ok', 'Violation recorded.');
            } else {
                flash('err', implode(' ', $errs));
            }
            redirect(route_url('/officer/violation'));
        }

        $drivers = $pdo->query('SELECT id, name FROM users WHERE role="driver" ORDER BY name')->fetchAll();
        $spots = $pdo->query('SELECT spot_id, address FROM parking_spots ORDER BY address')->fetchAll();
        $recent_fines = $pdo->query('SELECT f.*, u.name AS driver_name, ps.address FROM fines f JOIN users u ON f.driver_id = u.id JOIN parking_spots ps ON f.spot_id = ps.spot_id ORDER BY f.issued_at DESC LIMIT 20')->fetchAll();

        self::render('officer/violation', [
            'drivers' => $drivers,
            'spots' => $spots,
            'recent_fines' => $recent_fines,
            'pageTitle' => 'Record Violation',
        ]);
    }
}
