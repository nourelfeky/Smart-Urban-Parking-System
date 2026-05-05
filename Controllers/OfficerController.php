<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';

class OfficerController extends BaseController
{
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
            $penalty = (float)($_POST['penalty_amount'] ?? 50);
            
            if ($driver_id && $spot_id) {
                // Use 'fines' table
                $pdo->prepare('INSERT INTO fines (driver_id, spot_id, type, penalty_amount, status) VALUES (?,?,?,?,?)')
                    ->execute([$driver_id, $spot_id, $type, $penalty, 'pending']);
                
                $pdo->prepare('UPDATE drivers SET unpaid_fines = unpaid_fines + 1 WHERE driver_id=?')->execute([$driver_id]);
                
                flash('ok', 'Violation recorded.');
            } else {
                flash('err', 'Driver and Spot are required.');
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
