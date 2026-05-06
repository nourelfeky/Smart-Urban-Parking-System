<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/ParkingBookingValidator.php';
require_once __DIR__ . '/../Models/OwnerReportModel.php';
require_once __DIR__ . '/../Models/WaitlistModel.php';
require_once __DIR__ . '/../Models/ReviewModel.php';

class OwnerController extends BaseController
{
    public static function dashboard(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];

        // Ensures review/trust-score columns exist for older DBs.
        new ReviewModel($pdo);

        $spotCount = $pdo->prepare('SELECT COUNT(*) FROM parking_spots WHERE owner_id=?');
        $spotCount->execute([$uid]);
        $spot_count = $spotCount->fetchColumn();

        $rev = $pdo->prepare('SELECT COALESCE(SUM(r.final_cost), 0) FROM reservations r JOIN parking_spots ps ON r.spot_id = ps.spot_id WHERE ps.owner_id = ? AND r.status = "completed"');
        $rev->execute([$uid]);
        $total_rev = $rev->fetchColumn();

        $ownerStmt = $pdo->prepare('SELECT earnings_balance, verification_status, trust_score FROM space_owners WHERE owner_id=?');
        $ownerStmt->execute([$uid]);
        $odata = $ownerStmt->fetch();

        $recentStmt = $pdo->prepare('SELECT r.reservation_id, r.start_time, r.end_time, r.status, r.final_cost, u.name AS driver_name, ps.address FROM reservations r JOIN parking_spots ps ON r.spot_id = ps.spot_id JOIN users u ON r.driver_id = u.id WHERE ps.owner_id = ? ORDER BY r.created_at DESC LIMIT 6');
        $recentStmt->execute([$uid]);
        $recent = $recentStmt->fetchAll();

