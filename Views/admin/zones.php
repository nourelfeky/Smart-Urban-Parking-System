<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Zone Management</h1>

<div class="card mb-3">
    <div class="card-title">Add New Zone</div>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Zone Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Heliopolis" required>
            </div>
            <div class="form-group">
                <label>VAT Rate (%)</label>
                <input type="number" name="vat_rate" class="form-control" value="14" min="0" max="100" step="0.5">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Zone</button>
    </form>
</div>

<div class="card">
    <div class="card-title">All Zones</div>
    <?php if (empty($zones)): ?>
        <p class="text-muted">No zones defined.</p>
    <?php else: ?>
    <?php foreach ($zones as $z): ?>
    <div style="padding:16px 0;border-bottom:1px solid var(--gray-100)">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:8px;margin-bottom:10px">
            <div>
                <strong><?= htmlspecialchars($z['name']) ?></strong>
                <span class="text-muted"> — <?= $z['spot_count'] ?> spot(s) — VAT <?= round($z['vat_rate']*100,1) ?>%</span>
            </div>
            <span class="badge <?= $z['status']==='active' ? 'badge-green' : 'badge-red' ?>"><?= $z['status'] ?></span>
        </div>

        <?php if ($z['status'] === 'locked'): ?>
        <p class="text-muted">
            Locked for: <strong><?= htmlspecialchars($z['locked_event']) ?></strong>
            &nbsp;|&nbsp; <?= date('d M H:i', strtotime($z['lock_start'])) ?> – <?= date('d M H:i', strtotime($z['lock_end'])) ?>
        </p>
        <form method="post" class="mt-2">
            <input type="hidden" name="action" value="unlock">
            <input type="hidden" name="zone_id" value="<?= $z['zone_id'] ?>">
            <button class="btn btn-success btn-sm">Unlock Zone</button>
        </form>
        <?php else: ?>
        <details>
            <summary class="btn btn-warning btn-sm" style="cursor:pointer;display:inline-flex">Lock for Event</summary>
            <form method="post" class="mt-3" style="max-width:500px">
                <input type="hidden" name="action" value="lock">
                <input type="hidden" name="zone_id" value="<?= $z['zone_id'] ?>">
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="locked_event" class="form-control" placeholder="e.g. Cairo Marathon 2026" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Lock Start</label>
                        <input type="datetime-local" name="lock_start" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Lock End</label>
                        <input type="datetime-local" name="lock_end" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger">Lock Zone</button>
            </form>
        </details>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>