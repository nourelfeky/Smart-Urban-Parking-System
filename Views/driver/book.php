<?php
$booking_mode = $_POST['booking_mode'] ?? $_GET['booking_mode'] ?? 'one_time';
$booking_mode = $booking_mode === 'subscription' ? 'subscription' : 'one_time';
$is_subscription_ui = $booking_mode === 'subscription';
$alt_ctx = $alt_ctx ?? ['start' => '', 'end' => '', 'booking_mode' => 'one_time'];
$current_fav_label = $current_fav_label ?? null;
$on_spot_waitlist = $on_spot_waitlist ?? false;
$show_spot_waitlist = $show_spot_waitlist ?? false;
$pref_start = $_POST['start_time'] ?? ($alt_ctx['start'] ?? ($_GET['start'] ?? ''));
$pref_end = $_POST['end_time'] ?? ($alt_ctx['end'] ?? ($_GET['end'] ?? ''));
$wl_q = $_GET;
$wl_q['spot'] = (string)(int)$spot['spot_id'];
$waitlist_action_url = route_url('/driver/book?' . http_build_query($wl_q));
?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Book Parking Spot</h1>

<div class="card mb-3">
    <div class="card-title"><?= htmlspecialchars($spot['address']) ?></div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
        <div><span class="text-muted">Base rate:</span> <strong><?= number_format($spot['base_rate'], 2) ?> EGP/hr</strong></div>
        <?php if ($spot['has_ev_charger']): ?><div><span class="badge badge-green">EV Charger ⚡</span></div><?php endif; ?>
        <?php if ($spot['height_cm']): ?><div><span class="text-muted">Height:</span> <?= $spot['height_cm'] ?>cm</div><?php endif; ?>
        <?php if ($spot['width_cm']): ?><div><span class="text-muted">Width:</span> <?= $spot['width_cm'] ?>cm</div><?php endif; ?>
    </div>
    <?php if ($loyalty_discount > 0): ?>
        <div class="alert alert-info mt-3">Your loyalty tier gives you <?= $loyalty_discount ?>% discount!</div>
    <?php endif; ?>
    <div class="text-muted mt-2">A mandatory 10-minute buffer is enforced between consecutive bookings on this spot.</div>
</div>