        self::render('owner/dashboard', [
            'spot_count' => $spot_count,
            'total_rev' => $total_rev,
            'odata' => $odata,
            'recent' => $recent,
            'pageTitle' => 'Owner Dashboard',
        ]);
    }

    public static function reports(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = (int)$u['id'];

        $month = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $metrics = (new OwnerReportModel($pdo))->getMonthlyOwnerMetrics($uid, $month);
        self::render('owner/reports', [
            'month' => $month,
            'metrics' => $metrics,
            'pageTitle' => 'Monthly Reports',
        ]);
    }

    public static function reportPdf(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = (int)$u['id'];

        $month = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            http_response_code(400);
            echo 'Invalid month format. Expected YYYY-MM.';
            return;
        }

        // If a static PDF exists (requested by user), serve it directly.
        if (defined('OWNER_REPORT_STATIC_PDF')) {
            $path = (string)OWNER_REPORT_STATIC_PDF;
            if ($path !== '' && is_file($path)) {
                if (function_exists('ob_get_level')) {
                    while (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
                }
                if (!headers_sent()) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="parking_system_report.pdf"');
                    header('Content-Length: ' . filesize($path));
                }
                readfile($path);
                return;
            }
        }

        (new OwnerReportModel($pdo))->downloadMonthlyPdf($uid, $month, (string)($u['name'] ?? 'Owner'));
    }

    public static function spots(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            if ($act === 'add') {
                $address = trim($_POST['address'] ?? '');
                $rate = (float)($_POST['base_rate'] ?? 0);
                $height = (float)($_POST['height'] ?? 0);
                $width = (float)($_POST['width'] ?? 0);
                $ev = isset($_POST['ev']) ? 1 : 0;
                $avs = $_POST['avail_start'] ?? '08:00';
                $ave = $_POST['avail_end'] ?? '22:00';
                $availErr = ParkingBookingValidator::validateOwnerDailyWindow($avs, $ave);
                if ($address && $rate > 0 && $availErr !== null) {
                    flash('err', $availErr);
                } elseif ($address && $rate > 0) {
                    $stmt = $pdo->prepare('INSERT INTO parking_spots (owner_id, address, base_rate, height_cm, width_cm, has_ev_charger, availability_start, availability_end) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->execute([$uid, $address, $rate, $height ?: null, $width ?: null, $ev, $avs, $ave]);
                    $sid = $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO pricing_engine (spot_id) VALUES (?)')->execute([$sid]);
                    $pdo->prepare('INSERT INTO buffer_manager (spot_id) VALUES (?)')->execute([$sid]);
                    flash('ok', 'Spot added.');
                }
            } elseif ($act === 'toggle') {
                $sid = (int)($_POST['spot_id'] ?? 0);
                $status = $_POST['new_status'] ?? 'available';
                $active = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE spot_id=? AND status IN ("confirmed","active")');
                $active->execute([$sid]);
                if ($active->fetchColumn() && $status !== 'available') {
                    flash('err', 'Cannot change status — there is an active booking on this spot.');
                } else {
                    $pdo->prepare('UPDATE parking_spots SET status=? WHERE spot_id=? AND owner_id=?')->execute([$status, $sid, $uid]);
                    if ($status === 'available') {
                        (new WaitlistModel($pdo))->notifySpotAvailable($sid);
                    }
                    flash('ok', 'Spot status updated.');
                }
            } elseif ($act === 'delete') {
                $sid = (int)($_POST['spot_id'] ?? 0);
                $active = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE spot_id=? AND status IN ("confirmed","active")');
                $active->execute([$sid]);
                if ($active->fetchColumn()) {
                    flash('err', 'Cannot delete — active booking exists.');
                } else {
                    $pdo->prepare('DELETE FROM parking_spots WHERE spot_id=? AND owner_id=?')->execute([$sid, $uid]);
                    flash('ok', 'Spot removed.');
                }
            }
            redirect(route_url('/owner/spots'));
        }

        $spotsStmt = $pdo->prepare('SELECT * FROM parking_spots WHERE owner_id=? ORDER BY created_at DESC');
        $spotsStmt->execute([$uid]);
        $spots = $spotsStmt->fetchAll();

        self::render('owner/spots', [
            'spots' => $spots,
            'pageTitle' => 'My Spots',
        ]);
    }

    public static function earnings(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ownerStmt = $pdo->prepare('SELECT earnings_balance FROM space_owners WHERE owner_id=?');
            $ownerStmt->execute([$uid]);
            $bal = $ownerStmt->fetchColumn();
            if ($bal >= 100) {
                $pdo->prepare('INSERT INTO payouts (owner_id, amount, status, week_start, week_end) VALUES (?,?,?,?,?)')->execute([$uid, $bal, 'pending', date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))]);
                $pdo->prepare('UPDATE space_owners SET earnings_balance=0 WHERE owner_id=?')->execute([$uid]);
                flash('ok', "Payout of {$bal} EGP requested.");
            } else {
                flash('err', 'Minimum payout threshold is 100 EGP.');
            }
            redirect(route_url('/owner/earnings'));
        }

        $ownerStmt = $pdo->prepare('SELECT earnings_balance, verification_status FROM space_owners WHERE owner_id=?');
        $ownerStmt->execute([$uid]);
        $odata = $ownerStmt->fetch();

        $monthlyStmt = $pdo->prepare('SELECT DATE_FORMAT(r.created_at, "%Y-%m") AS month, COUNT(*) AS sessions, SUM(r.final_cost) AS gross, SUM(r.final_cost * 0.85) AS net FROM reservations r JOIN parking_spots ps ON r.spot_id = ps.spot_id WHERE ps.owner_id = ? AND r.status = "completed" GROUP BY month ORDER BY month DESC LIMIT 6');
        $monthlyStmt->execute([$uid]);
        $monthly = $monthlyStmt->fetchAll();

        $payoutStmt = $pdo->prepare('SELECT * FROM payouts WHERE owner_id=? ORDER BY week_start DESC LIMIT 10');
        $payoutStmt->execute([$uid]);
        $payouts = $payoutStmt->fetchAll();

        self::render('owner/earnings', [
            'odata' => $odata,
            'monthly' => $monthly,
            'payouts' => $payouts,
            'pageTitle' => 'Earnings',
        ]);
    }

    public static function verify(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $paths = [];
            $upload_dir = dirname(__DIR__, 1) . '/uploads/docs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach (['id_doc', 'utility_bill'] as $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                        $fname = $field . '_' . $uid . '_' . time() . '.' . $ext;
                        $target = $upload_dir . $fname;
                        if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
                            $paths[] = $fname;
                        }
                    }
                }
            }
            
            if (count($paths) === 2) {
                $pdo->prepare('INSERT INTO document_repository (owner_id, document_paths, status) VALUES (?,?,?)')->execute([$uid, implode(',', $paths), 'pending']);
                $pdo->prepare('UPDATE space_owners SET verification_status="pending" WHERE owner_id=?')->execute([$uid]);
                flash('ok', 'Documents submitted for review.');
            } else {
                flash('err', 'Please upload both ID document and utility bill (JPG, PNG or PDF).');
            }
            redirect(route_url('/owner/verify'));
        }

        $ownerStmt = $pdo->prepare('SELECT verification_status FROM space_owners WHERE owner_id=?');
        $ownerStmt->execute([$uid]);
        $vst = $ownerStmt->fetchColumn();

        $docsStmt = $pdo->prepare('SELECT * FROM document_repository WHERE owner_id=? ORDER BY submitted_at DESC LIMIT 5');
        $docsStmt->execute([$uid]);
        $docs = $docsStmt->fetchAll();

        self::render('owner/verify', [
            'vst' => $vst,
            'docs' => $docs,
            'pageTitle' => 'Verification',
        ]);
    }
}
