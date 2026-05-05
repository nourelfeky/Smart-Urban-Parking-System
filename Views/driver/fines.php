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
    <details class="mt-3">
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
    <?php elseif ($f['appeal_id']): ?>
    <p class="text-muted mt-2">Appeal status: <strong><?= $f['appeal_status'] ?></strong></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>