<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Owner Verification Requests</h1>

<?php if (empty($requests)): ?>
<div class="card"><p class="text-muted">No verification requests.</p></div>
<?php else: ?>
<?php foreach ($requests as $req): ?>
<div class="card">
    <div class="flex items-center justify-between mb-3" style="flex-wrap:wrap;gap:8px">
        <div>
            <strong><?= htmlspecialchars($req['owner_name']) ?></strong>
            <span class="text-muted"> &lt;<?= htmlspecialchars($req['owner_email']) ?>&gt;</span>
        </div>
        <span class="badge <?= ['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'][$req['status']] ?? 'badge-gray' ?>"><?= $req['status'] ?></span>
    </div>
    <p class="text-muted">Submitted: <?= date('d M Y H:i', strtotime($req['submitted_at'])) ?></p>

    <?php if ($req['document_paths']): ?>
    <div class="mt-2 flex gap-2" style="flex-wrap:wrap">
        <?php foreach (explode(',', $req['document_paths']) as $doc): ?>
        <a href="<?= htmlspecialchars(route_url('/admin/view-doc?file=' . urlencode(trim($doc)))) ?>" target="_blank" class="btn btn-outline btn-sm">
            View Document
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($req['status'] === 'pending'): ?>
    <form method="post" class="mt-3">
        <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
        <input type="hidden" name="owner_id" value="<?= $req['owner_id'] ?>">
        <div class="form-group">
            <label>Decision Note (optional)</label>
            <input type="text" name="note" class="form-control" placeholder="Reason if rejecting...">
        </div>
        <div class="flex gap-2">
            <button type="submit" name="decision" value="approved" class="btn btn-success">Approve</button>
            <button type="submit" name="decision" value="rejected" class="btn btn-danger">Reject</button>
        </div>
    </form>
    <?php elseif ($req['decision_note']): ?>
    <p class="text-muted mt-2">Note: <?= htmlspecialchars($req['decision_note']) ?></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>