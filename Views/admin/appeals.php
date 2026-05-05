<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Fine Appeals</h1>

<?php if (empty($appeals)): ?>
<div class="card"><p class="text-muted">No appeals submitted yet.</p></div>
<?php else: ?>
<?php foreach ($appeals as $a): ?>
<div class="card">
    <div class="flex items-center justify-between mb-3" style="flex-wrap:wrap;gap:8px">
        <div>
            <strong><?= htmlspecialchars($a['driver_name']) ?></strong>
            <span class="text-muted"> — Fine #<?= $a['fine_id'] ?> (<?= $a['fine_type'] ?>)</span>
        </div>
        <?php
        $ab = ['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'];
        echo '<span class="badge ' . ($ab[$a['status']] ?? 'badge-gray') . '">' . $a['status'] . '</span>';
        ?>
    </div>
    <p><strong>Spot:</strong> <?= htmlspecialchars($a['spot_addr']) ?></p>
    <p><strong>Fine Amount:</strong> <?= number_format($a['penalty_amount'],2) ?> EGP</p>
    <p class="mt-2"><strong>Driver's reason:</strong><br><?= htmlspecialchars($a['reason']) ?></p>
    <?php if ($a['evidence_url']): ?>
    <p class="mt-2"><a href="<?= htmlspecialchars($a['evidence_url']) ?>" target="_blank" class="btn btn-outline btn-sm">View Evidence</a></p>
    <?php endif; ?>
    <p class="text-muted mt-2">Submitted: <?= date('d M Y H:i', strtotime($a['submitted_at'])) ?></p>

    <?php if ($a['status'] === 'pending'): ?>
    <form method="post" class="mt-3">
        <input type="hidden" name="appeal_id" value="<?= $a['appeal_id'] ?>">
        <div class="form-group">
            <label>Decision Note</label>
            <textarea name="note" class="form-control" rows="2" placeholder="Optional reason..."></textarea>
        </div>
        <div class="flex gap-2">
            <button type="submit" name="decision" value="approved" class="btn btn-success">Approve (Cancel Fine)</button>
            <button type="submit" name="decision" value="rejected" class="btn btn-danger">Reject</button>
        </div>
    </form>
    <?php elseif ($a['decision_note']): ?>
    <p class="text-muted mt-2">Decision note: <?= htmlspecialchars($a['decision_note']) ?></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>