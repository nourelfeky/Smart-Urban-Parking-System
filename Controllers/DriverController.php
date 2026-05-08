<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/BookingManager.php';
require_once __DIR__ . '/../Models/ParkingBookingValidator.php';
require_once __DIR__ . '/../Models/PaymentModel.php';
require_once __DIR__ . '/../Models/PenaltyModel.php';
require_once __DIR__ . '/../Models/WaitlistModel.php';
require_once __DIR__ . '/../Models/ReviewModel.php';
require_once __DIR__ . '/../Models/SpotApprovalModel.php';
require_once __DIR__ . '/../Models/PromotionalCodeValidator.php';
require_once __DIR__ . '/../Models/ReservationSubject.php';
require_once __DIR__ . '/../Models/ReservationEvent.php';
require_once __DIR__ . '/../Models/PaymentMethodStrategy.php';
require_once __DIR__ . '/../Models/PricingEngine.php';
require_once __DIR__ . '/../Models/TaxEngine.php';
require_once __DIR__ . '/../Models/ParkingSystemConfig.php';

class DriverController extends BaseController
{
    private const DIMENSION_CM_MIN = 1.0;
    private const DIMENSION_CM_MAX = 1000.0;
    private const BOOKING_EXTENSION_MIN_MINS = 1;
    private const BOOKING_EXTENSION_MAX_MINS = 12 * 60; // 12 hours

    public static function dashboard(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();
        $u = current_user();
        $uid = (int)$u['id'];
        PromotionalCodeValidator::ensureDefaultPromotionalCodes($pdo);
        PromotionalCodeValidator::maybeNotifyGoldTierPromo($pdo, $uid);
        new PaymentModel($pdo);

        // Keep loyalty counters rolling (30 days) even if the user hasn't booked recently.
        self::refreshLoyaltyRolling($pdo, $uid);

        $active_count = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE driver_id=? AND status IN ("confirmed","active")');
        $active_count->execute([$uid]);
        $active_count = $active_count->fetchColumn();

        $fine_count = $pdo->prepare('SELECT COUNT(*) FROM fines WHERE driver_id=? AND status="pending"');
        $fine_count->execute([$uid]);
        $fine_count = $fine_count->fetchColumn();

        $notif_count = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND is_read=0');
        $notif_count->execute([$uid]);
        $notif_count = $notif_count->fetchColumn();

        $loyalty = $pdo->prepare('SELECT current_tier, booking_last_30_days FROM loyalty_accounts WHERE driver_id=?');
        $loyalty->execute([$uid]);
        $loy = $loyalty->fetch() ?: ['current_tier' => 'bronze', 'booking_last_30_days' => 0];

        $recent = $pdo->prepare('SELECT r.reservation_id, r.start_time, r.end_time, r.status, r.final_cost, r.qr_code_token, ps.address FROM reservations r JOIN parking_spots ps ON r.spot_id = ps.spot_id WHERE r.driver_id = ? ORDER BY r.created_at DESC LIMIT 5');
        $recent->execute([$uid]);
        $recent_bookings = $recent->fetchAll();

        $subsStmt = $pdo->prepare(
            'SELECT cs.subscription_id, cs.days_of_week, cs.start_time_of_day, cs.end_time_of_day, cs.weeks, cs.discount_percent, cs.status, ps.address
             FROM commuter_subscriptions cs
             JOIN parking_spots ps ON ps.spot_id = cs.spot_id
             WHERE cs.driver_id = ? AND cs.status = "active"
             ORDER BY cs.created_at DESC
             LIMIT 5'
        );
        $subsStmt->execute([$uid]);
        $subscriptions = $subsStmt->fetchAll();

        $driverInfo = $pdo->prepare('SELECT can_book, account_status FROM drivers WHERE driver_id=?');
        $driverInfo->execute([$uid]);
        $dinfo = $driverInfo->fetch();
        // Wallet balance is not stored in DB yet (best-effort default).
        $dinfo['wallet_balance'] = 0.00;
        if (!isset($dinfo['account_status']) || !$dinfo['account_status']) {
            $dinfo['account_status'] = 'active';
        }

        self::render('driver/dashboard', [
            'u' => $u,
            'active_count' => $active_count,
            'fine_count' => $fine_count,
            'notif_count' => $notif_count,
            'loy' => $loy,
            'recent' => $recent_bookings,
            'subscriptions' => $subscriptions,
            'dinfo' => $dinfo,
            'pageTitle' => 'Dashboard',
        ]);
    }

    public static function search(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        (new BookingManager($pdo))->ensureSubscriptionSchema();
        $q = trim($_GET['q'] ?? '');
        $ev_only = isset($_GET['ev_only']);
        $max_h = trim($_GET['max_h'] ?? '');
        $max_w = trim($_GET['max_w'] ?? '');

        $where = ["ps.status = 'available'", '(z.status = \'active\' OR ps.zone_id IS NULL)', SpotApprovalModel::isApprovedSql('ps')];
        $params = [];
        if ($q) {
            $where[] = 'ps.address LIKE ?';
            $params[] = "%$q%";
        }
        if ($ev_only) {
            $where[] = 'ps.has_ev_charger = 1';
        }
        if ($max_h !== '') {
            if (!is_numeric($max_h) || (float)$max_h <= 0) {
                flash('err', 'Max height must be a positive number.');
                $max_h = '';
            } else {
                $where[] = '(ps.height_cm IS NULL OR ps.height_cm >= ?)';
                $params[] = (float)$max_h;
            }
        }
        if ($max_w !== '') {
            if (!is_numeric($max_w) || (float)$max_w <= 0) {
                flash('err', 'Max width must be a positive number.');
                $max_w = '';
            } else {
                $where[] = '(ps.width_cm IS NULL OR ps.width_cm >= ?)';
                $params[] = (float)$max_w;
            }
        }

        $sql = 'SELECT ps.spot_id, ps.zone_id, ps.address, ps.base_rate, ps.has_ev_charger, ps.height_cm, ps.width_cm, ps.difficulty_label, pe.default_multiplier, COALESCE(AVG(dr.rating_value), 0) AS avg_rating, COUNT(DISTINCT dr.rating_id) AS rating_count, ps.latitude, ps.longitude FROM parking_spots ps LEFT JOIN pricing_engine pe ON pe.spot_id = ps.spot_id LEFT JOIN difficulty_ratings dr ON dr.spot_id = ps.spot_id LEFT JOIN zones z ON z.zone_id = ps.zone_id WHERE ' . implode(' AND ', $where) . ' GROUP BY ps.spot_id ORDER BY ps.base_rate ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $spots = $stmt->fetchAll();

        self::render('driver/search', [
            'spots' => $spots,
            'q' => $q,
            'ev_only' => $ev_only,
            'max_h' => $max_h,
            'max_w' => $max_w,
            'pageTitle' => 'Find Parking',
        ]);
    }

    public static function zones(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        (new BookingManager($pdo))->ensureSubscriptionSchema();
        $u = current_user();
        $uid = (int)$u['id'];
        $wait = new WaitlistModel($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $zone_id = (int)($_POST['zone_id'] ?? 0);
            if ($zone_id) {
                if ($act === 'watch_zone') {
                    $wait->joinZoneWatch($uid, $zone_id);
                    flash('ok', 'You are now watching this zone. We will notify you when a spot becomes available.');
                } elseif ($act === 'unwatch_zone') {
                    $wait->leaveZoneWatch($uid, $zone_id);
                    flash('ok', 'Zone watch removed.');
                }
            }
            redirect(route_url('/driver/zones'));
        }

        $zonesStmt = $pdo->prepare(
            'SELECT z.zone_id, z.name,
                    SUM(CASE WHEN ps.status = "available" AND ' . SpotApprovalModel::isApprovedSql('ps') . ' THEN 1 ELSE 0 END) AS available_spots,
                    COUNT(ps.spot_id) AS total_spots,
                    zw.watch_id
             FROM zones z
             LEFT JOIN parking_spots ps ON ps.zone_id = z.zone_id
             LEFT JOIN zone_watchlist zw ON zw.zone_id = z.zone_id AND zw.driver_id = ?
             WHERE z.status = "active"
             GROUP BY z.zone_id
             ORDER BY z.name ASC'
        );
        $zonesStmt->execute([$uid]);
        $zones = $zonesStmt->fetchAll();

        self::render('driver/zones', [
            'zones' => $zones,
            'pageTitle' => 'Parking Zones',
        ]);
    }