<div class="card mb-3">
    <div class="card-title">Save to Favorites</div>
    <p class="text-muted" style="margin-top:-6px">
        Save this spot so you can quickly re-book nearby alternatives from your Favorites page.
    </p>

    <?php if (!empty($current_fav_label)): ?>
        <div style="margin-bottom:10px">
            <span class="badge badge-blue">Saved as <?= htmlspecialchars((string)$current_fav_label) ?></span>
        </div>
    <?php else: ?>
        <div style="margin-bottom:10px">
            <span class="badge badge-gray">Not saved yet</span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(route_url('/driver/favorites'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add_fav">
        <input type="hidden" name="spot_id" value="<?= (int)$spot['spot_id'] ?>">

        <div class="form-row" style="align-items:flex-end">
            <div class="form-group" style="min-width:240px">
                <label>Label</label>
                <select name="custom_label" class="form-control">
                    <option value="" <?= empty($current_fav_label) ? 'selected' : '' ?>>Favorite (no label)</option>
                    <option value="Home" <?= ($current_fav_label === 'Home') ? 'selected' : '' ?>>Home</option>
                    <option value="Work" <?= ($current_fav_label === 'Work') ? 'selected' : '' ?>>Work</option>
                </select>
                <small class="text-muted" style="display:block;margin-top:6px">
                    Choosing <strong>Home</strong> or <strong>Work</strong> enables quick re-booking nearby spots.
                </small>
            </div>
            <div style="margin-bottom:2px">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>

    <?php if (!empty($current_fav_label)): ?>
        <form method="post" action="<?= htmlspecialchars(route_url('/driver/favorites'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:10px" onsubmit="return confirm('Remove this spot from favorites?');">
            <input type="hidden" name="action" value="remove_fav">
            <input type="hidden" name="spot_id" value="<?= (int)$spot['spot_id'] ?>">
            <button type="submit" class="btn btn-outline">Remove from Favorites</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($err): ?>
<div class="alert alert-error"><?= $err ?></div>
<?php endif; ?>

<?php if ($show_spot_waitlist): ?>
<div class="card mb-3">
    <div class="card-title">Waitlist for this spot</div>
    <?php if ($on_spot_waitlist): ?>
        <p class="text-muted" style="margin-top:-6px">You’re on the waitlist for this address. Leave anytime, or stay to get in-app alerts when it may free up.</p>
    <?php else: ?>
        <p class="text-muted" style="margin-top:-6px">Your selected time isn’t available on this spot right now. Join the waitlist and we’ll notify you in-app when it may open (e.g. after checkout or cancellation).</p>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($waitlist_action_url) ?>" class="flex gap-2 items-center" style="flex-wrap:wrap">
        <input type="hidden" name="waitlist_spot_id" value="<?= (int)$spot['spot_id'] ?>">
        <?php if (!$on_spot_waitlist): ?>
            <input type="hidden" name="waitlist_spot_action" value="join">
            <button type="submit" class="btn btn-outline btn-sm">Join waitlist for this address</button>
        <?php else: ?>
            <input type="hidden" name="waitlist_spot_action" value="leave">
            <button type="submit" class="btn btn-warning btn-sm">Leave waitlist</button>
            <span class="badge badge-blue">On waitlist</span>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($recommendations)): ?>
<div class="card mb-3">
    <div class="card-title">Nearby alternatives</div>
    <?php
        $anyFit = false;
        foreach ($recommendations as $x) {
            if (!empty($x['fits_requested_window'])) {
                $anyFit = true;
                break;
            }
        }
    ?>
    <p class="text-muted" style="margin-top:-6px">
        <?php if ($anyFit): ?>
            Spots marked <span class="badge badge-green">fits your time</span> are free for the same schedule (and your vehicle size) when applicable. Others are nearby — open the page and pick a time.
        <?php else: ?>
            Distance uses map coordinates when saved. These are the nearest listed spots; confirm times on the booking page.
        <?php endif; ?>
    </p>
    <ul style="list-style:none;padding:0;margin:0">
        <?php foreach ($recommendations as $alt): ?>
            <?php
                $q = ['spot' => (int)$alt['spot_id']];
                if ($alt_ctx['booking_mode'] === 'subscription') {
                    $q['booking_mode'] = 'subscription';
                } elseif (!empty($alt_ctx['start']) && !empty($alt_ctx['end'])) {
                    $q['start'] = $alt_ctx['start'];
                    $q['end'] = $alt_ctx['end'];
                    $q['booking_mode'] = 'one_time';
                }
                $bookAltUrl = route_url('/driver/book?' . http_build_query($q));
            ?>
            <li style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--gray-100)">
                <div class="flex gap-2 items-center" style="flex-wrap:wrap">
                    <strong><?= htmlspecialchars($alt['address']) ?></strong>
                    <?php if (!empty($alt['fits_requested_window'])): ?>
                        <span class="badge badge-green">fits your time</span>
                    <?php else: ?>
                        <span class="badge badge-amber">nearby</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted mt-1" style="font-size:13px">
                    <?= number_format((float)$alt['distance_km'], 2) ?> km · <?= number_format((float)$alt['base_rate'], 2) ?> EGP/hr
                </div>
                <a class="btn btn-sm btn-outline mt-2" href="<?= htmlspecialchars($bookAltUrl) ?>">Book this spot</a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-title">Booking Details</div>
    <form method="post" id="booking-form">
        <div class="form-group">
            <label>Vehicle</label>
            <select name="vehicle_id" class="form-control" required>
                <option value="">— Select vehicle —</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['vehicle_id'] ?>" <?= ($v['vehicle_id'] == ($_POST['vehicle_id'] ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['license_plate']) ?> <?= $v['is_default'] ? '(default)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Booking type</label>
            <input type="hidden" name="booking_mode" id="booking_mode" value="<?= htmlspecialchars($booking_mode, ENT_QUOTES, 'UTF-8') ?>">
            <?php
                $spotIdQ = isset($_GET['spot']) ? (int)$_GET['spot'] : 0;
                $spotQuery = $spotIdQ ? ('?spot=' . $spotIdQ) : '';
                $bookingModeSuffix = ($spotQuery ? '&' : '?') . 'booking_mode=';
            ?>
            <div class="flex gap-2" style="flex-wrap:wrap" role="group" aria-label="Booking type">
                <a class="btn btn-sm <?= !$is_subscription_ui ? 'btn-primary' : 'btn-outline' ?>"
                   href="<?= htmlspecialchars(route_url('/driver/book' . $spotQuery . $bookingModeSuffix . 'one_time')) ?>">One-time booking</a>
                <a class="btn btn-sm <?= $is_subscription_ui ? 'btn-primary' : 'btn-outline' ?>"
                   href="<?= htmlspecialchars(route_url('/driver/book' . $spotQuery . $bookingModeSuffix . 'subscription')) ?>">Commuter subscription</a>
            </div>
        </div>

        <noscript>
            <div class="alert alert-info" style="margin-top:8px">
                JavaScript is disabled. Subscription fields appear when you open this page with <code>?booking_mode=subscription</code> (use the commuter subscription button).
            </div>
        </noscript>

        <div id="one-time-panel" class="booking-type-panel" style="<?= $is_subscription_ui ? 'display:none' : '' ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Start time</label>
                    <input type="datetime-local" name="start_time" class="form-control one-time-input" value="<?= htmlspecialchars($pref_start) ?>" <?= $is_subscription_ui ? '' : 'required' ?>>
                </div>
                <div class="form-group">
                    <label>End time</label>
                    <input type="datetime-local" name="end_time" class="form-control one-time-input" value="<?= htmlspecialchars($pref_end) ?>" <?= $is_subscription_ui ? '' : 'required' ?>>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Promo code</label>
                <input type="text" name="promo_code" class="form-control" value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>">
                <small class="text-muted" style="display:block;margin-top:6px">
                    Promo must stay valid through the end of your booking window (won’t expire mid-reservation).
                </small>
            </div>
        </div>

        <div id="subscription-panel" class="booking-type-panel card mb-0 mt-3"
             style="padding:16px;background:rgba(255,255,255,0.02);<?= !$is_subscription_ui ? 'display:none' : '' ?>">
            <div class="card-title" style="margin-bottom:8px">Commuter subscription</div>
            <p class="text-muted" style="margin-bottom:12px">Pick a date range (end after start, at least 7 days). <strong>Duration (weeks)</strong> is computed as <code>ceil((End − Start) days / 7)</code> automatically.</p>
            <div class="form-row">
                <div class="form-group">
                    <label>Subscription start date</label>
                    <input type="date" name="sub_start_date" id="sub_start_date" class="form-control sub-input" value="<?= htmlspecialchars($_POST['sub_start_date'] ?? '') ?>" <?= $is_subscription_ui ? 'required' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Subscription end date</label>
                    <input type="date" name="sub_end_date" id="sub_end_date" class="form-control sub-input" value="<?= htmlspecialchars($_POST['sub_end_date'] ?? '') ?>" <?= $is_subscription_ui ? 'required' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="duration_weeks_readonly">Duration (weeks)</label>
                    <input type="text" id="duration_weeks_readonly" class="form-control" readonly tabindex="-1"
                        value="" placeholder="—"
                        title="Calculated from date range"
                        style="background:rgba(0,0,0,0.04);cursor:not-allowed;font-weight:600">
                    <small class="text-muted" id="duration_weeks_hint" style="display:block;margin-top:4px"></small>
                </div>
            </div>
            <div class="form-group">
                <label>Days of week</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php
                    $dowMap = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                    $selectedDays = $_POST['sub_days'] ?? [];
                    foreach ($dowMap as $k => $label):
                    ?>
                    <label style="display:flex;align-items:center;gap:4px">
                        <input type="checkbox" class="sub-day sub-input-inline" name="sub_days[]" value="<?= $k ?>" <?= in_array((string)$k, array_map('strval', $selectedDays), true) ? 'checked' : '' ?>>
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Recurring start time</label>
                    <input type="time" name="sub_start_time" class="form-control sub-input" value="<?= htmlspecialchars($_POST['sub_start_time'] ?? '') ?>" <?= $is_subscription_ui ? 'required' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Recurring end time</label>
                    <input type="time" name="sub_end_time" class="form-control sub-input" value="<?= htmlspecialchars($_POST['sub_end_time'] ?? '') ?>" <?= $is_subscription_ui ? 'required' : '' ?>>
                </div>
            </div>
        </div>
        <button type="submit" name="action" value="preview" class="btn btn-primary mt-3">Preview cost</button>
    </form>
