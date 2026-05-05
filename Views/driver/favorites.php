<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Favorites & Waitlist</h1>

<?php if (empty($favs)): ?>
<div class="card">
    <p class="text-muted">No favorite spots yet. Book a spot and it'll appear here once you mark it.</p>
</div>
<?php else: ?>
<div class="spot-grid">
<?php foreach ($favs as $f): ?>
<div class="spot-card">
    <div class="spot-addr"><?= htmlspecialchars($f['custom_label'] ?: $f['address']) ?></div>
    <div class="spot-rate"><?= number_format($f['base_rate'],2) ?> EGP/hr</div>
    <div class="spot-meta">
        <?php
        $sc = ['available'=>'badge-green','occupied'=>'badge-amber','reserved'=>'badge-blue','maintenance'=>'badge-gray'];
        echo '<span class="badge ' . ($sc[$f['status']] ?? 'badge-gray') . '">' . $f['status'] . '</span>';
        ?>
    </div>

    <div class="flex gap-2" style="flex-wrap:wrap">
        <?php if ($f['status'] === 'available'): ?>
            <a href="<?= htmlspecialchars(route_url('/driver/book?spot=' . $f['spot_id'])) ?>" class="btn btn-primary btn-sm">Book</a>
        <?php else: ?>
            <?php if (!$f['waitlist_id']): ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="join_waitlist">
                <input type="hidden" name="spot_id" value="<?= $f['spot_id'] ?>">
                <button class="btn btn-outline btn-sm">Watch (Waitlist)</button>
            </form>
            <?php else: ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="leave_waitlist">
                <input type="hidden" name="spot_id" value="<?= $f['spot_id'] ?>">
                <button class="btn btn-warning btn-sm">Leave Waitlist</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="remove_fav">
            <input type="hidden" name="spot_id" value="<?= $f['spot_id'] ?>">
            <button class="btn btn-sm" style="color:var(--red)">Remove</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>