    public static function book(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        new PaymentModel($pdo);
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();
        PromotionalCodeValidator::ensureDefaultPromotionalCodes($pdo);
        $u = current_user();
        $uid = (int)$u['id'];
        $spot_id = (int)($_GET['spot'] ?? 0);
        if (!$spot_id) {
            redirect(route_url('/driver/search'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waitlist_spot_action'])) {
            $target = (int)($_POST['waitlist_spot_id'] ?? 0);
            $act = (string)($_POST['waitlist_spot_action'] ?? '');
            if ($target > 0 && in_array($act, ['join', 'leave'], true)) {
                if ($act === 'join') {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM waitlist WHERE driver_id=? AND spot_id=?');
                    $chk->execute([$uid, $target]);
                    if (!$chk->fetchColumn()) {
                        $pdo->prepare('INSERT INTO waitlist (spot_id, driver_id) VALUES (?,?)')->execute([$target, $uid]);
                        flash('ok', "You're on the waitlist for this spot. We'll notify you when it may be free.");
                    } else {
                        flash('err', 'You are already on the waitlist for this spot.');
                    }
                } else {
                    $pdo->prepare('DELETE FROM waitlist WHERE driver_id=? AND spot_id=?')->execute([$uid, $target]);
                    flash('ok', 'Removed from waitlist for this spot.');
                }
            }
            $q = $_GET;
            $q['spot'] = (string)$target;
            ksort($q);
            redirect(route_url('/driver/book?' . http_build_query($q)));
        }

        $spotStmt = $pdo->prepare('SELECT ps.*, z.vat_rate, z.status AS zone_status, pe.default_multiplier, bm.buffer_duration_mins FROM parking_spots ps LEFT JOIN zones z ON z.zone_id = ps.zone_id LEFT JOIN pricing_engine pe ON pe.spot_id = ps.spot_id LEFT JOIN buffer_manager bm ON bm.spot_id = ps.spot_id WHERE ps.spot_id = ?');
        $spotStmt->execute([$spot_id]);
        $spot = $spotStmt->fetch();
        if (!$spot) {
            flash('err', 'Spot not available.');
            redirect(route_url('/driver/search'));
        }

        $wlChk = $pdo->prepare('SELECT COUNT(*) FROM waitlist WHERE driver_id=? AND spot_id=?');
        $wlChk->execute([$uid, $spot_id]);
        $on_spot_waitlist = (int)$wlChk->fetchColumn() > 0;

        $recommendations = [];
        $availabilityError = '';
        $listingOk = (($spot['spot_approval_status'] ?? SpotApprovalModel::STATUS_APPROVED) === SpotApprovalModel::STATUS_APPROVED);
        if (!$listingOk) {
            $availabilityError = 'This parking spot is pending admin verification and cannot be booked yet.';
        } elseif ($spot['status'] !== 'available' || $spot['zone_status'] === 'locked') {
            $availabilityError = $spot['zone_status'] === 'locked'
                ? 'This zone is currently locked by a municipal event.'
                : 'This spot is currently unavailable.';
            $recommendations = $bookingManager->getAlternativeSpots($spot, 5);
            foreach ($recommendations as &$rec) {
                $rec['fits_requested_window'] = false;
            }
            unset($rec);
        }

        $dinfoStmt = $pdo->prepare('SELECT can_book, account_status FROM drivers WHERE driver_id=?');
        $dinfoStmt->execute([$uid]);
        $dinfo = $dinfoStmt->fetch();
        if ($dinfo && (string)($dinfo['account_status'] ?? 'active') !== 'active') {
            flash('err', 'Your driver account is not active. Booking is disabled.');
            redirect(route_url('/driver/dashboard'));
        }
        if ($dinfo && !$dinfo['can_book']) {
            flash('err', 'Your account is suspended from making bookings due to unpaid fines.');
            redirect(route_url('/driver/dashboard'));
        }

        $vehiclesStmt = $pdo->prepare('SELECT * FROM vehicle_profiles WHERE owner_id=?');
        $vehiclesStmt->execute([$uid]);
        $vehicles = $vehiclesStmt->fetchAll();

        // Keep loyalty counters rolling (30 days) even if the user hasn't booked recently.
        self::refreshLoyaltyRolling($pdo, $uid);

        $loyaltyStmt = $pdo->prepare('SELECT booking_last_30_days FROM loyalty_accounts WHERE driver_id=?');
        $loyaltyStmt->execute([$uid]);
        $loy = $loyaltyStmt->fetch();
        $loyalty_discount = 0;
        if ($loy) {
            $b = $loy['booking_last_30_days'];
            if ($b >= 20) {
                $loyalty_discount = 15;
            } elseif ($b >= 10) {
                $loyalty_discount = 10;
            } elseif ($b >= 5) {
                $loyalty_discount = 5;
            }
        }

        $error = '';
        $preview = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['waitlist_spot_action'])) {
            $data = self::processBookingForm($_POST, $spot, $vehicles, $uid, $loyalty_discount, $pdo, $bookingManager);
            $error = $data['error'];
            $preview = $data['preview'];
            $recommendations = $data['recommendations'] ?? $recommendations;
        }

        $alt_ctx = [
            'start' => trim((string)($_POST['start_time'] ?? $_GET['start'] ?? '')),
            'end' => trim((string)($_POST['end_time'] ?? $_GET['end'] ?? '')),
            'booking_mode' => ($_POST['booking_mode'] ?? $_GET['booking_mode'] ?? 'one_time') === 'subscription' ? 'subscription' : 'one_time',
        ];

        $time_based_waitlist = false;
        if ($alt_ctx['booking_mode'] === 'one_time') {
            $time_based_waitlist = self::isOneTimeWindowBlockedForSpot(
                $spot,
                $listingOk,
                $alt_ctx['start'],
                $alt_ctx['end'],
                $bookingManager
            );
        } elseif (
            $alt_ctx['booking_mode'] === 'subscription'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && !isset($_POST['waitlist_spot_action'])
        ) {
            $time_based_waitlist = self::isSubscriptionScheduleBlockedMessage($error);
        }

        $show_spot_waitlist = $on_spot_waitlist || $time_based_waitlist;

        $current_fav_label = null;
        $favStmt = $pdo->prepare('SELECT custom_label FROM favorite_spots WHERE driver_id=? AND spot_id=? LIMIT 1');
        $favStmt->execute([$uid, $spot_id]);
        $favRow = $favStmt->fetch();
        if ($favRow) {
            $lbl = trim((string)($favRow['custom_label'] ?? ''));
            if ($lbl !== '') {
                // Keep consistent casing so the UI can match labels reliably.
                if (strcasecmp($lbl, 'home') === 0) {
                    $lbl = 'Home';
                } elseif (strcasecmp($lbl, 'work') === 0) {
                    $lbl = 'Work';
                }
                $current_fav_label = $lbl;
            }
        }

        self::render('driver/book', [
            'spot' => $spot,
            'vehicles' => $vehicles,
            'loyalty_discount' => $loyalty_discount,
            'err' => $error ?: $availabilityError,
            'preview' => $preview,
            'recommendations' => $recommendations,
            'on_spot_waitlist' => $on_spot_waitlist,
            'show_spot_waitlist' => $show_spot_waitlist,
            'alt_ctx' => $alt_ctx,
            'current_fav_label' => $current_fav_label,
            'pageTitle' => 'Book Spot',
        ]);
    }

    /** Per-spot waitlist join: only when a concrete one-time window is outside owner hours or conflicts (incl. buffer). */
    private static function isOneTimeWindowBlockedForSpot(
        array $spot,
        bool $listingOk,
        string $startRaw,
        string $endRaw,
        BookingManager $bookingManager
    ): bool {
        if (!$listingOk) {
            return false;
        }
        if (($spot['status'] ?? '') !== 'available' || ($spot['zone_status'] ?? '') === 'locked') {
            return false;
        }

        $rangeCheck = ParkingBookingValidator::validateClientDateTimePair($startRaw, $endRaw);
        if (isset($rangeCheck['error'])) {
            return false;
        }
        $start = $rangeCheck['start'];
        $end = $rangeCheck['end'];

        if (ParkingBookingValidator::reservationFitsOwnerAvailability(
            $start,
            $end,
            $spot['availability_start'] ?? null,
            $spot['availability_end'] ?? null
        ) !== null) {
            return true;
        }

        $bufferMins = $bookingManager->getBufferMinutes($spot);

        return $bookingManager->hasBufferedConflict((int)$spot['spot_id'], $start, $end, $bufferMins);
    }

    private static function isSubscriptionScheduleBlockedMessage(string $error): bool
    {
        if ($error === '') {
            return false;
        }
        return str_contains($error, 'One or more recurring slots conflict')
            || str_contains($error, 'A recurring slot conflicted during confirmation')
            || str_contains($error, 'Affected recurring slot:');
    }

    /**
     * @param array<string,mixed>|null $vehRow vehicle_profiles row
     * @return array{height_cm:mixed,width_cm:mixed,is_ev_capable:bool}|null
     */
    private static function vehicleProfileForAlternatives(?array $vehRow): ?array
    {
        if (!$vehRow) {
            return null;
        }

        return [
            'height_cm' => $vehRow['height_cm'] ?? null,
            'width_cm' => $vehRow['width_cm'] ?? null,
            'is_ev_capable' => !empty($vehRow['is_ev_capable']),
        ];
    }

