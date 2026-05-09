<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Booking disputes</h1>
<p class="text-muted mb-3">Listing-accuracy claims (e.g. spot size). Approve a partial refund percentage; the owner’s credited share is reversed proportionally.</p>

<?php if (empty($pending)): ?>
<div class="card"><p class="text-muted">No pending disputes.</p></div>
<?php else: ?>
<?php foreach ($pending as $d): ?>
<div class="card">
    <div class="flex items-center justify-between mb-2" style="flex-wrap:wrap;gap:8px">
        <div>
            <strong>#<?= (int)$d['dispute_id'] ?></strong>
            <span class="text-muted"> — Reservation #<?= (int)$d['reservation_id'] ?> (<?= htmlspecialchars((string)$d['res_status']) ?>)</span>
        </div>
        <span class="badge badge-amber">pending</span>
    </div>
    <p><strong>Driver:</strong> <?= htmlspecialchars((string)$d['driver_name']) ?> &lt;<?= htmlspecialchars((string)$d['driver_email']) ?>&gt;</p>
    <p><strong>Spot:</strong> <?= htmlspecialchars((string)$d['address']) ?></p>
    <p><strong>Booking total:</strong> <?= number_format((float)$d['final_cost'], 2) ?> EGP</p>
    <p class="mt-2"><strong>Reason:</strong><br><?= nl2br(htmlspecialchars((string)$d['reason'])) ?></p>
    <?php if ($d['refund_percent_requested'] !== null): ?>
        <p class="text-muted mt-2">Driver requested refund: <?= htmlspecialchars((string)$d['refund_percent_requested']) ?>%</p>
    <?php endif; ?>
    <p class="text-muted mt-2">Submitted: <?= date('d M Y H:i', strtotime((string)$d['created_at'])) ?></p>

    <form method="post" class="mt-3" style="max-width:520px">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="dispute_id" value="<?= (int)$d['dispute_id'] ?>">
        <div class="form-group">
            <label>Approved refund % (0–100)</label>
            <input type="number" name="approved_percent" class="form-control" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string)($d['refund_percent_requested'] ?? '25')) ?>" required>
        </div>
        <div class="form-group">
            <label>Admin note (optional)</label>
            <textarea name="admin_note" class="form-control" rows="2"></textarea>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-success">Approve refund</button>
        </div>
    </form>
    <form method="post" class="mt-2" onsubmit="return confirm('Reject this dispute?');">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="dispute_id" value="<?= (int)$d['dispute_id'] ?>">
        <div class="form-group">
            <label>Note (optional)</label>
            <textarea name="admin_note" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-outline" style="color:var(--red);border-color:var(--red)">Reject</button>
    </form>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
