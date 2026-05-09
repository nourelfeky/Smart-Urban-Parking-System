<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">Booking #<?= $id ?></h1>
    <span class="badge <?= $bc ?>" style="font-size:14px;padding:6px 14px"><?= $r['status'] ?></span>
</div>

<?php if (($r['penalty_amount'] ?? 0) > 0): ?>
    <div class="card" style="border-left:4px solid var(--red)">
        <div style="font-weight:700;color:var(--red)">Overstay penalty applied</div>
        <div class="text-muted mt-1">
            You checked out after your reserved end time, so an overstay penalty was added to your total.
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Spot Details</div>
    <p><strong><?= htmlspecialchars($r['address']) ?></strong></p>
    <p class="text-muted mt-2">
        <?php if ($r['latitude'] && $r['longitude']): ?>
        <a href="https://maps.google.com/?q=<?= $r['latitude'] ?>,<?= $r['longitude'] ?>" target="_blank" class="btn btn-outline btn-sm">
            Open in Google Maps
        </a>
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <div class="card-title">Reservation Info</div>
    <p class="text-muted" style="font-size:13px;margin-bottom:12px">Times use the app timezone (<?= htmlspecialchars(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC') ?>).</p>
    <table style="max-width:480px">
        <tr><td class="text-muted">From</td><td style="padding-left:24px"><?= date('d M Y, H:i', strtotime($r['start_time'])) ?></td></tr>
        <tr><td class="text-muted">To</td><td style="padding-left:24px"><?= date('d M Y, H:i', strtotime($r['end_time'])) ?></td></tr>
        <tr><td class="text-muted">Buffer ends</td><td style="padding-left:24px"><?= date('d M Y, H:i', strtotime($r['buffer_end_time'])) ?></td></tr>
        <?php if ($r['license_plate']): ?>
        <tr><td class="text-muted">Vehicle</td><td style="padding-left:24px"><?= htmlspecialchars($r['license_plate']) ?></td></tr>
        <?php endif; ?>
        <tr><td class="text-muted">Base cost</td><td style="padding-left:24px"><?= number_format($r['base_cost'], 2) ?> EGP</td></tr>
        <?php if ($r['discount_amount'] > 0): ?>
        <tr><td class="text-muted">Discount</td><td style="padding-left:24px;color:var(--green)">- <?= number_format($r['discount_amount'], 2) ?> EGP</td></tr>
        <?php endif; ?>
        <tr><td class="text-muted">VAT</td><td style="padding-left:24px"><?= number_format($r['tax_amount'], 2) ?> EGP</td></tr>
        <tr style="font-weight:700"><td>Total</td><td style="padding-left:24px"><?= number_format($r['final_cost'], 2) ?> EGP</td></tr>
        <tr>
            <td class="text-muted">Escrow status</td>
            <td style="padding-left:24px">
                <?php if (($r['payment_status'] ?? '') === 'completed'): ?>
                    <span class="badge badge-green">Payment Completed</span>
                <?php elseif (($r['payment_status'] ?? '') === 'refunded'): ?>
                    <span class="badge badge-red">Payment Refunded</span>
                <?php else: ?>
                    <span class="badge badge-amber">Payment Held in Escrow</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php if (($r['penalty_amount'] ?? 0) > 0): ?>
        <tr><td class="text-muted">Reserved Time</td><td style="padding-left:24px"><?= date('d M Y, H:i', strtotime($r['end_time'])) ?></td></tr>
        <tr><td class="text-muted">Actual Time</td><td style="padding-left:24px"><?= $r['check_out_time'] ? date('d M Y, H:i', strtotime($r['check_out_time'])) : '-' ?></td></tr>
        <tr><td class="text-muted">Overstay Duration</td><td style="padding-left:24px"><?= (int)$r['overstay_minutes'] ?> minutes</td></tr>
        <tr><td class="text-muted">Overstay penalty</td><td style="padding-left:24px;color:var(--red)"><?= number_format($r['penalty_amount'], 2) ?> EGP</td></tr>
        <tr style="font-weight:700"><td>Total cost (with penalty)</td><td style="padding-left:24px"><?= number_format($r['final_cost'], 2) ?> EGP</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php if ($r['status'] === 'completed'): ?>
<div class="card">
    <div class="card-title">Rate the Space Owner</div>
    <?php if (!empty($reviewed_owner)): ?>
        <p class="text-muted">You already rated this owner for this reservation.</p>
    <?php else: ?>
        <form method="post" style="max-width:520px">
            <input type="hidden" name="action" value="rate_owner">
            <div class="flex gap-2" style="align-items:end;flex-wrap:wrap">
                <div>
                    <label class="text-muted" style="display:block;margin-bottom:6px">Rating</label>
                    <select name="rating" class="form-control" style="width:160px" required>
                        <option value="">Select…</option>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Good</option>
                        <option value="3">3 - OK</option>
                        <option value="2">2 - Bad</option>
                        <option value="1">1 - Very bad</option>
                    </select>
                </div>
                <div style="flex:1;min-width:220px">
                    <label class="text-muted" style="display:block;margin-bottom:6px">Comment (optional)</label>
                    <input name="comment" class="form-control" placeholder="Short feedback…" />
                </div>
                <div>
                    <button class="btn btn-primary">Submit</button>
                </div>
            </div>
        </form>
        <p class="text-muted mt-2">This rating contributes to the owner trust score (weighted by reviewer activity and recency).</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (in_array($r['status'], ['confirmed','active'])): ?>
<div class="card">
    <div class="card-title">QR Check-in / Check-out</div>
    <div class="qr-box">
        <p class="text-muted">Scan this QR code or show the token at the scanner</p>
        <div id="qr-code-container" style="display:flex;flex-direction:column;align-items:center;gap:12px">
            <div id="qrcode" style="padding:12px;background:#fff;border:1px solid #ddd;border-radius:4px"></div>
            <div class="qr-token" style="font-size:12px;color:#999"><?= htmlspecialchars($r['qr_code_token']) ?></div>
        </div>
    </div>
    <div class="flex gap-2 mt-3" style="flex-wrap:wrap">
        <?php if ($r['status'] === 'confirmed'): ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="checkin">
            <input type="hidden" name="qr_token" value="<?= htmlspecialchars((string)$r['qr_code_token']) ?>">
            <button class="btn btn-success">Check In</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Cancel this booking?')">
            <input type="hidden" name="action" value="cancel">
            <button class="btn btn-outline" style="color:var(--red);border-color:var(--red)">Cancel Booking</button>
        </form>
        <?php elseif ($r['status'] === 'active'): ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="qr_token" value="<?= htmlspecialchars((string)$r['qr_code_token']) ?>">
            <button class="btn btn-primary">Check Out</button>
        </form>
        <details style="display:inline-block">
            <summary class="btn btn-outline" style="cursor:pointer;display:inline-flex">Extend Stay</summary>
            <form method="post" class="mt-3" style="max-width:450px" id="extend_form">
                <input type="hidden" name="action" value="extend">
                <div class="form-group">
                    <label>Select Duration</label>
                    <select name="extra_mins" class="form-control" id="extend_duration_select" required>
                        <option value="">Choose extension length…</option>
                        <option value="30">+30 minutes</option>
                        <option value="60">+1 hour</option>
                        <option value="120">+2 hours</option>
                    </select>
                </div>
                <div id="extend_preview" style="display:none;padding:12px;background:#f5f5f5;border-radius:4px;margin-bottom:12px">
                    <p class="text-muted" style="margin:0;font-size:13px">Extension cost (estimated)</p>
                    <p style="margin:6px 0;font-weight:700">Base: <span id="preview_base">0</span> EGP</p>
                    <p style="margin:6px 0;font-weight:700">Tax: <span id="preview_tax">0</span> EGP</p>
                    <p style="margin:6px 0;color:var(--red);font-weight:700">Total: <span id="preview_total">0</span> EGP</p>
                    <div id="extend_conflict_msg" style="display:none;margin-top:12px;padding:8px;background:#ffe6e6;border:1px solid #ff9999;border-radius:3px;color:var(--red);font-size:13px">
                        Cannot extend — another booking conflicts with this time slot.
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" id="extend_confirm_btn" style="display:none">Confirm Extension & Pay</button>
                <input type="hidden" name="confirm_extend" value="0" id="confirm_extend_input">
            </form>
        </details>
        <?php endif; ?>
    </div>
    <p class="text-muted mt-2" style="font-size:12px">Check-in is allowed from 15 minutes before your start time through the end of the grace period (<?= (int)($r['grace_period_mins'] ?? 5) ?> min after start). The QR token must match (browser sends it with the button).</p>
</div>
<?php endif; ?>

<?php if (!empty($can_file_dispute ?? false)): ?>
<div class="card">
    <div class="card-title">Listing dispute (inaccurate description)</div>
    <?php if (!empty($dispute_pending ?? false)): ?>
        <p class="text-muted">You have a pending dispute for this booking. An admin will review it.</p>
    <?php else: ?>
        <p class="text-muted">If the spot did not match the listing (e.g. size), open a dispute. An admin can approve a partial refund and adjust the owner’s balance.</p>
        <form method="post" style="max-width:520px">
            <input type="hidden" name="action" value="file_dispute">
            <div class="form-group">
                <label>Describe the problem</label>
                <textarea name="dispute_reason" class="form-control" rows="3" required placeholder="Example: advertised sedan parking but opening too narrow for my vehicle."></textarea>
            </div>
            <div class="form-group">
                <label>Requested refund % (optional)</label>
                <input type="number" name="requested_percent" class="form-control" min="0" max="100" step="1" placeholder="e.g. 30">
            </div>
            <button type="submit" class="btn btn-outline">Submit dispute</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="mt-3">
    <a href="<?= htmlspecialchars(route_url('/driver/bookings')) ?>" class="btn btn-outline">← Back to Bookings</a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize QR Code
    const token = <?= json_encode($r['qr_code_token']) ?>;
    const qrcodeContainer = document.getElementById('qrcode');
    if (qrcodeContainer && token) {
        new QRCode(qrcodeContainer, {
            text: token,
            width: 256,
            height: 256,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    // Extension validation and cost calculator
    const durationSelect = document.getElementById('extend_duration_select');
    const previewDiv = document.getElementById('extend_preview');
    const confirmBtn = document.getElementById('extend_confirm_btn');
    const confirmInput = document.getElementById('confirm_extend_input');
    const conflictMsg = document.getElementById('extend_conflict_msg');
    const extendForm = document.getElementById('extend_form');

    if (durationSelect) {
        durationSelect.addEventListener('change', function() {
            const minutes = parseInt(this.value);
            if (minutes > 0) {
                const hours = minutes / 60;
                const baseRate = <?= (float)$r['base_rate'] ?>;
                const multiplier = <?= (float)($r['default_multiplier'] ?? 1.0) ?>;
                const vatRate = <?= (float)($r['vat_rate'] ?? 0.14) ?>;

                const extBase = Math.round((baseRate * multiplier * hours) * 100) / 100;
                const extTax = Math.round((extBase * vatRate) * 100) / 100;
                const extTotal = extBase + extTax;

                document.getElementById('preview_base').textContent = extBase.toFixed(2);
                document.getElementById('preview_tax').textContent = extTax.toFixed(2);
                document.getElementById('preview_total').textContent = extTotal.toFixed(2);

                previewDiv.style.display = 'block';
                
                // Check for conflicts on the server
                const currentEnd = <?= json_encode($r['end_time']) ?>;
                const spotId = <?= (int)$r['spot_id'] ?>;
                const currentStart = <?= json_encode($r['start_time']) ?>;
                const bufferMins = <?= (int)($r['buffer_duration_mins'] ?? 0) ?>;
                
                const newEndTs = new Date(currentEnd).getTime() + minutes * 60 * 1000;
                const newEnd = new Date(newEndTs).toISOString().slice(0, 19).replace('T', ' ');
                const newBufTs = newEndTs + bufferMins * 60 * 1000;
                const newBuf = new Date(newBufTs).toISOString().slice(0, 19).replace('T', ' ');

                // AJAX check for conflicts
                fetch('<?= route_url('/driver/check-extend-conflict') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'spot_id=' + spotId + '&start_time=' + encodeURIComponent(currentStart) + '&end_time=' + encodeURIComponent(newEnd) + '&buffer_mins=' + bufferMins + '&reservation_id=' + <?= (int)$id ?>
                })
                .then(response => response.json())
                .then(data => {
                    if (data.hasConflict) {
                        conflictMsg.style.display = 'block';
                        confirmBtn.style.display = 'none';
                        confirmInput.value = '0';
                    } else {
                        conflictMsg.style.display = 'none';
                        confirmBtn.style.display = 'inline-block';
                        confirmInput.value = '1';
                    }
                })
                .catch(err => {
                    console.error('Error checking conflicts:', err);
                    confirmBtn.style.display = 'inline-block';
                    confirmInput.value = '1';
                });
            } else {
                previewDiv.style.display = 'none';
                confirmBtn.style.display = 'none';
                confirmInput.value = '0';
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>