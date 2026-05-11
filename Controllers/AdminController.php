<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/SpotApprovalModel.php';
require_once __DIR__ . '/../Models/BookingDisputeModel.php';
require_once __DIR__ . '/../Models/DriverWalletModel.php';
require_once __DIR__ . '/../Models/PaymentModel.php';

class AdminController extends BaseController
{
    private const VAT_PERCENT_MIN = 0.0;
    private const VAT_PERCENT_MAX = 100.0;

    public static function dashboard(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        $total_spots = $pdo->query('SELECT COUNT(*) FROM parking_spots')->fetchColumn();
        $avail_spots = $pdo->query("SELECT COUNT(*) FROM parking_spots WHERE status='available'")->fetchColumn();
        $active_res = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN ('confirmed','active')")->fetchColumn();
        $pending_fin = $pdo->query("SELECT COUNT(*) FROM fines WHERE status='pending'")->fetchColumn();
        $pending_app = $pdo->query("SELECT COUNT(*) FROM appeals WHERE status='pending'")->fetchColumn();
        $pending_ver = $pdo->query("SELECT COUNT(*) FROM document_repository WHERE status='pending'")->fetchColumn();
        new SpotApprovalModel($pdo);
        $pending_spot_listings = $pdo->query(
            'SELECT COUNT(*) FROM parking_spots WHERE spot_approval_status=' . $pdo->quote(SpotApprovalModel::STATUS_PENDING_REVIEW)
        )->fetchColumn();
        $total_rev = $pdo->query("SELECT COALESCE(SUM(final_cost),0) FROM reservations WHERE status='completed'")->fetchColumn();

        self::render('admin/dashboard', [
            'total_spots' => $total_spots,
            'avail_spots' => $avail_spots,
            'active_res' => $active_res,
            'pending_fin' => $pending_fin,
            'pending_app' => $pending_app,
            'pending_ver' => $pending_ver,
            'pending_spot_listings' => $pending_spot_listings,
            'total_rev' => $total_rev,
            'pageTitle' => 'Admin Dashboard',
        ]);
    }

    public static function spots(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        $filter = $_GET['status'] ?? 'all';
        $where = '1=1';
        $params = [];
        if ($filter !== 'all') {
            $where = 'ps.status = ?';
            $params[] = $filter;
        }
        $stmt = $pdo->prepare("SELECT ps.*, u.name AS owner_name, z.name AS zone_name, COUNT(r.reservation_id) AS total_bookings FROM parking_spots ps JOIN users u ON ps.owner_id = u.id LEFT JOIN zones z ON z.zone_id = ps.zone_id LEFT JOIN reservations r ON r.spot_id = ps.spot_id AND r.status = 'completed' WHERE $where GROUP BY ps.spot_id ORDER BY ps.created_at DESC");
        $stmt->execute($params);
        $spots = $stmt->fetchAll();
        self::render('admin/spots', [
            'spots' => $spots,
            'filter' => $filter,
            'pageTitle' => 'All Spots',
        ]);
    }