    private static function processBookingForm(array $post, array $spot, array $vehicles, int $uid, int $loyalty_discount, PDO $pdo, BookingManager $bookingManager): array
    {
        $vehicle_id = (int)($post['vehicle_id'] ?? 0);
        $promo = strtoupper(trim($post['promo_code'] ?? ''));
        $card_name = trim($post['card_name'] ?? '');
        $card_number = preg_replace('/\D/', '', $post['card_number'] ?? '');
        $card_expiry_month = $post['card_expiry_month'] ?? '';
        $card_expiry_year = $post['card_expiry_year'] ?? '';
        if (strlen($card_expiry_year) === 4) {
            $card_expiry_year = substr($card_expiry_year, -2);
        }
        $card_expiry = $card_expiry_month . '/' . $card_expiry_year;
        $card_cvv = trim($post['card_cvv'] ?? '');
        $action = $post['action'] ?? 'preview';
        $booking_mode = $post['booking_mode'] ?? 'one_time';

        if (($spot['spot_approval_status'] ?? SpotApprovalModel::STATUS_APPROVED) !== SpotApprovalModel::STATUS_APPROVED) {
            return [
                'error' => 'This parking spot has not been approved by an admin yet.',
                'preview' => null,
                'recommendations' => [],
            ];
        }

        if ($spot['status'] !== 'available' || $spot['zone_status'] === 'locked') {
            $rec = $bookingManager->getAlternativeSpots($spot, 5);
            foreach ($rec as &$rr) {
                $rr['fits_requested_window'] = false;
            }
            unset($rr);

            return [
                'error' => 'The selected spot is no longer available.',
                'preview' => null,
                'recommendations' => $rec,
            ];
        }

        if (empty($vehicles)) {
            return ['error' => 'You must add a vehicle before booking. <a href="' . htmlspecialchars(route_url('/driver/vehicles')) . '">Add vehicle</a>', 'preview' => null];
        }
        if (!$vehicle_id) {
            return ['error' => 'Please select a vehicle.', 'preview' => null];
        }

        $vehicleStmt = $pdo->prepare('SELECT height_cm, width_cm, is_ev_capable FROM vehicle_profiles WHERE vehicle_id=? AND owner_id=?');
        $vehicleStmt->execute([$vehicle_id, $uid]);
        $veh = $vehicleStmt->fetch();
        if (!$veh) {
            return ['error' => 'Invalid vehicle selected.', 'preview' => null];
        }
        if (($veh['height_cm'] && $spot['height_cm'] && $veh['height_cm'] > $spot['height_cm']) || ($veh['width_cm'] && $spot['width_cm'] && $veh['width_cm'] > $spot['width_cm']) || ($veh['is_ev_capable'] && !$spot['has_ev_charger'])) {
            return ['error' => 'Your vehicle does not fit this parking spot.', 'preview' => null];
        }

        if ($booking_mode === 'subscription') {
            return self::processSubscriptionBooking($post, $spot, $vehicle_id, $veh, $uid, $loyalty_discount, $pdo, $bookingManager, $action, $promo);
        }

        $rangeCheck = ParkingBookingValidator::validateClientDateTimePair($post['start_time'] ?? null, $post['end_time'] ?? null);
        if (isset($rangeCheck['error'])) {
            return ['error' => $rangeCheck['error'], 'preview' => null];
        }
        $start = $rangeCheck['start'];
        $end = $rangeCheck['end'];

        $outsideOwnerWindow = ParkingBookingValidator::reservationFitsOwnerAvailability(
            $start,
            $end,
            $spot['availability_start'] ?? null,
            $spot['availability_end'] ?? null
        );
        if ($outsideOwnerWindow !== null) {
            return ['error' => $outsideOwnerWindow, 'preview' => null];
        }

        $bufferMins = $bookingManager->getBufferMinutes($spot);
        if ($bookingManager->hasBufferedConflict((int)$spot['spot_id'], $start, $end, $bufferMins)) {
            return [
                'error' => 'This time overlaps an existing booking on this spot (including the required buffer). Pick another time, try a nearby spot, or join the waitlist below.',
                'preview' => null,
                'recommendations' => $bookingManager->getRankedAlternatives(
                    $spot,
                    $start,
                    $end,
                    null,
                    5,
                    self::vehicleProfileForAlternatives($veh)
                ),
            ];
        }

        $cost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $start, $end, 0);
        if (($cost['promo_error'] ?? '') !== '') {
            return ['error' => $cost['promo_error'], 'preview' => null];
        }
        if ($action === 'preview') {
            return ['error' => '', 'preview' => array_merge($cost, ['start' => $start, 'end' => $end, 'vehicle_id' => $vehicle_id, 'promo' => $promo, 'booking_mode' => 'one_time'])];
        }

