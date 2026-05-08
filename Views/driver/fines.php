<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">My Fines</h1>

<?php if (empty($fines)): ?>
<div class="card"><p class="text-muted">No fines — great driving!</p></div>
<?php else: ?>
<?php foreach ($fines as $f): ?>
<div class="card">
    <div class="flex items-center justify-between mb-3" style="flex-wrap:wrap;gap:8px">
        <div>
            <strong>Fine #<?= $f['fine_id'] ?></strong> &mdash; <?= htmlspecialchars($f['address']) ?>
        </div>
        <?php
        $fb = ['pending'=>'badge-amber','paid'=>'badge-green','appealed'=>'badge-blue','cancelled'=>'badge-gray'];
        echo '<span class="badge ' . ($fb[$f['status']] ?? 'badge-gray') . '">' . $f['status'] . '</span>';
        ?>
    </div>
    <table style="max-width:400px">
        <tr><td class="text-muted">Type</td><td style="padding-left:20px"><?= $f['type'] ?></td></tr>
        <tr><td class="text-muted">Amount</td><td style="padding-left:20px;color:var(--red);font-weight:700"><?= number_format($f['penalty_amount'], 2) ?> EGP</td></tr>
        <tr><td class="text-muted">Issued</td><td style="padding-left:20px"><?= date('d M Y H:i', strtotime($f['issued_at'])) ?></td></tr>
        <?php if ($f['overstay_minutes'] > 0): ?>
        <tr><td class="text-muted">Overstay</td><td style="padding-left:20px"><?= $f['overstay_minutes'] ?> min</td></tr>
        <?php endif; ?>
    </table>

    <?php if ($f['status'] === 'pending'): ?>
    <div class="flex gap-2 mt-3" style="flex-wrap:wrap">
        <details style="display:inline-block">
            <summary class="btn btn-success btn-sm" style="cursor:pointer;display:inline-flex">Pay Fine</summary>
            <form method="post" class="mt-3" style="max-width:400px">
                <input type="hidden" name="action" value="pay_fine">
                <input type="hidden" name="fine_id" value="<?= $f['fine_id'] ?>">
                <div class="form-group">
                    <label>Cardholder Name</label>
                    <input type="text" name="card_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" required>
                </div>
                <div class="form-row" style="display:flex;gap:8px">
                    <div class="form-group" style="flex:1">
                        <label>Expiry Month</label>
                        <input type="text" name="card_expiry_month" class="form-control" placeholder="MM" maxlength="2" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Expiry Year</label>
                        <input type="text" name="card_expiry_year" class="form-control" placeholder="YY" maxlength="2" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>CVV</label>
                        <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm">Pay <?= number_format($f['penalty_amount'], 2) ?> EGP</button>
            </form>
        </details>
        <details style="display:inline-block">
            <summary class="btn btn-outline btn-sm" style="cursor:pointer;display:inline-flex">Submit Appeal</summary>
            <form method="post" enctype="multipart/form-data" class="mt-3">
                <input type="hidden" name="action" value="appeal">
                <input type="hidden" name="fine_id" value="<?= $f['fine_id'] ?>">
                <div class="form-group">
                    <label>Reason for appeal</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Evidence (photo/PDF, optional)</label>
                    <input type="file" name="evidence" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Submit Appeal</button>
            </form>
        </details>
    </div>
    <?php elseif ($f['appeal_id']): ?>
    <p class="text-muted mt-2">Appeal status: <strong><?= $f['appeal_status'] ?></strong></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>