    public static function fines(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $fine_id = (int)($_POST['fine_id'] ?? 0);
            if ($act === 'issue') {
                $driver_id = (int)($_POST['driver_id'] ?? 0);
                $spot_id = (int)($_POST['spot_id'] ?? 0);
                $type = (string)($_POST['type'] ?? 'unauthorized');
                $allowedTypes = ['unauthorized', 'overstay'];
                if (!in_array($type, $allowedTypes, true)) {
                    $type = 'unauthorized';
                }
                $amountRaw = trim((string)($_POST['amount'] ?? '50'));
                $amount = (float)$amountRaw;

                $errs = FineIssueValidator::validateIssuePayload($driver_id, $spot_id, $amountRaw, 'Fine amount');

                if ($errs === []) {
                    $pdo->prepare('INSERT INTO fines (driver_id, spot_id, type, penalty_amount) VALUES (?,?,?,?)')->execute([$driver_id, $spot_id, $type, $amount]);
                    $unpaid = $pdo->prepare("SELECT COUNT(*) FROM fines WHERE driver_id=? AND status='pending'");
                    $unpaid->execute([$driver_id]);
                    $cnt = $unpaid->fetchColumn();
                    if (DriverSanctionPolicy::shouldSuspendForUnpaidFines((int)$cnt)) {
                        $pdo->prepare('UPDATE drivers SET can_book=0, unpaid_fines=? WHERE driver_id=?')->execute([$cnt, $driver_id]);
                        $bl = $pdo->prepare('SELECT COUNT(*) FROM blacklist WHERE driver_id=?');
                        $bl->execute([$driver_id]);
                        if (!$bl->fetchColumn()) {
                            $pdo->prepare('INSERT INTO blacklist (driver_id) VALUES (?)')->execute([$driver_id]);
                        }
                        $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$driver_id, 'in_app', 'Your account has been suspended due to 3+ unpaid fines.', 'suspension', 'sent']);
                    }
                    $pdo->prepare('UPDATE drivers SET unpaid_fines=? WHERE driver_id=?')->execute([$cnt, $driver_id]);
                    flash('ok', 'Fine issued. Driver now has ' . $cnt . ' unpaid fine(s).');
                } else {
                    flash('err', implode(' ', $errs));
                }
            } elseif ($act === 'cancel' && $fine_id) {
                $pdo->prepare('UPDATE fines SET status="cancelled" WHERE fine_id=?')->execute([$fine_id]);
                flash('ok', 'Fine cancelled.');
            }
            redirect(route_url('/admin/fines'));
        }

        $finesStmt = $pdo->prepare('SELECT f.*, u.name AS driver_name, ps.address, a.appeal_id, a.status AS appeal_status FROM fines f JOIN users u ON f.driver_id = u.id JOIN parking_spots ps ON f.spot_id = ps.spot_id LEFT JOIN appeals a ON a.fine_id = f.fine_id ORDER BY f.issued_at DESC LIMIT 50');
        $finesStmt->execute();
        $fines = $finesStmt->fetchAll();

        $drivers = $pdo->query('SELECT u.id, u.name FROM users u JOIN drivers d ON d.driver_id=u.id ORDER BY u.name')->fetchAll();
        $spots = $pdo->query('SELECT spot_id, address FROM parking_spots ORDER BY address')->fetchAll();

        self::render('admin/fines', [
            'fines' => $fines,
            'drivers' => $drivers,
            'spots' => $spots,
            'pageTitle' => 'Fines Management',
        ]);
    }

    public static function appeals(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $appeal_id = (int)($_POST['appeal_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $note = trim($_POST['note'] ?? '');
            if (in_array($decision, ['approved', 'rejected']) && $appeal_id) {
                $apStmt = $pdo->prepare('SELECT * FROM appeals WHERE appeal_id=?');
                $apStmt->execute([$appeal_id]);
                $ap = $apStmt->fetch();
                $pdo->prepare('UPDATE appeals SET status=?, decision_note=?, resolved_at=NOW() WHERE appeal_id=?')->execute([$decision, $note, $appeal_id]);
                if ($decision === 'approved') {
                    $pdo->prepare('UPDATE fines SET status="cancelled" WHERE fine_id=?')->execute([$ap['fine_id']]);
                    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM fines WHERE driver_id=? AND status='pending'");
                    $cntStmt->execute([$ap['driver_id']]);
                    $unpaid = $cntStmt->fetchColumn();
                    if ($unpaid < 3) {
                        $pdo->prepare('UPDATE drivers SET can_book=1, unpaid_fines=? WHERE driver_id=?')->execute([$unpaid, $ap['driver_id']]);
                        $pdo->prepare('DELETE FROM blacklist WHERE driver_id=?')->execute([$ap['driver_id']]);
                    }
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$ap['driver_id'], 'in_app', 'Your appeal has been approved and the fine has been cancelled.', 'appeal', 'sent']);
                } else {
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$ap['driver_id'], 'in_app', 'Your fine appeal was reviewed. Decision: rejected. Reason: ' . $note, 'appeal', 'sent']);
                }
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, new_state) VALUES (?,?,?,?)')->execute(['APPEAL_DECISION', $appeal_id, current_user()['id'], $decision]);
                flash('ok', 'Appeal ' . $decision . '.');
            }
            redirect(route_url('/admin/appeals'));
        }

        $appealsStmt = $pdo->prepare('SELECT a.*, u.name AS driver_name, f.penalty_amount, f.type AS fine_type, ps.address AS spot_addr FROM appeals a JOIN users u ON a.driver_id = u.id JOIN fines f ON a.fine_id = f.fine_id JOIN parking_spots ps ON f.spot_id = ps.spot_id ORDER BY a.submitted_at DESC');
        $appealsStmt->execute();
        $appeals = $appealsStmt->fetchAll();

        self::render('admin/appeals', [
            'appeals' => $appeals,
            'pageTitle' => 'Fine Appeals',
        ]);
    }

    public static function notifications(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = (int)$u['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE recipient_id=?')->execute([$uid]);
            flash('ok', 'All marked as read.');
            redirect(route_url('/admin/notifications'));
        }

        $notifs = $pdo->prepare('SELECT * FROM notifications WHERE recipient_id=? ORDER BY created_at DESC LIMIT 50');
        $notifs->execute([$uid]);
        $notifs = $notifs->fetchAll();

        self::render('admin/notifications', [
            'notifs' => $notifs,
            'pageTitle' => 'Notifications',
        ]);
    }

    public static function zones(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $zone_id = (int)($_POST['zone_id'] ?? 0);
            if ($act === 'add') {
                $name = trim($_POST['name'] ?? '');
                $vatPercentRaw = trim((string)($_POST['vat_rate'] ?? '14'));
                $vatPercent = (float)$vatPercentRaw;
                $errs = [];
                if ($name === '') {
                    $errs[] = 'Zone name is required.';
                }
                if ($vatPercentRaw === '' || !is_numeric($vatPercentRaw) || $vatPercent < self::VAT_PERCENT_MIN || $vatPercent > self::VAT_PERCENT_MAX) {
                    $errs[] = 'VAT rate must be between ' . self::VAT_PERCENT_MIN . '% and ' . self::VAT_PERCENT_MAX . '%.';
                }
                if ($errs === []) {
                    $vat = $vatPercent / 100;
                    $pdo->prepare('INSERT INTO zones (name, vat_rate) VALUES (?,?)')->execute([$name, $vat]);
                    flash('ok', 'Zone added.');
                } else {
                    flash('err', implode(' ', $errs));
                }
            } elseif ($act === 'lock' && $zone_id) {
                $event = trim($_POST['locked_event'] ?? '');
                $start = $_POST['lock_start'] ?? '';
                $end = $_POST['lock_end'] ?? '';
                $pdo->prepare('UPDATE zones SET status="locked", locked_event=?, lock_start=?, lock_end=? WHERE zone_id=?')->execute([$event, $start, $end, $zone_id]);
                $booked = $pdo->prepare(
                    'SELECT r.reservation_id, r.driver_id, r.final_cost
                     FROM reservations r
                     JOIN parking_spots ps ON r.spot_id = ps.spot_id
                     WHERE ps.zone_id = ? AND r.status IN ("confirmed","pending") AND r.start_time >= ?'
                );
                $booked->execute([$zone_id, $start]);
                $affected = $booked->fetchAll();
                new PaymentModel($pdo);
                foreach ($affected as $b) {
                    $rid = (int)$b['reservation_id'];
                    $did = (int)$b['driver_id'];
                    $finalCost = round((float)($b['final_cost'] ?? 0), 2);
                    $pdo->prepare('UPDATE reservations SET status="cancelled", cancelled_at=NOW() WHERE reservation_id=?')->execute([$rid]);
                    $pdo->prepare(
                        'UPDATE payments SET escrow_status="refunded", refund_percent=100, refund_amount=? WHERE payment_id=(SELECT payment_id FROM reservations WHERE reservation_id=?)'
                    )->execute([$finalCost, $rid]);
                    (new PaymentModel($pdo))->refundFunds($rid);
                    if ($finalCost > 0) {
                        DriverWalletModel::credit($pdo, $did, $finalCost);
                    }
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$did, 'in_app', "Your booking was cancelled because zone was locked for: {$event}. Full refund issued.", 'zone_lock', 'sent']);
                }
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, new_state) VALUES (?,?,?,?)')->execute(['ZONE_LOCKED', $zone_id, current_user()['id'], 'locked']);
                flash('ok', 'Zone locked. ' . count($affected) . ' booking(s) cancelled with full refund.');
            } elseif ($act === 'unlock' && $zone_id) {
                $pdo->prepare('UPDATE zones SET status="active", locked_event=NULL, lock_start=NULL, lock_end=NULL WHERE zone_id=?')->execute([$zone_id]);
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, new_state) VALUES (?,?,?,?)')->execute(['ZONE_UNLOCKED', $zone_id, current_user()['id'], 'active']);
                flash('ok', 'Zone unlocked.');
            }
            redirect(route_url('/admin/zones'));
        }

        $zonesStmt = $pdo->query('SELECT z.*, COUNT(ps.spot_id) AS spot_count FROM zones z LEFT JOIN parking_spots ps ON ps.zone_id = z.zone_id GROUP BY z.zone_id ORDER BY z.name')->fetchAll();
        self::render('admin/zones', [
            'zones' => $zonesStmt,
            'pageTitle' => 'Zone Management',
        ]);
    }

    public static function owners(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $req_id = (int)($_POST['request_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $note = trim($_POST['note'] ?? '');
            $owner_id = (int)($_POST['owner_id'] ?? 0);
            if (in_array($decision, ['approved', 'rejected']) && $req_id) {
                $pdo->prepare('UPDATE document_repository SET status=?, decision_note=?, decided_at=NOW() WHERE request_id=?')->execute([$decision, $note, $req_id]);
                $pdo->prepare('UPDATE space_owners SET verification_status=? WHERE owner_id=?')->execute([$decision, $owner_id]);
                $msg = $decision === 'approved' ? 'Your account has been verified! You can now list spots and receive payouts.' : 'Your verification was rejected. Reason: ' . $note;
                $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$owner_id, 'in_app', $msg, 'verification', 'sent']);
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, new_state) VALUES (?,?,?,?)')->execute(['OWNER_VERIFICATION', $req_id, current_user()['id'], $decision]);
                flash('ok', 'Decision saved.');
            }
            redirect(route_url('/admin/owners'));
        }

        $requestsStmt = $pdo->prepare('SELECT dr.*, u.name AS owner_name, u.email AS owner_email FROM document_repository dr JOIN users u ON dr.owner_id = u.id ORDER BY dr.submitted_at DESC');
        $requestsStmt->execute();
        $requests = $requestsStmt->fetchAll();

        self::render('admin/owners', [
            'requests' => $requests,
            'pageTitle' => 'Owner Verifications',
        ]);
    }

    public static function spotApprovals(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        new SpotApprovalModel($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $submission_id = (int)($_POST['submission_id'] ?? 0);
            $spot_id = (int)($_POST['spot_id'] ?? 0);
            $owner_id = (int)($_POST['owner_id'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));

            $subStmt = $pdo->prepare('SELECT * FROM spot_document_submissions WHERE submission_id=? AND review_status="pending" LIMIT 1');
            $subStmt->execute([$submission_id]);
            $sub = $subStmt->fetch();
            if ($sub && (int)$sub['spot_id'] === $spot_id) {
                if ($act === 'approve_spot_listing') {
                    $pdo->prepare('UPDATE spot_document_submissions SET review_status="approved", admin_note=NULL, reviewed_at=NOW() WHERE submission_id=?')->execute([$submission_id]);
                    $pdo->prepare('UPDATE parking_spots SET spot_approval_status=?, status="available" WHERE spot_id=?')->execute([SpotApprovalModel::STATUS_APPROVED, $spot_id]);
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$owner_id, 'in_app', 'Your parking spot listing was approved by an admin and is now bookable.', 'spot_listing', 'sent']);
                    $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, new_state) VALUES (?,?,?,?)')->execute(['SPOT_LISTING_APPROVED', (string)$spot_id, current_user()['id'], 'approved']);
                    flash('ok', 'Spot listing approved.');
                } elseif ($act === 'reject_spot_listing') {
                    $pdo->prepare('UPDATE spot_document_submissions SET review_status="rejected", admin_note=?, reviewed_at=NOW() WHERE submission_id=?')->execute([$note ?: null, $submission_id]);
                    // Keep the spot clearly non-bookable after rejection so the owner can re-submit docs.
                    $pdo->prepare('UPDATE parking_spots SET spot_approval_status=?, status="maintenance" WHERE spot_id=?')->execute([SpotApprovalModel::STATUS_REJECTED, $spot_id]);
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$owner_id, 'in_app', 'Your spot listing documents were rejected. Reason: ' . ($note ?: 'See admin feedback.') . ' You may submit new documents from My Spots.', 'spot_listing', 'sent']);
                    $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, new_state) VALUES (?,?,?,?)')->execute(['SPOT_LISTING_REJECTED', (string)$spot_id, current_user()['id'], 'rejected']);
                    flash('ok', 'Spot listing rejected.');
                }
            }
            redirect(route_url('/admin/spot-approvals'));
        }

        $q = <<<'SQL'
