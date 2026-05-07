<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Spot listing approvals</h1>
<p class="text-muted mb-3">Review documents submitted by space owners for each new parking spot. Approve to make the spot bookable by drivers.</p>

<?php if (empty($pending)): ?>
<div class="card"><p class="text-muted">No pending spot listings.</p></div>
<?php else: ?>
<?php foreach ($pending as $p): ?>
<?php
    $files = array_filter(array_map('trim', explode(',', (string)$p['document_paths'])));
?>
<div class="card mb-3">
    <div class="flex items-center justify-between mb-2" style="flex-wrap:wrap;gap:8px">
        <div>
            <strong><?= htmlspecialchars($p['address']) ?></strong>
            <span class="text-muted"> — <?= number_format((float)$p['base_rate'], 2) ?> EGP/hr</span>
        </div>
        <span class="badge badge-amber">Pending review</span>
    </div>
    <p class="text-muted" style="font-size:13px;margin-bottom:12px">
        Owner: <strong><?= htmlspecialchars($p['owner_name']) ?></strong> (<?= htmlspecialchars($p['owner_email']) ?>)
        · Submitted <?= htmlspecialchars(substr((string)$p['submitted_at'], 0, 16)) ?>
    </p>
    <div class="mb-3">
        <div class="text-muted" style="font-size:12px;margin-bottom:6px">Documents</div>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <?php foreach ($files as $f): ?>
                <a class="btn btn-outline btn-sm" target="_blank" href="<?= htmlspecialchars(route_url('/admin/view-doc?bucket=spot&file=' . urlencode($f))) ?>"><?= htmlspecialchars($f) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="flex gap-2" style="flex-wrap:wrap;align-items:flex-end">
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="approve_spot_listing">
            <input type="hidden" name="submission_id" value="<?= (int)$p['submission_id'] ?>">
            <input type="hidden" name="spot_id" value="<?= (int)$p['spot_id'] ?>">
            <input type="hidden" name="owner_id" value="<?= (int)$p['owner_id'] ?>">
            <button type="submit" class="btn btn-success btn-sm">Approve spot</button>
        </form>
        <form method="post" class="flex gap-2" style="flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="action" value="reject_spot_listing">
            <input type="hidden" name="submission_id" value="<?= (int)$p['submission_id'] ?>">
            <input type="hidden" name="spot_id" value="<?= (int)$p['spot_id'] ?>">
            <input type="hidden" name="owner_id" value="<?= (int)$p['owner_id'] ?>">
            <input type="text" name="note" class="form-control" style="min-width:220px" placeholder="Rejection reason (optional)" />
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this listing?');">Reject</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