        if (!$card_name) {
            return ['error' => 'Please enter the cardholder name.', 'preview' => null];
        }
        if (!$card_number || strlen($card_number) < 13 || strlen($card_number) > 19) {
            return ['error' => 'Please enter a valid credit card number.', 'preview' => null];
        }
        if (!$card_expiry_month || !$card_expiry_year || !preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $card_expiry, $exp)) {
            return ['error' => 'Please enter a valid expiry date.', 'preview' => null];
        }
        if (!preg_match('/^[0-9]{3,4}$/', $card_cvv)) {
            return ['error' => 'Please enter a valid CVV.', 'preview' => null];
        }

        $month = (int)$exp[1];
        $year = 2000 + (int)$exp[2];
        $expiry_ts = mktime(23, 59, 59, $month + 1, 0, $year);
        if ($expiry_ts < time()) {
            return ['error' => 'This credit card has expired.', 'preview' => null];
        }

        $pdo->beginTransaction();
        try {
            if (
                ParkingBookingValidator::reservationFitsOwnerAvailability($start, $end, $spot['availability_start'] ?? null, $spot['availability_end'] ?? null) !== null
                || $bookingManager->hasBufferedConflict((int)$spot['spot_id'], $start, $end, $bufferMins)
            ) {
                $pdo->rollBack();
                return [
                    'error' => 'This slot is no longer available for the selected window. Please preview again.',
                    'preview' => null,
                    'recommendations' => $bookingManager->getRankedAlternatives(
                        $spot,
                        $start,
                        $end,
                        null,
                        5,
                        self::vehicleProfileForAlternatives($veh)
                    ),
                ];
            }

            $cost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $start, $end, 0);
            if (($cost['promo_error'] ?? '') !== '') {
                $pdo->rollBack();
                return ['error' => $cost['promo_error'], 'preview' => null];
            }

            $buffer_end = date('Y-m-d H:i:s', strtotime($end) + $bufferMins * 60);
            $qr_token = bin2hex(random_bytes(16));
            $pay_id = PaymentProcessingService::insertHeldPayment(
                $pdo,
                new CreditCardPaymentStrategy(),
                $uid,
                $cost,
                ['card_number' => $card_number]
            );

            $r = $pdo->prepare('INSERT INTO reservations (driver_id, spot_id, vehicle_id, payment_id, start_time, end_time, buffer_end_time, status, qr_code_token, base_cost, tax_amount, discount_amount, final_cost, escrow_amount, promo_code, buffer_applied, grace_period_mins) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $r->execute([$uid, $spot['spot_id'], $vehicle_id ?: null, $pay_id, $start, $end, $buffer_end, 'confirmed', $qr_token, $cost['base'], $cost['tax'], $cost['discount'], $cost['total'], $cost['escrow'], $promo ?: null, 1, 5]);
            $res_id = $pdo->lastInsertId();
            (new PaymentModel($pdo))->lockFunds((int)$res_id, (float)$cost['total']);

            if ($promo) {
                $pdo->prepare('UPDATE promo_codes SET usage_count = usage_count + 1 WHERE code=?')->execute([$promo]);
            }
            // Update loyalty points and rolling 30-day booking count/tier.
            $pdo->prepare('UPDATE loyalty_accounts SET total_points = total_points + ? WHERE driver_id=?')->execute([$cost['total'] * 0.1, $uid]);
            self::refreshLoyaltyRolling($pdo, $uid);

            // Update platform commission aggregator for this successful booking.
            $commissionAmt = round($cost['total'] * 0.15, 2);
            $pdo->prepare('UPDATE platform_account SET total_commission = total_commission + ?')->execute([$commissionAmt]);
            ReservationSubject::getInstance()->notifyObservers(
                ReservationEvent::bookingCreated(
                    $pdo,
                    $uid,
                    (int)$spot['owner_id'],
                    (int)$spot['spot_id'],
                    (string)$spot['address'],
                    $start,
                    (int)$res_id
                )
            );
            $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)')->execute(['RESERVATION_CREATED', $res_id, $uid, $spot['spot_id'], 'confirmed']);
            $pdo->commit();
            flash('ok', 'Booking confirmed! Your QR token: ' . $qr_token);
            redirect(route_url('/driver/bookingdetail?id=' . $res_id));
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['error' => 'Booking failed. Please try again.', 'preview' => null];
        }
    }

    /**
     * @param list<array{start:string,end:string}> $slots
     */
    private static function latestReservationEndAmongSlots(array $slots): string
    {
        $bestEnd = '';
        $bestTs = 0;
        foreach ($slots as $s) {
            $ts = strtotime($s['end'] ?? '');
            if ($ts !== false && $ts >= $bestTs) {
                $bestTs = $ts;
                $bestEnd = (string)$s['end'];
            }
        }
        return $bestEnd !== '' ? $bestEnd : (string)($slots[0]['end'] ?? '');
    }

    /**
     * @param ?string $promoBookingWindowEnd latest datetime in the booked window — promo expiry must stay >= this
     */
    private static function calculateBookingCost(
        array $spot,
        PDO $pdo,
        string $promo_code,
        int $loyalty_discount,
        string $start,
        string $end,
        float $extraDiscountPercent = 0,
        ?string $promoBookingWindowEnd = null
    ): array {
        $hours = max(0.5, (strtotime($end) - strtotime($start)) / 3600);
        $mult = $spot['default_multiplier'] ?? 1.0;
        $pricingEngine = new PricingEngine();
        $baseBeforePeak = $pricingEngine->calculatePrice($hours, (float)$spot['base_rate'] * (float)$mult);
        $peakData = $pricingEngine->calculatePriceWithPeak((float)$baseBeforePeak, $start);
        $base = $peakData['final_price'];
        $vat_rate = (float)($spot['vat_rate'] ?? ParkingSystemConfig::getInstance()->taxRate);
        $discount = 0;
        $promoBookingWindowEnd = ($promoBookingWindowEnd ?? '') !== '' ? $promoBookingWindowEnd : $end;

        $promo_error = '';
        if ($promo_code !== '') {
            $pc = PromotionalCodeValidator::getRowForBookingWindow($pdo, $promo_code, $promoBookingWindowEnd);
            if ($pc === null) {
                $promo_error = PromotionalCodeValidator::promoInvalidBookingMessage();
            } else {
                $discount = $pc['discount_type'] === 'percentage'
                    ? round($base * (float)$pc['discount_value'] / 100, 2)
                    : min((float)$pc['discount_value'], $base);
            }
        }
        $loyalty_disc = round($base * $loyalty_discount / 100, 2);
        $discount = max($discount, $loyalty_disc);
        if ($extraDiscountPercent > 0) {
            $discount = max($discount, round($base * $extraDiscountPercent / 100, 2));
        }
        $taxable = max(0, $base - $discount);
        $tax = (new TaxEngine())->calculateTax($taxable, $vat_rate);
        $total = $taxable + $tax;
        $escrow = round($total * 1.15, 2);
        return array_merge(compact('base', 'discount', 'tax', 'total', 'escrow', 'hours'), [
            'base_before_peak' => $baseBeforePeak,
            'peak_adjustment' => $peakData['adjustment_amount'],
            'peak_multiplier' => $peakData['multiplier'],
            'peak_applied' => $peakData['is_peak'],
            'peak_reason' => $peakData['reason'],
            'promo_error' => $promo_error,
        ]);
    }

    private static function refreshLoyaltyRolling(PDO $pdo, int $uid): void
    {
        // Rolling 30-day booking count (using reservation creation time).
        $cntStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations WHERE driver_id=? AND status IN ('confirmed','active','completed') AND created_at >= (NOW() - INTERVAL 30 DAY)"
        );
        $cntStmt->execute([$uid]);
        $count = (int)$cntStmt->fetchColumn();

        // Tier mapping requested: bronze < 5, silver >= 5, gold >= 20.
        $tier = $count >= 20 ? 'gold' : ($count >= 5 ? 'silver' : 'bronze');

        $pdo->prepare('UPDATE loyalty_accounts SET booking_last_30_days=?, current_tier=? WHERE driver_id=?')
            ->execute([$count, $tier, $uid]);
    }

    private static function processSubscriptionBooking(
        array $post,
        array $spot,
        int $vehicle_id,
        ?array $veh,
        int $uid,
        int $loyalty_discount,
        PDO $pdo,
        BookingManager $bookingManager,
        string $action,
        string $promo
    ): array {
        $sub_discount = 15.0; // Assumption: commuter plan gets fixed 15% discount.

        if (($spot['spot_approval_status'] ?? SpotApprovalModel::STATUS_APPROVED) !== SpotApprovalModel::STATUS_APPROVED) {
            return ['error' => 'This parking spot has not been approved by an admin yet.', 'preview' => null];
        }

        // Subscription must not mixed with one-time datetime fields (POST tampering defense).
        $mixedOneTimeErr = ParkingBookingValidator::subscriptionRejectOneTimeFieldsPresent($post['start_time'] ?? null, $post['end_time'] ?? null);
        if ($mixedOneTimeErr !== null) {
            return ['error' => $mixedOneTimeErr, 'preview' => null];
        }

        $daysErr = ParkingBookingValidator::validateSubscriptionDaysSelected(isset($post['sub_days']) && is_array($post['sub_days']) ? $post['sub_days'] : null);
        if ($daysErr !== null) {
            return ['error' => $daysErr, 'preview' => null];
        }

        /** @var array<mixed> $daysRaw */
        $daysRaw = $post['sub_days'] ?? [];
        $daysInts = [];
        foreach ($daysRaw as $d) {
            $n = (int)$d;
            if ($n >= 1 && $n <= 7) {
                $daysInts[] = $n;
            }
        }
        $daysInts = array_values(array_unique($daysInts));
        sort($daysInts);
        if ($daysInts === []) {
            return ['error' => 'Please select at least one day of the week', 'preview' => null];
        }

        $start_time_of_day = trim((string)($post['sub_start_time'] ?? ''));
        $end_time_of_day = trim((string)($post['sub_end_time'] ?? ''));
        if ($start_time_of_day === '' || $end_time_of_day === '') {
            return ['error' => 'Please provide recurring start time and recurring end time.', 'preview' => null];
        }

        if (isset($post['sub_weeks']) && trim((string)$post['sub_weeks']) !== '') {
            return ['error' => 'Duration (weeks) is calculated automatically—do not submit a value for it.', 'preview' => null];
        }

        $period = ParkingBookingValidator::validateSubscriptionDateRange(
            $post['sub_start_date'] ?? null,
            $post['sub_end_date'] ?? null
        );
        if (isset($period['error'])) {
            return ['error' => $period['error'], 'preview' => null];
        }

        $tod = ParkingBookingValidator::validateSubscriptionTimeOfDayAndWindow(
            $start_time_of_day,
            $end_time_of_day,
            $spot['availability_start'] ?? null,
            $spot['availability_end'] ?? null
        );
        if (isset($tod['error'])) {
            return ['error' => $tod['error'], 'preview' => null];
        }

        $slots = $bookingManager->generateSubscriptionSlots(
            $period['start_date'],
            $period['end_date'],
            $daysInts,
            $tod['start'],
            $tod['end']
        );
        if (empty($slots)) {
            return ['error' => 'No valid future slots were generated for this subscription.', 'preview' => null];
        }

        foreach ($slots as $slot) {
            $availErr = ParkingBookingValidator::reservationFitsOwnerAvailability(
                $slot['start'],
                $slot['end'],
                $spot['availability_start'] ?? null,
                $spot['availability_end'] ?? null
            );
            if ($availErr !== null) {
                return ['error' => $availErr . ' Affected recurring slot: ' . $slot['start'] . '.', 'preview' => null];
            }
        }

        $subBuffer = $bookingManager->getBufferMinutes($spot);
        foreach ($slots as $slot) {
            if ($bookingManager->hasBufferedConflict((int)$spot['spot_id'], $slot['start'], $slot['end'], $subBuffer)) {
                return [
                    'error' => 'One or more recurring slots conflict with existing bookings on this spot (including buffers). Try nearby spots or join the waitlist.',
                    'preview' => null,
                    'recommendations' => $bookingManager->getRankedAlternatives(
                        $spot,
                        null,
                        null,
                        $slots,
                        5,
                        self::vehicleProfileForAlternatives($veh)
                    ),
                ];
            }
        }

        $latestBookingEnd = self::latestReservationEndAmongSlots($slots);
        $firstCost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $slots[0]['start'], $slots[0]['end'], $sub_discount, $latestBookingEnd);
        if (($firstCost['promo_error'] ?? '') !== '') {
            return ['error' => $firstCost['promo_error'], 'preview' => null];
        }
        $preview = array_merge($firstCost, [
            'booking_mode' => 'subscription',
            'subscription_discount_percent' => $sub_discount,
            'slots_count' => count($slots),
            'weeks' => $period['duration_weeks'],
            'sub_period_start_date' => $period['start_date'],
            'sub_period_end_date' => $period['end_date'],
        ]);

        if ($action === 'preview') {
            return ['error' => '', 'preview' => $preview];
        }

        $pdo->beginTransaction();
        try {
            $sub = $pdo->prepare('INSERT INTO commuter_subscriptions (driver_id, spot_id, vehicle_id, days_of_week, start_time_of_day, end_time_of_day, weeks, discount_percent, status) VALUES (?,?,?,?,?,?,?,?,?)');
            $sub->execute([$uid, $spot['spot_id'], $vehicle_id, implode(',', $daysInts), $tod['start'], $tod['end'], $period['duration_weeks'], $sub_discount, 'active']);
            $subscription_id = $pdo->lastInsertId();

            $totalPoints = 0.0;
            $totalCommission = 0.0;
            foreach ($slots as $slot) {
                if (
                    ParkingBookingValidator::reservationFitsOwnerAvailability(
                        $slot['start'],
                        $slot['end'],
                        $spot['availability_start'] ?? null,
                        $spot['availability_end'] ?? null
                    ) !== null
                    || $bookingManager->hasBufferedConflict((int)$spot['spot_id'], $slot['start'], $slot['end'], $subBuffer)
                ) {
                    $pdo->rollBack();
                    return [
                        'error' => 'A recurring slot conflicted during confirmation. Preview again.',
                        'preview' => null,
                        'recommendations' => $bookingManager->getRankedAlternatives(
                            $spot,
                            null,
                            null,
                            $slots,
                            5,
                            self::vehicleProfileForAlternatives($veh)
                        ),
                    ];
                }

                $cost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $slot['start'], $slot['end'], $sub_discount, $latestBookingEnd);
                if (($cost['promo_error'] ?? '') !== '') {
                    $pdo->rollBack();
                    return ['error' => $cost['promo_error'], 'preview' => null];
                }
                $commissionAmt = round($cost['total'] * 0.15, 2);
                $pay_id = PaymentProcessingService::insertHeldPayment(
                    $pdo,
                    new SubscriptionPaymentStrategy(),
                    $uid,
                    $cost,
                    []
                );
                $buffer_end = date('Y-m-d H:i:s', strtotime($slot['end']) + $subBuffer * 60);
                $qr_token = bin2hex(random_bytes(16));
                $r = $pdo->prepare('INSERT INTO reservations (driver_id, spot_id, vehicle_id, payment_id, subscription_id, start_time, end_time, buffer_end_time, status, qr_code_token, base_cost, tax_amount, discount_amount, final_cost, escrow_amount, promo_code, buffer_applied, grace_period_mins) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $r->execute([$uid, $spot['spot_id'], $vehicle_id, $pay_id, $subscription_id, $slot['start'], $slot['end'], $buffer_end, 'confirmed', $qr_token, $cost['base'], $cost['tax'], $cost['discount'], $cost['total'], $cost['escrow'], $promo ?: null, 1, 5]);
                $res_id = (int)$pdo->lastInsertId();
                (new PaymentModel($pdo))->lockFunds($res_id, (float)$cost['total']);
                $totalPoints += ((float)$cost['total']) * 0.1;
                $totalCommission += (float)$commissionAmt;
            }
            $pdo->commit();

            // Mirror one-time booking side effects: loyalty points + platform commission.
            $pdo->prepare('UPDATE loyalty_accounts SET total_points = total_points + ? WHERE driver_id=?')->execute([$totalPoints, $uid]);
            self::refreshLoyaltyRolling($pdo, $uid);

            // Update platform commission aggregator for all subscription slots.
            $pdo->prepare('UPDATE platform_account SET total_commission = total_commission + ?')->execute([$totalCommission]);

            if ($promo !== '') {
                $pdo->prepare('UPDATE promo_codes SET usage_count = usage_count + ? WHERE code=?')->execute([count($slots), $promo]);
            }

            $addr = (string)($spot['address'] ?? 'the selected spot');
            ReservationSubject::getInstance()->notifyObservers(
                ReservationEvent::subscriptionCreated(
                    $pdo,
                    $uid,
                    (int)($spot['owner_id'] ?? 0),
                    (int)$spot['spot_id'],
                    $addr
                )
            );

            flash('ok', 'Subscription created. Recurring reservations were generated successfully.');
            redirect(route_url('/driver/bookings?tab=bookings&status=confirmed'));
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['error' => 'Subscription booking failed. Please try again.', 'preview' => null];
        }
    }

    public static function cancelSubscription(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        new PaymentModel($pdo);
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();

        $u = current_user();
        $uid = (int)$u['id'];
        $subscription_id = (int)($_POST['subscription_id'] ?? 0);
        if ($subscription_id <= 0) {
            flash('err', 'Subscription id is required.');
            redirect(route_url('/driver/bookings'));
        }

        // Ensure this subscription belongs to this driver and is cancellable.
        $subStmt = $pdo->prepare('SELECT subscription_id, status FROM commuter_subscriptions WHERE subscription_id=? AND driver_id=? LIMIT 1');
        $subStmt->execute([$subscription_id, $uid]);
        $sub = $subStmt->fetch();
        if (!$sub) {
            flash('err', 'Subscription not found.');
            redirect(route_url('/driver/bookings'));
        }
        if (($sub['status'] ?? '') === 'cancelled') {
            flash('ok', 'Subscription already cancelled.');
            redirect(route_url('/driver/bookings'));
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE commuter_subscriptions SET status="cancelled" WHERE subscription_id=? AND driver_id=?')
                ->execute([$subscription_id, $uid]);

            // Cancel only future reservations (including confirmed/pending), leave already-started ones untouched.
            $futureStmt = $pdo->prepare(
                'SELECT r.reservation_id, r.start_time, r.final_cost, r.status, r.spot_id, ps.owner_id, ps.address, r.payment_id
                 FROM reservations r
                 JOIN parking_spots ps ON ps.spot_id = r.spot_id
                 WHERE r.subscription_id=? AND r.driver_id=? AND r.start_time >= NOW()
                   AND r.status IN ("confirmed","pending","active")'
            );
            $futureStmt->execute([$subscription_id, $uid]);
            $future = $futureStmt->fetchAll();

            foreach ($future as $rr) {
                $rid = (int)$rr['reservation_id'];
                $paymentId = (int)($rr['payment_id'] ?? 0);
                $startTs = strtotime((string)$rr['start_time']);
                $now = time();
                $refund = 0;
                if ($startTs !== false && ($startTs - $now) > 7200) {
                    $refund = 100;
                } elseif ($startTs !== false && ($startTs - $now) > 3600) {
                    $refund = 50;
                }

                $pdo->prepare("UPDATE reservations SET status='cancelled', cancelled_at=NOW() WHERE reservation_id=?")->execute([$rid]);
                if ($paymentId > 0) {
                    $pdo->prepare("UPDATE payments SET refund_percent=?, refund_amount=? WHERE payment_id=?")
                        ->execute([$refund, round(((float)($rr['final_cost'] ?? 0.0)) * $refund / 100, 2), $paymentId]);
                }
                (new PaymentModel($pdo))->refundFunds($rid);

                // Reverse refunded owner share for the 85% revenue portion (safe-guard against negatives).
                $ownerShareFull = round(((float)($rr['final_cost'] ?? 0.0)) * 0.85, 2);
                $refundedOwnerShare = round($ownerShareFull * ($refund / 100), 2);
                if ($refundedOwnerShare > 0) {
                    $pdo->prepare('UPDATE space_owners SET earnings_balance = GREATEST(0, earnings_balance - ?) WHERE owner_id=?')
                        ->execute([$refundedOwnerShare, (int)$rr['owner_id']]);
                }

                // Update spot status based on remaining confirmed/active reservations.
                $otherActive = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE spot_id=? AND reservation_id<>? AND status IN ("confirmed","active")');
                $otherActive->execute([(int)$rr['spot_id'], $rid]);
                $otherCount = (int)$otherActive->fetchColumn();
                if ($otherCount === 0) {
                    $pdo->prepare("UPDATE parking_spots SET status='available' WHERE spot_id=?")->execute([(int)$rr['spot_id']]);
                } else {
                    $pdo->prepare("UPDATE parking_spots SET status='reserved' WHERE spot_id=?")->execute([(int)$rr['spot_id']]);
                }

                // Notify next waitlisted driver (if any) for this spot.
                $wait = $pdo->prepare('SELECT driver_id FROM waitlist WHERE spot_id=? ORDER BY joined_at ASC LIMIT 1');
                $wait->execute([(int)$rr['spot_id']]);
                $wdriver = $wait->fetch();
                if ($wdriver) {
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')
                        ->execute([(int)$wdriver['driver_id'], 'in_app', "A spot you are next in line for ({$rr['address']}) just became available!", 'waitlist', 'sent']);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('err', 'Failed to cancel subscription. Please try again.');
            redirect(route_url('/driver/bookings'));
        }

        flash('ok', 'Subscription cancelled. Future reservations were cancelled.');
        redirect(route_url('/driver/bookings'));
    }

    public static function bookings(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();
        $u = current_user();
        $uid = (int)$u['id'];

        $tab = (($_GET['tab'] ?? '') === 'waitlist') ? 'waitlist' : 'bookings';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'leave_booking_waitlist') {
            $sid = (int)($_POST['spot_id'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare('DELETE FROM waitlist WHERE driver_id=? AND spot_id=?')->execute([$uid, $sid]);
                flash('ok', 'Removed from waitlist.');
            }
            redirect(route_url('/driver/bookings?tab=waitlist'));
        }

        $filter = $_GET['status'] ?? 'all';
        $where = 'r.driver_id = ?';
        $params = [$uid];
        if ($filter !== 'all') {
            $where .= ' AND r.status = ?';
            $params[] = $filter;
        }
        $stmt = $pdo->prepare("SELECT r.reservation_id, r.start_time, r.end_time, r.status, r.final_cost, r.qr_code_token, r.subscription_id, ps.address, p.payment_status FROM reservations r JOIN parking_spots ps ON r.spot_id = ps.spot_id LEFT JOIN payments p ON p.payment_id = r.payment_id WHERE $where ORDER BY r.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $subs = $pdo->prepare(
            'SELECT cs.subscription_id, cs.days_of_week, cs.start_time_of_day, cs.end_time_of_day, cs.weeks, cs.discount_percent, cs.status, ps.address
             FROM commuter_subscriptions cs
             JOIN parking_spots ps ON ps.spot_id = cs.spot_id
             WHERE cs.driver_id = ? AND cs.status = "active"
             ORDER BY cs.created_at DESC'
        );
        $subs->execute([$uid]);
        $subscriptions = $subs->fetchAll();

        $waitlistStmt = $pdo->prepare(
            'SELECT w.waitlist_id, w.joined_at, ps.spot_id, ps.address, ps.base_rate, ps.status AS spot_status, ps.latitude, ps.longitude
             FROM waitlist w
             INNER JOIN parking_spots ps ON ps.spot_id = w.spot_id
             WHERE w.driver_id = ?
             ORDER BY w.joined_at DESC'
        );
        $waitlistStmt->execute([$uid]);
        $waitlist_entries = $waitlistStmt->fetchAll();

        self::render('driver/bookings', [
            'rows' => $rows,
            'subscriptions' => $subscriptions,
            'waitlist_entries' => $waitlist_entries,
            'filter' => $filter,
            'tab' => $tab,
            'pageTitle' => 'My Bookings',
        ]);
    }

    public static function bookingDetail(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        new PaymentModel($pdo);
        $u = current_user();
        $uid = $u['id'];
        $id = (int)($_GET['id'] ?? 0);
        $res = $pdo->prepare('SELECT r.*, ps.address, ps.latitude, ps.longitude, ps.base_rate, ps.owner_id, ps.availability_start, ps.availability_end, vp.license_plate, pe.default_multiplier, bm.buffer_duration_mins, p.payment_status, p.escrow_status FROM reservations r JOIN parking_spots ps ON r.spot_id = ps.spot_id LEFT JOIN vehicle_profiles vp ON r.vehicle_id = vp.vehicle_id LEFT JOIN pricing_engine pe ON pe.spot_id = ps.spot_id LEFT JOIN buffer_manager bm ON bm.spot_id = ps.spot_id LEFT JOIN payments p ON p.payment_id = r.payment_id WHERE r.reservation_id = ? AND r.driver_id = ?');
        $res->execute([$id, $uid]);
        $r = $res->fetch();
        if (!$r) {
            redirect(route_url('/driver/bookings'));
        }

        $reviewed_owner = false;
        if ($r['status'] === 'completed') {
            $reviewed_owner = (new ReviewModel($pdo))->hasReviewForReservation((int)$r['owner_id'], $uid, $id);
        }

        // Enforce booking eligibility for any state-changing actions.
        $driverEligibility = $pdo->prepare('SELECT can_book, account_status FROM drivers WHERE driver_id=? LIMIT 1');
        $driverEligibility->execute([$uid]);
        $elig = $driverEligibility->fetch() ?: ['can_book' => 0, 'account_status' => 'active'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ((string)($elig['account_status'] ?? 'active') !== 'active') {
                flash('err', 'Your driver account is not active. Booking actions are disabled.');
                redirect(route_url('/driver/dashboard'));
            }
            if (!(int)($elig['can_book'] ?? 0)) {
                flash('err', 'Your account is suspended from making bookings due to unpaid fines.');
                redirect(route_url('/driver/dashboard'));
            }
            $act = $_POST['action'] ?? '';
            if ($act === 'checkin' && $r['status'] === 'confirmed') {
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE reservations SET status='active', check_in_time=?, arrival_time=? WHERE reservation_id=?")->execute([$now, $now, $id]);
                // Keep spot as "available" in parking_spots so it still appears on Find Parking / map.
                // Occupancy is tracked by reservations (active/confirmed) + buffer conflict checks on booking.
                $pdo->prepare('INSERT INTO parking_sessions (reservation_id, driver_id, spot_id, start_time, status) VALUES (?,?,?,?,?)')->execute([$id, $uid, $r['spot_id'], $now, 'active']);
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)')->execute(['QR_CHECKIN', $id, $uid, $r['spot_id'], 'active']);
                flash('ok', 'Checked in successfully.');
                redirect(route_url('/driver/bookingdetail?id=' . $id));
            }
            if ($act === 'checkout' && $r['status'] === 'active') {
                $now = date('Y-m-d H:i:s');
                $penaltyBreakdown = (new PenaltyModel($pdo))->calculateOverstayPenaltyBreakdown($r['end_time'], $now);
                $overstay = (int)($penaltyBreakdown['overstay_minutes'] ?? 0);
                $penalty = (float)($penaltyBreakdown['penalty_amount'] ?? 0.0);
                $updatedTotal = (float)$r['final_cost'] + $penalty;
                $pdo->prepare("UPDATE reservations SET status='completed', check_out_time=?, overstay_minutes=?, penalty_amount=?, final_cost=? WHERE reservation_id=?")->execute([$now, $overstay, $penalty, $updatedTotal, $id]);
                ReservationSubject::getInstance()->notifyObservers(ReservationEvent::bookingCompleted($pdo, $uid, $r));
                $pdo->prepare("UPDATE parking_sessions SET end_time=?, status=?, duration_mins=? WHERE reservation_id=?")->execute([$now, $overstay > 0 ? 'overstay' : 'completed', (int)((strtotime($now) - strtotime($r['check_in_time'])) / 60), $id]);
                $pdo->prepare("UPDATE payments SET final_amount = final_amount + ? WHERE payment_id=?")->execute([$penalty, $r['payment_id']]);
                (new PaymentModel($pdo))->releaseFunds($id);
                // Owners should only receive revenue for the confirmed booking cost (excluding overstay penalty).
                $owner_share = round(((float)($r['final_cost'] ?? 0.0)) * 0.85, 2);
                $pdo->prepare("UPDATE space_owners SET earnings_balance = earnings_balance + ? WHERE owner_id=?")->execute([$owner_share, $r['owner_id']]);
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)')->execute(['QR_CHECKOUT', $id, $uid, $r['spot_id'], 'completed']);
                flash('ok', 'Checked out.' . ($penalty > 0 ? " Overstay penalty applied: {$penalty} EGP." : ''));
                redirect(route_url('/driver/bookingdetail?id=' . $id));
            }
            if ($act === 'rate_owner' && $r['status'] === 'completed') {
                $ownerId = (int)$r['owner_id'];
                $rating = (int)($_POST['rating'] ?? 0);
                $comment = trim((string)($_POST['comment'] ?? ''));
                if ($ownerId <= 0 || $rating < 1 || $rating > 5) {
                    flash('err', 'Please choose a rating from 1 to 5.');
                    redirect(route_url('/driver/bookingdetail?id=' . $id));
                }
                $rm = new ReviewModel($pdo);
                if ($rm->hasReviewForReservation($ownerId, $uid, $id)) {
                    flash('err', 'You already rated this owner for this reservation.');
                    redirect(route_url('/driver/bookingdetail?id=' . $id));
                }
                $rm->addOwnerReview($ownerId, $uid, $id, $rating, $comment);
                $rm->recomputeOwnerTrustScore($ownerId);
                flash('ok', 'Thanks! Your review was submitted.');
                redirect(route_url('/driver/bookingdetail?id=' . $id));
            }
            if ($act === 'extend' && $r['status'] === 'active') {
                $extra_mins = (int)($_POST['extra_mins'] ?? 30);
                if ($extra_mins < self::BOOKING_EXTENSION_MIN_MINS || $extra_mins > self::BOOKING_EXTENSION_MAX_MINS) {
                    flash('err', 'Extension must be between ' . self::BOOKING_EXTENSION_MIN_MINS . ' and ' . self::BOOKING_EXTENSION_MAX_MINS . ' minutes.');
                } else {
                    $bookingManagerExt = new BookingManager($pdo);
                    $extendBufferMins = $bookingManagerExt->getBufferMinutes(['buffer_duration_mins' => $r['buffer_duration_mins']]);
                    $new_end = date('Y-m-d H:i:s', strtotime($r['end_time']) + $extra_mins * 60);
                    $new_buf = date('Y-m-d H:i:s', strtotime($new_end) + $extendBufferMins * 60);
                    $extendAvailErr = ParkingBookingValidator::reservationFitsOwnerAvailability(
                        $r['start_time'],
                        $new_end,
                        $r['availability_start'] ?? null,
                        $r['availability_end'] ?? null
                    );
                    if ($extendAvailErr !== null) {
                        flash('err', $extendAvailErr);
                    } elseif ($bookingManagerExt->hasBufferedConflict((int)$r['spot_id'], $r['start_time'], $new_end, $extendBufferMins, $id)) {
                        flash('err', 'Cannot extend — overlaps another booking including its buffer.');
                    } else {
                        // Calculate extension cost, applying the same effective discount rate as the original reservation.
                        $origBase = (float)($r['base_cost'] ?? 0.0);
                        $origDiscount = (float)($r['discount_amount'] ?? 0.0);
                        $discountPercent = 0.0;
                        if ($origBase > 0 && $origDiscount > 0) {
                            $discountPercent = max(0.0, min(100.0, ($origDiscount / $origBase) * 100.0));
                        }

                        $pseudoSpot = [
                            'base_rate' => (float)$r['base_rate'],
                            'default_multiplier' => $r['default_multiplier'] ?? 1.0,
                            'vat_rate' => $r['vat_rate'] ?? 0.14,
                        ];
                        $extCost = self::calculateBookingCost(
                            $pseudoSpot,
                            $pdo,
                            '',
                            0,
                            $r['end_time'],
                            $new_end,
                            $discountPercent
                        );
                        $ext_base = (float)$extCost['base'];
                        $ext_discount = (float)$extCost['discount'];
                        $ext_tax = (float)$extCost['tax'];
                        $ext_total = (float)$extCost['total'];
                        
                        // Server-side: don't rely on client-controlled confirm flags.
                        // We already validated conflicts and availability above.
                        $new_base = (float)$r['base_cost'] + $ext_base;
                        $new_disc = (float)$r['discount_amount'] + $ext_discount;
                        $new_tax = (float)$r['tax_amount'] + $ext_tax;
                        $new_final = (float)$r['final_cost'] + $ext_total;

                        $pdo->prepare('UPDATE reservations SET end_time=?, buffer_end_time=?, base_cost=?, discount_amount=?, tax_amount=?, final_cost=? WHERE reservation_id=?')
                            ->execute([$new_end, $new_buf, $new_base, $new_disc, $new_tax, $new_final, $id]);
                        $pdo->prepare('UPDATE payments SET amount=?, tax_amount=?, discount_applied=?, final_amount=? WHERE payment_id=?')
                            ->execute([$new_base, $new_tax, $new_disc, $new_final, $r['payment_id']]);
                        // Extension is an additional charge; update commission tracking too.
                        $extCommissionAmt = round($ext_total * 0.15, 2);
                        $pdo->prepare('UPDATE payments SET commission_amt = commission_amt + ? WHERE payment_id=?')
                            ->execute([$extCommissionAmt, $r['payment_id']]);
                        $pdo->prepare('UPDATE platform_account SET total_commission = total_commission + ?')
                            ->execute([$extCommissionAmt]);

                        flash('ok', "Booking extended by {$extra_mins} minutes. Additional charge: {$ext_total} EGP");
                    }
                }
                redirect(route_url('/driver/bookingdetail?id=' . $id));
            }
            if ($act === 'cancel' && in_array($r['status'], ['confirmed', 'pending'])) {
                $now = time();
                $start = strtotime($r['start_time']);
                $refund = 0;
                if ($start - $now > 7200) {
                    $refund = 100;
                } elseif ($start - $now > 3600) {
                    $refund = 50;
                }
                $pdo->prepare("UPDATE reservations SET status='cancelled', cancelled_at=NOW() WHERE reservation_id=?")->execute([$id]);
                $pdo->prepare("UPDATE payments SET refund_percent=?, refund_amount=? WHERE payment_id=?")->execute([$refund, round($r['final_cost'] * $refund / 100, 2), $r['payment_id']]);
                (new PaymentModel($pdo))->refundFunds($id);
                // Reverse owner earnings for the refunded portion (safe-guarded to never go negative).
                $ownerShareFull = round(((float)($r['final_cost'] ?? 0.0)) * 0.85, 2);
                $refundedOwnerShare = round($ownerShareFull * ($refund / 100), 2);
                if ($refundedOwnerShare > 0 && !empty($r['owner_id'])) {
                    $pdo->prepare('UPDATE space_owners SET earnings_balance = GREATEST(0, earnings_balance - ?) WHERE owner_id=?')
                        ->execute([$refundedOwnerShare, $r['owner_id']]);
                }
                ReservationSubject::getInstance()->notifyObservers(ReservationEvent::bookingCancelled($pdo, $uid, $r, $refund));
                flash('ok', "Booking cancelled. Refund: {$refund}%.");
                redirect(route_url('/driver/bookings'));
            }
        }

        $badges = ['confirmed' => 'badge-blue', 'active' => 'badge-green', 'completed' => 'badge-gray', 'cancelled' => 'badge-red', 'no_show' => 'badge-amber', 'pending' => 'badge-amber'];
        $bc = $badges[$r['status']] ?? 'badge-gray';

        self::render('driver/bookingdetail', [
            'r' => $r,
            'id' => $id,
            'bc' => $bc,
            'reviewed_owner' => $reviewed_owner,
            'pageTitle' => 'Booking #' . $id,
        ]);
    }

    public static function vehicles(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            if ($act === 'add') {
                $plate = strtoupper(trim($_POST['plate'] ?? ''));
                $heightRaw = trim((string)($_POST['height'] ?? ''));
                $widthRaw = trim((string)($_POST['width'] ?? ''));
                $height = $heightRaw !== '' ? (float)$heightRaw : null;
                $width = $widthRaw !== '' ? (float)$widthRaw : null;
                $ev = isset($_POST['ev_capable']) ? 1 : 0;
                $errs = [];
                if ($plate === '') {
                    $errs[] = 'License plate is required.';
                }
                if ($height !== null && ($height < self::DIMENSION_CM_MIN || $height > self::DIMENSION_CM_MAX)) {
                    $errs[] = 'Vehicle height must be between ' . self::DIMENSION_CM_MIN . ' and ' . self::DIMENSION_CM_MAX . ' cm.';
                }
                if ($width !== null && ($width < self::DIMENSION_CM_MIN || $width > self::DIMENSION_CM_MAX)) {
                    $errs[] = 'Vehicle width must be between ' . self::DIMENSION_CM_MIN . ' and ' . self::DIMENSION_CM_MAX . ' cm.';
                }

                if ($errs !== []) {
                    flash('err', implode(' ', $errs));
                } elseif ($plate) {
                    $exists = $pdo->prepare('SELECT COUNT(*) FROM vehicle_profiles WHERE license_plate = ?');
                    $exists->execute([$plate]);
                    if ($exists->fetchColumn() > 0) {
                        flash('err', 'A vehicle with this license plate is already registered.');
                    } else {
                        $isDefaultStmt = $pdo->prepare('SELECT COUNT(*) FROM vehicle_profiles WHERE owner_id=?');
                        $isDefaultStmt->execute([$uid]);
                        $def = $isDefaultStmt->fetchColumn() == 0 ? 1 : 0;
                        $pdo->prepare('INSERT INTO vehicle_profiles (owner_id, license_plate, height_cm, width_cm, is_ev_capable, is_default) VALUES (?,?,?,?,?,?)')
                            ->execute([$uid, $plate, $height, $width, $ev, $def]);
                        flash('ok', 'Vehicle added.');
                    }
                }
            } elseif ($act === 'delete') {
                $vid = (int)($_POST['vehicle_id'] ?? 0);
                $pdo->prepare('DELETE FROM vehicle_profiles WHERE vehicle_id=? AND owner_id=?')->execute([$vid, $uid]);
                flash('ok', 'Vehicle removed.');
            } elseif ($act === 'set_default') {
                $vid = (int)($_POST['vehicle_id'] ?? 0);
                $pdo->prepare('UPDATE vehicle_profiles SET is_default=0 WHERE owner_id=?')->execute([$uid]);
                $pdo->prepare('UPDATE vehicle_profiles SET is_default=1 WHERE vehicle_id=? AND owner_id=?')->execute([$vid, $uid]);
                flash('ok', 'Default vehicle updated.');
            }
            redirect(route_url('/driver/vehicles'));
        }

        $vehiclesStmt = $pdo->prepare('SELECT * FROM vehicle_profiles WHERE owner_id=? ORDER BY is_default DESC');
        $vehiclesStmt->execute([$uid]);
        $vehicles = $vehiclesStmt->fetchAll();

        self::render('driver/vehicles', [
            'vehicles' => $vehicles,
            'pageTitle' => 'My Vehicles',
        ]);
    }

    public static function favorites(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];
        $bookingManager = new BookingManager($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $spot_id = (int)($_POST['spot_id'] ?? 0);
            if ($act === 'remove_fav') {
                $pdo->prepare('DELETE FROM favorite_spots WHERE driver_id=? AND spot_id=?')->execute([$uid, $spot_id]);
                flash('ok', 'Removed from favorites.');
            } elseif ($act === 'add_fav') {
                $custom_label = trim((string)($_POST['custom_label'] ?? ''));
                if ($custom_label === '') {
                    $custom_label = null;
                } else {
                    // Store Home/Work in consistent casing for the quick re-book feature.
                    if (strcasecmp($custom_label, 'home') === 0) {
                        $custom_label = 'Home';
                    } elseif (strcasecmp($custom_label, 'work') === 0) {
                        $custom_label = 'Work';
                    }
                }

                // Upsert by unique key (driver_id, spot_id).
                $pdo->prepare(
                    'INSERT INTO favorite_spots (driver_id, spot_id, custom_label)
                     VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE custom_label = VALUES(custom_label)'
                )->execute([$uid, $spot_id, $custom_label]);
                flash('ok', $custom_label ? ('Saved to favorites as ' . $custom_label . '.') : 'Saved to favorites.');
            } elseif ($act === 'join_waitlist') {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM waitlist WHERE driver_id=? AND spot_id=?');
                $chk->execute([$uid, $spot_id]);
                if (!$chk->fetchColumn()) {
                    $pdo->prepare('INSERT INTO waitlist (spot_id, driver_id) VALUES (?,?)')->execute([$spot_id, $uid]);
                    flash('ok', "You'll be notified when this spot is free.");
                } else {
                    flash('err', 'Already on waitlist for this spot.');
                }
            } elseif ($act === 'leave_waitlist') {
                $pdo->prepare('DELETE FROM waitlist WHERE driver_id=? AND spot_id=?')->execute([$uid, $spot_id]);
                flash('ok', 'Removed from waitlist.');
            }
            redirect(route_url('/driver/favorites'));
        }

        $favs = $pdo->prepare('SELECT fs.favorite_id, fs.spot_id, fs.custom_label, ps.address, ps.base_rate, ps.status, ps.latitude, ps.longitude, w.waitlist_id FROM favorite_spots fs JOIN parking_spots ps ON ps.spot_id = fs.spot_id LEFT JOIN waitlist w ON w.spot_id = fs.spot_id AND w.driver_id = ? WHERE fs.driver_id = ? ORDER BY fs.saved_at DESC');
        $favs->execute([$uid, $uid]);
        $favs = $favs->fetchAll();

        $home_adjacent = [];
        $work_adjacent = [];

        foreach ($favs as $f) {
            $label = trim((string)($f['custom_label'] ?? ''));
            if ($label !== '' && strcasecmp($label, 'home') === 0 && $home_adjacent === []) {
                $ref = [
                    'spot_id' => (int)$f['spot_id'],
                    'address' => (string)$f['address'],
                    'base_rate' => (float)$f['base_rate'],
                    'latitude' => $f['latitude'] ?? null,
                    'longitude' => $f['longitude'] ?? null,
                ];
                $home_adjacent = $bookingManager->getAlternativeSpots($ref, 5);
            } elseif ($label !== '' && strcasecmp($label, 'work') === 0 && $work_adjacent === []) {
                $ref = [
                    'spot_id' => (int)$f['spot_id'],
                    'address' => (string)$f['address'],
                    'base_rate' => (float)$f['base_rate'],
                    'latitude' => $f['latitude'] ?? null,
                    'longitude' => $f['longitude'] ?? null,
                ];
                $work_adjacent = $bookingManager->getAlternativeSpots($ref, 5);
            }
        }

        self::render('driver/favorites', [
            'favs' => $favs,
            'home_adjacent' => $home_adjacent,
            'work_adjacent' => $work_adjacent,
            'pageTitle' => 'Favorites & Waitlist',
        ]);
    }

    public static function notifications(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE recipient_id=?')->execute([$uid]);
            flash('ok', 'All marked as read.');
            redirect(route_url('/driver/notifications'));
        }

        $notifs = $pdo->prepare('SELECT * FROM notifications WHERE recipient_id=? ORDER BY created_at DESC LIMIT 50');
        $notifs->execute([$uid]);
        $notifs = $notifs->fetchAll();

        self::render('driver/notifications', [
            'notifs' => $notifs,
            'pageTitle' => 'Notifications',
        ]);
    }

    public static function fines(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $u = current_user();
        $uid = $u['id'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $fine_id = (int)($_POST['fine_id'] ?? 0);
            if ($act === 'appeal' && $fine_id) {
                $reason = trim($_POST['reason'] ?? '');
                $ev_url = '';
                if (!empty($_FILES['evidence']['tmp_name'])) {
                    $ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                        $fname = 'ev_' . $uid . '_' . $fine_id . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES['evidence']['tmp_name'], __DIR__ . '/../uploads/evidence/' . $fname);
                        $ev_url = base_url('/uploads/evidence/' . $fname);
                    }
                }
                $chk = $pdo->prepare('SELECT COUNT(*) FROM appeals WHERE fine_id=? AND driver_id=?');
                $chk->execute([$fine_id, $uid]);
                if ($chk->fetchColumn()) {
                    flash('err', 'You already submitted an appeal for this fine.');
                } else {
                    $pdo->prepare('INSERT INTO appeals (fine_id, driver_id, reason, evidence_url) VALUES (?,?,?,?)')->execute([$fine_id, $uid, $reason, $ev_url]);
                    $pdo->prepare('UPDATE fines SET status="appealed" WHERE fine_id=?')->execute([$fine_id]);
                    $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id) VALUES (?,?,?)')->execute(['APPEAL_SUBMITTED', $fine_id, $uid]);
                    // Notify all admins that a new appeal was submitted.
                    $adminIds = $pdo->query('SELECT id FROM users WHERE role="admin"')->fetchAll(PDO::FETCH_COLUMN);
                    $driverName = (string)($u['name'] ?? 'A driver');
                    foreach ($adminIds as $aid) {
                        $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')
                            ->execute([(int)$aid, 'in_app', "{$driverName} submitted an appeal for fine #{$fine_id}.", 'appeal', 'sent']);
                    }
                    flash('ok', 'Appeal submitted successfully.');
                }
                redirect(route_url('/driver/fines'));
            }
            if ($act === 'pay_fine' && $fine_id) {
                $fine = $pdo->prepare('SELECT * FROM fines WHERE fine_id=? AND driver_id=? AND status="pending"');
                $fine->execute([$fine_id, $uid]);
                $f = $fine->fetch();
                if (!$f) {
                    flash('err', 'Fine not found or already processed.');
                } else {
                    // Validate credit card details
                    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
                    $card_expiry_month = $_POST['card_expiry_month'] ?? '';
                    $card_expiry_year = $_POST['card_expiry_year'] ?? '';
                    if (strlen($card_expiry_year) === 4) {
                        $card_expiry_year = substr($card_expiry_year, -2);
                    }
                    $card_expiry = $card_expiry_month . '/' . $card_expiry_year;
                    $card_cvv = trim($_POST['card_cvv'] ?? '');
                    $card_name = trim($_POST['card_name'] ?? '');

                    $errors = [];
                    if (!$card_number || strlen($card_number) < 13 || strlen($card_number) > 19) {
                        $errors[] = 'Please enter a valid credit card number.';
                    }
                    if (!$card_expiry_month || !$card_expiry_year || !preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $card_expiry)) {
                        $errors[] = 'Please enter a valid expiry date.';
                    }
                    if (!preg_match('/^[0-9]{3,4}$/', $card_cvv)) {
                        $errors[] = 'Please enter a valid CVV.';
                    }
                    if (!$card_name) {
                        $errors[] = 'Please enter the cardholder name.';
                    }

                    if (!empty($errors)) {
                        flash('err', implode(' ', $errors));
                    } else {
                        // Simulate payment processing
                        $pdo->prepare('UPDATE fines SET status="paid", paid_at=NOW(), charge_id=? WHERE fine_id=?')->execute([$card_number . '|' . $card_expiry, $fine_id]);
                        $pdo->prepare('UPDATE drivers SET unpaid_fines = GREATEST(0, unpaid_fines - 1) WHERE driver_id=?')->execute([$uid]);
                        $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id) VALUES (?,?,?)')->execute(['FINE_PAID', $fine_id, $uid]);
                        flash('ok', 'Fine paid successfully.');
                    }
                }
                redirect(route_url('/driver/fines'));
            }
        }

        $fines = $pdo->prepare('SELECT f.*, ps.address, a.appeal_id, a.status AS appeal_status FROM fines f JOIN parking_spots ps ON f.spot_id = ps.spot_id LEFT JOIN appeals a ON a.fine_id = f.fine_id AND a.driver_id = f.driver_id WHERE f.driver_id = ? ORDER BY f.issued_at DESC');
        $fines->execute([$uid]);
        $fines = $fines->fetchAll();

        self::render('driver/fines', [
            'fines' => $fines,
            'pageTitle' => 'My Fines',
        ]);
    }

    public static function checkExtendConflict(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        header('Content-Type: application/json');

        $spot_id = (int)($_POST['spot_id'] ?? 0);
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $buffer_mins = (int)($_POST['buffer_mins'] ?? 0);
        $reservation_id = (int)($_POST['reservation_id'] ?? 0);

        if (!$spot_id || !$start_time || !$end_time) {
            echo json_encode(['hasConflict' => true, 'error' => 'Missing parameters']);
            exit;
        }

        $bm = new BookingManager($pdo);
        $hasConflict = $bm->hasBufferedConflict($spot_id, $start_time, $end_time, $buffer_mins, $reservation_id);

        echo json_encode(['hasConflict' => $hasConflict]);
        exit;
    }
}

