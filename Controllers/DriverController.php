<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/BookingManager.php';
require_once __DIR__ . '/../Models/ParkingBookingValidator.php';
require_once __DIR__ . '/../Models/PricingModel.php';
require_once __DIR__ . '/../Models/PaymentModel.php';
require_once __DIR__ . '/../Models/PenaltyModel.php';

class DriverController extends BaseController
{
    public static function dashboard(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();
        new PaymentModel($pdo);
        $u = current_user();
        $uid = $u['id'];

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
             WHERE cs.driver_id = ?
             ORDER BY cs.created_at DESC
             LIMIT 5'
        );
        $subsStmt->execute([$uid]);
        $subscriptions = $subsStmt->fetchAll();

        $driverInfo = $pdo->prepare('SELECT can_book FROM drivers WHERE driver_id=?');
        $driverInfo->execute([$uid]);
        $dinfo = $driverInfo->fetch();
        // Wallet and account status are not in the drivers table yet, provide defaults
        $dinfo['wallet_balance'] = 0.00;
        $dinfo['account_status'] = 'active';

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
        $q = trim($_GET['q'] ?? '');
        $ev_only = isset($_GET['ev_only']);
        $max_h = trim($_GET['max_h'] ?? '');
        $max_w = trim($_GET['max_w'] ?? '');

        $where = ["ps.status = 'available'", "(z.status = 'active' OR ps.zone_id IS NULL)"];
        $params = [];
        if ($q) {
            $where[] = 'ps.address LIKE ?';
            $params[] = "%$q%";
        }
        if ($ev_only) {
            $where[] = 'ps.has_ev_charger = 1';
        }
        if ($max_h !== '') {
            $where[] = '(ps.height_cm IS NULL OR ps.height_cm >= ?)';
            $params[] = (float)$max_h;
        }
        if ($max_w !== '') {
            $where[] = '(ps.width_cm IS NULL OR ps.width_cm >= ?)';
            $params[] = (float)$max_w;
        }

        $sql = 'SELECT ps.spot_id, ps.address, ps.base_rate, ps.has_ev_charger, ps.height_cm, ps.width_cm, ps.difficulty_label, pe.default_multiplier, COALESCE(AVG(dr.rating_value), 0) AS avg_rating, COUNT(DISTINCT dr.rating_id) AS rating_count, ps.latitude, ps.longitude FROM parking_spots ps LEFT JOIN pricing_engine pe ON pe.spot_id = ps.spot_id LEFT JOIN difficulty_ratings dr ON dr.spot_id = ps.spot_id LEFT JOIN zones z ON z.zone_id = ps.zone_id WHERE ' . implode(' AND ', $where) . ' GROUP BY ps.spot_id ORDER BY ps.base_rate ASC';
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

    public static function book(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        new PaymentModel($pdo);
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();
        $u = current_user();
        $uid = $u['id'];
        $spot_id = (int)($_GET['spot'] ?? 0);
        if (!$spot_id) {
            redirect(route_url('/driver/search'));
        }

        $spotStmt = $pdo->prepare('SELECT ps.*, z.vat_rate, z.status AS zone_status, pe.default_multiplier, bm.buffer_duration_mins FROM parking_spots ps LEFT JOIN zones z ON z.zone_id = ps.zone_id LEFT JOIN pricing_engine pe ON pe.spot_id = ps.spot_id LEFT JOIN buffer_manager bm ON bm.spot_id = ps.spot_id WHERE ps.spot_id = ?');
        $spotStmt->execute([$spot_id]);
        $spot = $spotStmt->fetch();
        if (!$spot) {
            flash('err', 'Spot not available.');
            redirect(route_url('/driver/search'));
        }
        $recommendations = [];
        $availabilityError = '';
        if ($spot['status'] !== 'available' || $spot['zone_status'] === 'locked') {
            $availabilityError = $spot['zone_status'] === 'locked'
                ? 'This zone is currently locked by a municipal event.'
                : 'This spot is currently unavailable.';
            $recommendations = $bookingManager->getAlternativeSpots($spot, 3);
        }

        $dinfoStmt = $pdo->prepare('SELECT can_book FROM drivers WHERE driver_id=?');
        $dinfoStmt->execute([$uid]);
        $dinfo = $dinfoStmt->fetch();
        if ($dinfo && !$dinfo['can_book']) {
            flash('err', 'Your account is suspended. Pay outstanding fines first.');
            redirect(route_url('/driver/dashboard'));
        }

        $vehiclesStmt = $pdo->prepare('SELECT * FROM vehicle_profiles WHERE owner_id=?');
        $vehiclesStmt->execute([$uid]);
        $vehicles = $vehiclesStmt->fetchAll();

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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = self::processBookingForm($_POST, $spot, $vehicles, $uid, $loyalty_discount, $pdo, $bookingManager);
            $error = $data['error'];
            $preview = $data['preview'];
            $recommendations = $data['recommendations'] ?? $recommendations;
        }