</div>

<?php if ($preview): ?>
<div class="card mb-3">
    <div class="card-title">Price preview</div>
    <table style="max-width:500px">
        <tr><td class="text-muted">Base price</td><td style="padding-left:24px"><?= number_format($preview['base_before_peak'] ?? $preview['base'], 2) ?> EGP</td></tr>
        <?php if (!empty($preview['peak_applied'])): ?>
        <tr>
            <td class="text-muted">Peak adjustment</td>
            <td style="padding-left:24px;color:var(--amber)">+ <?= number_format((float)$preview['peak_adjustment'], 2) ?> EGP <span class="badge badge-amber">Peak <?= !empty($preview['peak_reason']) && $preview['peak_reason'] === 'special_event' ? '(Event)' : '(Hour)' ?></span></td>
        </tr>
        <?php endif; ?>
        <tr><td class="text-muted">Price after peak rule</td><td style="padding-left:24px"><?= number_format($preview['base'], 2) ?> EGP</td></tr>
        <tr><td class="text-muted">Discount</td><td style="padding-left:24px;color:var(--green)">- <?= number_format($preview['discount'], 2) ?> EGP</td></tr>
        <tr><td class="text-muted">VAT</td><td style="padding-left:24px"><?= number_format($preview['tax'], 2) ?> EGP</td></tr>
        <tr style="font-weight:700"><td>Total</td><td style="padding-left:24px"><?= number_format($preview['total'], 2) ?> EGP</td></tr>
        <tr><td class="text-muted">Escrow</td><td style="padding-left:24px"><?= number_format($preview['escrow'], 2) ?> EGP</td></tr>
        <tr><td class="text-muted">Payment status</td><td style="padding-left:24px"><span class="badge badge-amber">Payment Held in Escrow</span></td></tr>
        <?php if (($preview['booking_mode'] ?? 'one_time') === 'subscription'): ?>
        <tr><td class="text-muted">Subscription period</td><td style="padding-left:24px"><?= htmlspecialchars($preview['sub_period_start_date'] ?? '') ?> → <?= htmlspecialchars($preview['sub_period_end_date'] ?? '') ?></td></tr>
        <tr><td class="text-muted">Subscription discount</td><td style="padding-left:24px"><?= number_format((float)$preview['subscription_discount_percent'], 2) ?>%</td></tr>
        <tr><td class="text-muted">Generated reservations</td><td style="padding-left:24px"><?= (int)$preview['slots_count'] ?> slots · <?= (int)$preview['weeks'] ?> week(s)</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="card mb-3">
    <div class="card-title">Payment</div>
    <form method="post">
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($_POST['vehicle_id'] ?? '') ?>">
        <input type="hidden" name="promo_code" value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>">
        <?php $confirmSub = ($_POST['booking_mode'] ?? 'one_time') === 'subscription'; ?>
        <input type="hidden" name="booking_mode" value="<?= htmlspecialchars($_POST['booking_mode'] ?? 'one_time') ?>">
        <?php if ($confirmSub): ?>
        <?php foreach (($_POST['sub_days'] ?? []) as $day): ?>
        <input type="hidden" name="sub_days[]" value="<?= htmlspecialchars((string)$day) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="sub_start_time" value="<?= htmlspecialchars($_POST['sub_start_time'] ?? '') ?>">
        <input type="hidden" name="sub_end_time" value="<?= htmlspecialchars($_POST['sub_end_time'] ?? '') ?>">
        <input type="hidden" name="sub_start_date" value="<?= htmlspecialchars($_POST['sub_start_date'] ?? '') ?>">
        <input type="hidden" name="sub_end_date" value="<?= htmlspecialchars($_POST['sub_end_date'] ?? '') ?>">
        <?php else: ?>
        <input type="hidden" name="start_time" value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>">
        <input type="hidden" name="end_time" value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>">
        <?php endif; ?>
        <div class="form-group">
            <label>Cardholder Name</label>
            <input type="text" name="card_name" class="form-control" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Card Number</label>
                <input type="text" name="card_number" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Expiry</label>
                <div style="display:flex;gap:8px">
                    <input type="text" name="card_expiry_month" class="form-control" placeholder="MM" style="width:80px" required>
                    <input type="text" name="card_expiry_year" class="form-control" placeholder="YY" style="width:80px" required>
                </div>
            </div>
            <div class="form-group">
                <label>CVV</label>
                <input type="text" name="card_cvv" class="form-control" style="width:120px" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success">Confirm booking</button>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    var modeSel = document.getElementById('booking_mode');
    var otPanel = document.getElementById('one-time-panel');
    var subPanel = document.getElementById('subscription-panel');
    if (!modeSel || !otPanel || !subPanel) return;

    function daysBetweenInclusiveStartEnd(ds, de) {
        if (!ds || !de) return null;
        var a = new Date(ds + 'T00:00:00');
        var b = new Date(de + 'T00:00:00');
        if (isNaN(a.getTime()) || isNaN(b.getTime()) || b < a) return null;
        var deltaMs = b.getTime() - a.getTime();
        return Math.floor(deltaMs / 86400000);
    }

    /** Mirrors SubscriptionPeriodService + ParkingBookingValidator (calendar day delta, then ceil(days/7)). */
    function computeDurationWeeksFromInputs() {
        var sdEl = document.getElementById('sub_start_date');
        var edEl = document.getElementById('sub_end_date');
        if (!sdEl || !edEl || !sdEl.value || !edEl.value) {
            return { status: 'empty' };
        }
        var sd = sdEl.value;
        var ed = edEl.value;
        if (ed <= sd) {
            return { status: 'end_before_start' };
        }
        var n = daysBetweenInclusiveStartEnd(sd, ed);
        if (n === null || n < 7) {
            return { status: 'too_short', dayDelta: n };
        }
        return { status: 'ok', dayDelta: n, durationWeeks: Math.ceil(n / 7) };
    }

    function updateDurationWeeksUi() {
        var ro = document.getElementById('duration_weeks_readonly');
        var hint = document.getElementById('duration_weeks_hint');
        if (!ro || !hint) return;
        var r = computeDurationWeeksFromInputs();
        if (r.status === 'empty') {
            ro.value = '';
            ro.placeholder = '—';
            hint.textContent = 'Select start and end dates. Minimum span is 7 days.';
        } else if (r.status === 'end_before_start') {
            ro.value = '';
            ro.placeholder = '—';
            hint.textContent = 'End date must be after start date.';
        } else if (r.status === 'too_short') {
            ro.value = '';
            ro.placeholder = '—';
            hint.textContent = 'Subscription must be at least 7 days long.';
        } else {
            ro.value = String(r.durationWeeks);
            ro.placeholder = '';
            hint.textContent = 'Duration: ' + r.durationWeeks + ' week(s) (ceil(' + r.dayDelta + ' days ÷ 7)).';
        }
    }

    function syncPanels() {
        var submode = modeSel.value === 'subscription';
        // Avoid relying on the HTML "hidden" attribute so it works consistently.
        otPanel.style.display = submode ? 'none' : '';
        subPanel.style.display = submode ? '' : 'none';

        Array.prototype.forEach.call(otPanel.querySelectorAll('.one-time-input'), function (inp) {
            inp.disabled = submode;
            inp.required = !submode;
            if (submode) { inp.value = ''; }
        });

        Array.prototype.forEach.call(subPanel.querySelectorAll('.sub-input'), function (inp) {
            inp.disabled = !submode;
            inp.required = submode;
        });
        Array.prototype.forEach.call(subPanel.querySelectorAll('.sub-day'), function (cb) {
            cb.disabled = !submode;
            if (!submode) { cb.checked = false; }
        });

        updateDurationWeeksUi();
    }

    var bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function (ev) {
            if (modeSel.value !== 'subscription') return;
            var anyDay = !!subPanel.querySelector('.sub-day:checked');
            if (!anyDay) {
                ev.preventDefault();
                alert('Please select at least one day of the week');
                return;
            }
            var r = computeDurationWeeksFromInputs();
            if (r.status !== 'ok') {
                ev.preventDefault();
                if (r.status === 'empty') {
                    alert('Please provide subscription start and end dates.');
                } else if (r.status === 'end_before_start') {
                    alert('End date must be after start date.');
                } else {
                    alert('Subscription must be at least 7 days long');
                }
            }
        });
    }

    var sd = document.getElementById('sub_start_date');
    var ed = document.getElementById('sub_end_date');
    if (sd) sd.addEventListener('change', updateDurationWeeksUi);
    if (ed) ed.addEventListener('change', updateDurationWeeksUi);
    syncPanels();
})();
</script>

<div class="mt-3">
    <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>" class="btn btn-outline">← Back to search</a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