SELECT s.submission_id, s.spot_id, s.owner_id, s.document_paths, s.submitted_at,
       ps.address, ps.base_rate, u.name AS owner_name, u.email AS owner_email
FROM spot_document_submissions s
JOIN parking_spots ps ON ps.spot_id = s.spot_id
JOIN users u ON u.id = s.owner_id
WHERE s.review_status = 'pending'
  AND ps.spot_approval_status = ?
ORDER BY s.submitted_at ASC
SQL;
        $stmt = $pdo->prepare($q);
        $stmt->execute([SpotApprovalModel::STATUS_PENDING_REVIEW]);
        $pending = $stmt->fetchAll();

        self::render('admin/spot_approvals', [
            'pending' => $pending,
            'pageTitle' => 'Spot listing approvals',
        ]);
    }

    public static function bookingDisputes(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        $model = new BookingDisputeModel($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $did = (int)($_POST['dispute_id'] ?? 0);
            $adminId = (int)(current_user()['id'] ?? 0);
            if ($act === 'approve' && $did > 0) {
                $pct = (float)($_POST['approved_percent'] ?? 0);
                $note = trim((string)($_POST['admin_note'] ?? ''));
                $res = $model->resolveApprove($did, $pct, $note, $adminId > 0 ? $adminId : null);
                if ($res['ok'] ?? false) {
                    flash('ok', 'Dispute approved. Owner balance adjusted; driver notified.');
                } else {
                    flash('err', $res['error'] ?? 'Could not approve dispute.');
                }
            } elseif ($act === 'reject' && $did > 0) {
                $note = trim((string)($_POST['admin_note'] ?? ''));
                if ($model->resolveReject($did, $note)) {
                    flash('ok', 'Dispute rejected.');
                } else {
                    flash('err', 'Could not update dispute.');
                }
            }
            redirect(route_url('/admin/booking-disputes'));
        }

        self::render('admin/booking_disputes', [
            'pending' => $model->listPendingWithContext(),
            'pageTitle' => 'Booking disputes',
        ]);
    }

    public static function heatmap(): void
    {
        require_role('admin');
        $pdo = Database::getConnection();
        $data = $pdo->query('SELECT ps.spot_id, ps.address, z.name AS zone_name, COALESCE(SUM(r.final_cost), 0) AS revenue, COUNT(r.reservation_id) AS sessions FROM parking_spots ps LEFT JOIN zones z ON z.zone_id = ps.zone_id LEFT JOIN reservations r ON r.spot_id = ps.spot_id AND r.status = "completed" GROUP BY ps.spot_id ORDER BY revenue DESC')->fetchAll();
        $max_rev = max(array_column($data, 'revenue') ?: [1]);
        self::render('admin/heatmap', [
            'data' => $data,
            'max_rev' => $max_rev,
            'pageTitle' => 'Revenue Heatmap',
        ]);
    }

    public static function viewDoc(): void
    {
        require_role('admin');
        $file = $_GET['file'] ?? '';
        $bucket = $_GET['bucket'] ?? 'owner';
        if (!$file) {
            die('No file specified');
        }
        $base = basename($file);
        if ($bucket === 'spot') {
            $path = dirname(__DIR__) . '/uploads/spot_docs/' . $base;
        } else {
            $path = dirname(__DIR__) . '/uploads/docs/' . $base;
        }
        if (!file_exists($path)) {
            die('File not found');
        }
        $mime = mime_content_type($path);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        readfile($path);
    }
}
