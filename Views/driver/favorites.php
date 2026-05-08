<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Favorite spots</h1>

<?php if (!empty($home_adjacent) || !empty($work_adjacent)): ?>
<div class="card mb-3">
    <div class="card-title">Quick re-book near Home & Work</div>
    <p class="text-muted" style="margin-top:-6px">
        Based on your <strong>Home</strong> and <strong>Work</strong> favorites, here are nearby spots you can book in a few taps.
    </p>

    <?php if (!empty($home_adjacent)): ?>
        <h3 style="font-size:15px;margin-top:10px;margin-bottom:6px;">Near Home</h3>
        <ul style="list-style:none;padding:0;margin:0 0 8px 0;">
            <?php foreach ($home_adjacent as $alt): ?>
                <?php $homeUrl = route_url('/driver/book?spot=' . (int)$alt['spot_id']); ?>
                <li style="margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
                    <div class="flex gap-2 items-center" style="flex-wrap:wrap">
                        <strong><?= htmlspecialchars($alt['address']) ?></strong>
                        <span class="text-muted" style="font-size:13px">
                            <?= number_format((float)$alt['base_rate'], 2) ?> EGP/hr
                            <?php if (isset($alt['distance_km'])): ?>
                                · <?= number_format((float)$alt['distance_km'], 2) ?> km
                            <?php endif; ?>
                        </span>
                    </div>
                    <a class="btn btn-sm btn-outline mt-1" href="<?= htmlspecialchars($homeUrl) ?>">Book nearby Home</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($work_adjacent)): ?>
        <h3 style="font-size:15px;margin-top:12px;margin-bottom:6px;">Near Work</h3>
        <ul style="list-style:none;padding:0;margin:0;">
            <?php foreach ($work_adjacent as $alt): ?>
                <?php $workUrl = route_url('/driver/book?spot=' . (int)$alt['spot_id']); ?>
                <li style="margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
                    <div class="flex gap-2 items-center" style="flex-wrap:wrap">
                        <strong><?= htmlspecialchars($alt['address']) ?></strong>
                        <span class="text-muted" style="font-size:13px">
                            <?= number_format((float)$alt['base_rate'], 2) ?> EGP/hr
                            <?php if (isset($alt['distance_km'])): ?>
                                · <?= number_format((float)$alt['distance_km'], 2) ?> km
                            <?php endif; ?>
                        </span>
                    </div>
                    <a class="btn btn-sm btn-outline mt-1" href="<?= htmlspecialchars($workUrl) ?>">Book nearby Work</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

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