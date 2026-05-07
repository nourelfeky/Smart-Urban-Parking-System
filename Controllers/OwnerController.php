<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/ParkingBookingValidator.php';
require_once __DIR__ . '/../Models/OwnerReportModel.php';
require_once __DIR__ . '/../Models/WaitlistModel.php';
require_once __DIR__ . '/../Models/ReviewModel.php';
require_once __DIR__ . '/../Models/SpotApprovalModel.php';

class OwnerController extends BaseController
{
    /**
     * Best-effort address geocoding using OpenStreetMap Nominatim.
     * Returns [lat, lng] or [null, null] if not found/failed.
     *
     * NOTE: This is intentionally lightweight (no composer deps).
     */
    private static function geocodeAddress(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            return [null, null];
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode($address);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "User-Agent: CitySlot/1.0 (demo)\r\nAccept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return [null, null];
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json[0]) || !is_array($json[0])) {
            return [null, null];
        }

        $lat = isset($json[0]['lat']) ? (float)$json[0]['lat'] : null;
        $lon = isset($json[0]['lon']) ? (float)$json[0]['lon'] : null;
        if (!$lat || !$lon) {
            return [null, null];
        }
        return [$lat, $lon];
    }

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

        $reportModel = new OwnerReportModel($pdo);
        $metrics = $reportModel->getMonthlyOwnerMetrics($uid, $month);
        $daily = $reportModel->getDailySessions($uid, $month);
        $hourly = $reportModel->getHourlySessions($uid, $month);
        self::render('owner/reports', [
            'month' => $month,
            'metrics' => $metrics,
            'daily' => $daily,
            'hourly' => $hourly,
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

        $reportModel = new OwnerReportModel($pdo);
        $metrics = $reportModel->getMonthlyOwnerMetrics($uid, $month);
        $daily = $reportModel->getDailySessions($uid, $month);
        $hourly = $reportModel->getHourlySessions($uid, $month);
        $reportModel->downloadMonthlyPdf($month, (string)($u['name'] ?? 'Owner'), $metrics, $daily, $hourly);
    }

    public static function spots(): void
    {
        require_role('owner');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];
        new SpotApprovalModel($pdo);

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

                $latRaw = trim((string)($_POST['latitude'] ?? ''));
                $lngRaw = trim((string)($_POST['longitude'] ?? ''));
                $latPost = $latRaw !== '' ? (float)$latRaw : null;
                $lngPost = $lngRaw !== '' ? (float)$lngRaw : null;

                $pickedOk = $latPost !== null
                    && $lngPost !== null
                    && $latPost >= -90 && $latPost <= 90
                    && $lngPost >= -180 && $lngPost <= 180;

                $availErr = ParkingBookingValidator::validateOwnerDailyWindow($avs, $ave);
                if ($address && $rate > 0 && $availErr !== null) {
                    flash('err', $availErr);
                } elseif ($address && $rate > 0) {
                    if ($pickedOk) {
                        $lat = $latPost;
                        $lng = $lngPost;
                    } else {
                        [$lat, $lng] = self::geocodeAddress($address);
                    }
                    $stmt = $pdo->prepare('INSERT INTO parking_spots (owner_id, address, latitude, longitude, base_rate, height_cm, width_cm, has_ev_charger, availability_start, availability_end, status, spot_approval_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$uid, $address, $lat, $lng, $rate, $height ?: null, $width ?: null, $ev, $avs, $ave, 'reserved', SpotApprovalModel::STATUS_PENDING_DOCS]);
                    $sid = $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO pricing_engine (spot_id) VALUES (?)')->execute([$sid]);
                    $pdo->prepare('INSERT INTO buffer_manager (spot_id) VALUES (?)')->execute([$sid]);
                    flash('ok', 'Spot created. Upload proof documents below — it will go live after admin approval.');
                }
            } elseif ($act === 'submit_spot_documents') {
                $sid = (int)($_POST['spot_id'] ?? 0);
                $own = $pdo->prepare('SELECT spot_id, spot_approval_status FROM parking_spots WHERE spot_id=? AND owner_id=?');
                $own->execute([$sid, $uid]);
                $row = $own->fetch();
                if (!$row) {
                    flash('err', 'Invalid spot.');
                } elseif ($row['spot_approval_status'] === SpotApprovalModel::STATUS_PENDING_REVIEW) {
                    flash('err', 'This spot is already waiting for admin review.');
                } elseif ($row['spot_approval_status'] === SpotApprovalModel::STATUS_APPROVED) {
                    flash('err', 'This spot is already approved.');
                } else {
                    $upload_dir = dirname(__DIR__, 1) . '/uploads/spot_docs/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $paths = [];
                    foreach (['lease_or_ownership', 'spot_photo'] as $field) {
                        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                            continue;
                        }
                        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                            continue;
                        }
                        $fname = $field . '_spot' . $sid . '_' . $uid . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $fname)) {
                            $paths[] = $fname;
                        }
                    }
                    if (count($paths) !== 2) {
                        flash('err', 'Please upload both documents: lease/ownership proof and a spot photo (JPG, PNG, or PDF).');
                    } else {
                        $pdo->prepare(
                            'INSERT INTO spot_document_submissions (spot_id, owner_id, document_paths, review_status) VALUES (?,?,?,?)'
                        )->execute([$sid, $uid, implode(',', $paths), 'pending']);
                        $pdo->prepare('UPDATE parking_spots SET spot_approval_status=? WHERE spot_id=? AND owner_id=?')->execute([SpotApprovalModel::STATUS_PENDING_REVIEW, $sid, $uid]);
                        flash('ok', 'Documents submitted. An admin will review your spot.');
                    }
                }
                redirect(route_url('/owner/spots'));
            } elseif ($act === 'toggle') {
                $sid = (int)($_POST['spot_id'] ?? 0);
                $chk = $pdo->prepare('SELECT spot_approval_status, status FROM parking_spots WHERE spot_id=? AND owner_id=?');
                $chk->execute([$sid, $uid]);
                $crow = $chk->fetch();
                if (!$crow || $crow['spot_approval_status'] !== SpotApprovalModel::STATUS_APPROVED) {
                    flash('err', 'You can only change availability after admin approves this spot.');
                    redirect(route_url('/owner/spots'));
                }
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

        $latestSub = [];
        if ($spots !== []) {
            $ids = array_values(array_unique(array_map(static fn($s) => (int)$s['spot_id'], $spots)));
            $in = implode(',', $ids);
            $subStmt = $pdo->query("SELECT * FROM spot_document_submissions WHERE spot_id IN ($in) ORDER BY submission_id DESC");
            foreach ($subStmt->fetchAll() as $sr) {
                $spid = (int)$sr['spot_id'];
                if (!isset($latestSub[$spid])) {
                    $latestSub[$spid] = $sr;
                }
            }
        }

        self::render('owner/spots', [
            'spots' => $spots,
            'latestSub' => $latestSub,
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