        self::render('driver/book', [
            'spot' => $spot,
            'vehicles' => $vehicles,
            'loyalty_discount' => $loyalty_discount,
            'err' => $error ?: $availabilityError,
            'preview' => $preview,
            'recommendations' => $recommendations,
            'pageTitle' => 'Book Spot',
        ]);
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

        if ($spot['status'] !== 'available' || $spot['zone_status'] === 'locked') {
            return [
                'error' => 'The selected spot is no longer available.',
                'preview' => null,
                'recommendations' => $bookingManager->getAlternativeSpots($spot, 3),
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
            return self::processSubscriptionBooking($post, $spot, $vehicle_id, $uid, $loyalty_discount, $pdo, $bookingManager, $action, $promo);
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
                'error' => 'This booking violates the required buffer window for this spot (no overlap with existing bookings including buffer after their end times).',
                'preview' => null,
                'recommendations' => $bookingManager->getAlternativeSpots($spot, 3),
            ];
        }

        $cost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $start, $end, 0);
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
                    'recommendations' => $bookingManager->getAlternativeSpots($spot, 3),
                ];
            }

            $buffer_end = date('Y-m-d H:i:s', strtotime($end) + $bufferMins * 60);
            $qr_token = bin2hex(random_bytes(16));
            $transaction_id = 'CC' . time() . rand(1000, 9999);
            $p = $pdo->prepare('INSERT INTO payments (driver_id, amount, tax_amount, commission_amt, escrow_status, payment_status, penalty_buffer, final_amount, discount_applied, payment_method, token_ref, transaction_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $p->execute([
                $uid, $cost['base'], $cost['tax'], round($cost['total'] * 0.15, 2), 'held', 'pending', round($cost['escrow'] - $cost['total'], 2), $cost['total'], $cost['discount'], 'credit_card', 'CARD-****' . substr($card_number, -4), $transaction_id
            ]);
            $pay_id = $pdo->lastInsertId();

            $r = $pdo->prepare('INSERT INTO reservations (driver_id, spot_id, vehicle_id, payment_id, start_time, end_time, buffer_end_time, status, qr_code_token, base_cost, tax_amount, discount_amount, final_cost, escrow_amount, promo_code, buffer_applied, grace_period_mins) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $r->execute([$uid, $spot['spot_id'], $vehicle_id ?: null, $pay_id, $start, $end, $buffer_end, 'confirmed', $qr_token, $cost['base'], $cost['tax'], $cost['discount'], $cost['total'], $cost['escrow'], $promo ?: null, 1, 5]);
            $res_id = $pdo->lastInsertId();
            (new PaymentModel($pdo))->lockFunds((int)$res_id, (float)$cost['total']);

            if ($promo) {
                $pdo->prepare('UPDATE promo_codes SET usage_count = usage_count + 1 WHERE code=?')->execute([$promo]);
            }
            $pdo->prepare('UPDATE loyalty_accounts SET booking_last_30_days = booking_last_30_days + 1, total_points = total_points + ? WHERE driver_id=?')->execute([$cost['total'] * 0.1, $uid]);
            $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$uid, 'in_app', "Booking confirmed for {$spot['address']} on " . date('d M, H:i', strtotime($start)), 'booking', 'sent']);
            $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$spot['owner_id'], 'in_app', "New booking received for your spot at {$spot['address']}.", 'booking', 'sent']);
            $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)')->execute(['RESERVATION_CREATED', $res_id, $uid, $spot['spot_id'], 'confirmed']);
            $pdo->commit();
            flash('ok', 'Booking confirmed! Your QR token: ' . $qr_token);
            redirect(route_url('/driver/bookingdetail?id=' . $res_id));
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['error' => 'Booking failed. Please try again.', 'preview' => null];
        }
    }

    private static function calculateBookingCost(array $spot, PDO $pdo, string $promo_code, int $loyalty_discount, string $start, string $end, float $extraDiscountPercent = 0): array
    {
        $hours = max(0.5, (strtotime($end) - strtotime($start)) / 3600);
        $mult = $spot['default_multiplier'] ?? 1.0;
        $baseBeforePeak = round($spot['base_rate'] * $mult * $hours, 2);
        $peakData = (new PricingModel())->calculatePeakPrice((float)$baseBeforePeak, $start);
        $base = $peakData['final_price'];
        $vat_rate = $spot['vat_rate'] ?? 0.14;
        $discount = 0;
        if ($promo_code) {
            $pc = $pdo->prepare('SELECT * FROM promo_codes WHERE code=? AND expiry_date > NOW() AND (usage_limit=0 OR usage_count < usage_limit)');
            $pc->execute([$promo_code]);
            $pc = $pc->fetch();
            if ($pc) {
                $discount = $pc['discount_type'] === 'percentage' ? round($base * $pc['discount_value'] / 100, 2) : min((float)$pc['discount_value'], $base);
            }
        }
        $loyalty_disc = round($base * $loyalty_discount / 100, 2);
        $discount = max($discount, $loyalty_disc);
        if ($extraDiscountPercent > 0) {
            $discount = max($discount, round($base * $extraDiscountPercent / 100, 2));
        }
        $taxable = max(0, $base - $discount);
        $tax = round($taxable * $vat_rate, 2);
        $total = $taxable + $tax;
        $escrow = round($total * 1.15, 2);
        return array_merge(compact('base', 'discount', 'tax', 'total', 'escrow', 'hours'), [
            'base_before_peak' => $baseBeforePeak,
            'peak_adjustment' => $peakData['adjustment_amount'],
            'peak_multiplier' => $peakData['multiplier'],
            'peak_applied' => $peakData['is_peak'],
            'peak_reason' => $peakData['reason'],
        ]);
    }

    private static function processSubscriptionBooking(
        array $post,
        array $spot,
        int $vehicle_id,
        int $uid,
        int $loyalty_discount,
        PDO $pdo,
        BookingManager $bookingManager,
        string $action,
        string $promo
    ): array {
        $sub_discount = 15.0; // Assumption: commuter plan gets fixed 15% discount.

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
                    'error' => 'One or more recurring slots violate the buffer-time rule.',
                    'preview' => null,
                    'recommendations' => $bookingManager->getAlternativeSpots($spot, 3),
                ];
            }
        }

        $firstCost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $slots[0]['start'], $slots[0]['end'], $sub_discount);
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
                        'recommendations' => $bookingManager->getAlternativeSpots($spot, 3),
                    ];
                }

                $cost = self::calculateBookingCost($spot, $pdo, $promo, $loyalty_discount, $slot['start'], $slot['end'], $sub_discount);
                $transaction_id = 'SUB' . time() . rand(1000, 9999);
                $p = $pdo->prepare('INSERT INTO payments (driver_id, amount, tax_amount, commission_amt, escrow_status, payment_status, penalty_buffer, final_amount, discount_applied, payment_method, token_ref, transaction_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $p->execute([
                    $uid, $cost['base'], $cost['tax'], round($cost['total'] * 0.15, 2), 'held', 'pending', round($cost['escrow'] - $cost['total'], 2), $cost['total'], $cost['discount'], 'subscription', 'SUBSCRIPTION', $transaction_id
                ]);
                $pay_id = $pdo->lastInsertId();
                $buffer_end = date('Y-m-d H:i:s', strtotime($slot['end']) + $subBuffer * 60);
                $qr_token = bin2hex(random_bytes(16));
                $r = $pdo->prepare('INSERT INTO reservations (driver_id, spot_id, vehicle_id, payment_id, subscription_id, start_time, end_time, buffer_end_time, status, qr_code_token, base_cost, tax_amount, discount_amount, final_cost, escrow_amount, promo_code, buffer_applied, grace_period_mins) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $r->execute([$uid, $spot['spot_id'], $vehicle_id, $pay_id, $subscription_id, $slot['start'], $slot['end'], $buffer_end, 'confirmed', $qr_token, $cost['base'], $cost['tax'], $cost['discount'], $cost['total'], $cost['escrow'], $promo ?: null, 1, 5]);
                $res_id = (int)$pdo->lastInsertId();
                (new PaymentModel($pdo))->lockFunds($res_id, (float)$cost['total']);
            }
            $pdo->commit();
            flash('ok', 'Subscription created. Recurring reservations were generated successfully.');
            redirect(route_url('/driver/bookings?status=confirmed'));
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['error' => 'Subscription booking failed. Please try again.', 'preview' => null];
        }
    }

    public static function bookings(): void
    {
        require_role('driver');
        $pdo = Database::getConnection();
        $bookingManager = new BookingManager($pdo);
        $bookingManager->ensureSubscriptionSchema();
        $u = current_user();
        $uid = $u['id'];
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
             WHERE cs.driver_id = ?
             ORDER BY cs.created_at DESC'
        );
        $subs->execute([$uid]);
        $subscriptions = $subs->fetchAll();
        self::render('driver/bookings', [
            'rows' => $rows,
            'subscriptions' => $subscriptions,
            'filter' => $filter,
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            if ($act === 'checkin' && $r['status'] === 'confirmed') {
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE reservations SET status='active', check_in_time=?, arrival_time=? WHERE reservation_id=?")->execute([$now, $now, $id]);
                $pdo->prepare("UPDATE parking_spots SET status='occupied' WHERE spot_id=?")->execute([$r['spot_id']]);
                $pdo->prepare('INSERT INTO parking_sessions (reservation_id, driver_id, spot_id, start_time, status) VALUES (?,?,?,?,?)')->execute([$id, $uid, $r['spot_id'], $now, 'active']);
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)')->execute(['QR_CHECKIN', $id, $uid, $r['spot_id'], 'active']);
                flash('ok', 'Checked in successfully.');
                redirect(route_url('/driver/bookingdetail?id=' . $id));
            }
            if ($act === 'checkout' && $r['status'] === 'active') {
                $now = date('Y-m-d H:i:s');
                $penaltyBreakdown = (new PenaltyModel())->calculateOverstayPenalty($r['end_time'], $now);
                $overstay = (int)$penaltyBreakdown['overstay_minutes'];
                $penalty = (float)$penaltyBreakdown['penalty_amount'];
                $updatedTotal = (float)$r['final_cost'] + $penalty;
                $pdo->prepare("UPDATE reservations SET status='completed', check_out_time=?, overstay_minutes=?, penalty_amount=?, final_cost=? WHERE reservation_id=?")->execute([$now, $overstay, $penalty, $updatedTotal, $id]);
                $pdo->prepare("UPDATE parking_spots SET status='available' WHERE spot_id=?")->execute([$r['spot_id']]);
                $pdo->prepare("UPDATE parking_sessions SET end_time=?, status=?, duration_mins=? WHERE reservation_id=?")->execute([$now, $overstay > 0 ? 'overstay' : 'completed', (int)((strtotime($now) - strtotime($r['check_in_time'])) / 60), $id]);
                $pdo->prepare("UPDATE payments SET final_amount = final_amount + ? WHERE payment_id=?")->execute([$penalty, $r['payment_id']]);
                (new PaymentModel($pdo))->releaseFunds($id);
                $owner_share = round($updatedTotal * 0.85, 2);
                $pdo->prepare("UPDATE space_owners SET earnings_balance = earnings_balance + ? WHERE owner_id=?")->execute([$owner_share, $r['owner_id']]);
                $pdo->prepare('INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)')->execute(['QR_CHECKOUT', $id, $uid, $r['spot_id'], 'completed']);
                flash('ok', 'Checked out.' . ($penalty > 0 ? " Overstay penalty: {$penalty} EGP." : ''));
                redirect(route_url('/driver/bookingdetail?id=' . $id));
            }
            if ($act === 'extend' && $r['status'] === 'active') {
                $extra_mins = (int)($_POST['extra_mins'] ?? 30);
                if ($extra_mins <= 0) {
                    flash('err', 'Extension must be at least 1 minute.');
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
                        $pdo->prepare('UPDATE reservations SET end_time=?, buffer_end_time=? WHERE reservation_id=?')->execute([$new_end, $new_buf, $id]);
                        flash('ok', "Booking extended by {$extra_mins} minutes.");
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
                $pdo->prepare("UPDATE parking_spots SET status='available' WHERE spot_id=?")->execute([$r['spot_id']]);
                $wait = $pdo->prepare('SELECT driver_id FROM waitlist WHERE spot_id=? ORDER BY joined_at ASC LIMIT 1');
                $wait->execute([$r['spot_id']]);
                $wdriver = $wait->fetch();
                if ($wdriver) {
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')->execute([$wdriver['driver_id'], 'in_app', "A spot you're watching at {$r['address']} just became available!", 'waitlist', 'sent']);
                }
                // Nearby alternative recommendations when a reservation becomes unavailable/cancelled.
                $spotRef = ['spot_id' => $r['spot_id'], 'latitude' => $r['latitude'], 'longitude' => $r['longitude']];
                $bookingManager = new BookingManager($pdo);
                $alternatives = $bookingManager->getAlternativeSpots($spotRef, 3);
                if (!empty($alternatives)) {
                    $lines = array_map(
                        static fn(array $alt): string => $alt['address'] . ' (' . number_format((float)$alt['distance_km'], 2) . ' km)',
                        $alternatives
                    );
                    $pdo->prepare('INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)')
                        ->execute([$uid, 'in_app', 'Suggested nearby alternatives: ' . implode(' | ', $lines), 'booking', 'sent']);
                }
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
                $height = (float)($_POST['height'] ?? 0);
                $width = (float)($_POST['width'] ?? 0);
                $ev = isset($_POST['ev_capable']) ? 1 : 0;
                if ($plate) {
                    $exists = $pdo->prepare('SELECT COUNT(*) FROM vehicle_profiles WHERE license_plate = ?');
                    $exists->execute([$plate]);
                    if ($exists->fetchColumn() > 0) {
                        flash('err', 'A vehicle with this license plate is already registered.');
                    } else {
                        $isDefaultStmt = $pdo->prepare('SELECT COUNT(*) FROM vehicle_profiles WHERE owner_id=?');
                        $isDefaultStmt->execute([$uid]);
                        $def = $isDefaultStmt->fetchColumn() == 0 ? 1 : 0;
                        $pdo->prepare('INSERT INTO vehicle_profiles (owner_id, license_plate, height_cm, width_cm, is_ev_capable, is_default) VALUES (?,?,?,?,?,?)')->execute([$uid, $plate, $height ?: null, $width ?: null, $ev, $def]);
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';
            $spot_id = (int)($_POST['spot_id'] ?? 0);
            if ($act === 'remove_fav') {
                $pdo->prepare('DELETE FROM favorite_spots WHERE driver_id=? AND spot_id=?')->execute([$uid, $spot_id]);
                flash('ok', 'Removed from favorites.');
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

        $favs = $pdo->prepare('SELECT fs.favorite_id, fs.spot_id, fs.custom_label, ps.address, ps.base_rate, ps.status, w.waitlist_id FROM favorite_spots fs JOIN parking_spots ps ON ps.spot_id = fs.spot_id LEFT JOIN waitlist w ON w.spot_id = fs.spot_id AND w.driver_id = ? WHERE fs.driver_id = ? ORDER BY fs.saved_at DESC');
        $favs->execute([$uid, $uid]);
        $favs = $favs->fetchAll();

        self::render('driver/favorites', [
            'favs' => $favs,
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
                    flash('ok', 'Appeal submitted successfully.');
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
}
