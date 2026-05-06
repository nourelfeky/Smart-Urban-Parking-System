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
        <p class="text-muted">Show this token at the spot scanner</p>
        <div class="qr-token"><?= htmlspecialchars($r['qr_code_token']) ?></div>
    </div>
    <div class="flex gap-2 mt-3" style="flex-wrap:wrap">
        <?php if ($r['status'] === 'confirmed'): ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="checkin">
            <button class="btn btn-success">Check In</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Cancel this booking?')">
            <input type="hidden" name="action" value="cancel">
            <button class="btn btn-outline" style="color:var(--red);border-color:var(--red)">Cancel Booking</button>
        </form>
        <?php elseif ($r['status'] === 'active'): ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="checkout">
            <button class="btn btn-primary">Check Out</button>
        </form>
        <form method="post" style="display:inline;display:flex;gap:6px;align-items:center">
            <input type="hidden" name="action" value="extend">
            <select name="extra_mins" class="form-control" style="width:130px">
                <option value="30">+30 min</option>
                <option value="60">+1 hour</option>
                <option value="120">+2 hours</option>
            </select>
            <button class="btn btn-outline">Extend Stay</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="mt-3">
    <a href="<?= htmlspecialchars(route_url('/driver/bookings')) ?>" class="btn btn-outline">← Back to Bookings</a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>