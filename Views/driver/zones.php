<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">Parking Zones</h1>
    <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars(route_url('/driver/search')) ?>">Find Spots</a>
</div>

<div class="card">
    <div class="card-title">Watch a full parking lot (zone)</div>
    <p class="text-muted">
        If a zone is full (0 available spots), you can watch it and get notified when any spot becomes available.
    </p>
</div>

<?php if (empty($zones)): ?>
    <div class="card"><p class="text-muted">No zones found.</p></div>
<?php else: ?>
    <div class="spot-grid">
        <?php foreach ($zones as $z): ?>
            <?php
                $available = (int)($z['available_spots'] ?? 0);
                $total = (int)($z['total_spots'] ?? 0);
                $isWatching = !empty($z['watch_id']);
            ?>
            <div class="spot-card">
                <div class="spot-addr"><?= htmlspecialchars($z['name']) ?></div>
                <div class="spot-meta">
                    <span class="badge <?= $available > 0 ? 'badge-green' : 'badge-amber' ?>">
                        <?= $available ?> available / <?= $total ?> total
                    </span>
                </div>

                <?php if ($available > 0): ?>
                    <div class="text-muted mt-2">This zone has availability now — you can book from search.</div>
                <?php else: ?>
                    <div class="text-muted mt-2">Zone is currently full.</div>
                <?php endif; ?>

                <div class="flex gap-2 mt-3" style="flex-wrap:wrap">
                    <?php if (!$isWatching): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="watch_zone">
                            <input type="hidden" name="zone_id" value="<?= (int)$z['zone_id'] ?>">
                            <button class="btn btn-outline btn-sm" <?= $available > 0 ? 'disabled' : '' ?>>
                                Watch Zone
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="unwatch_zone">
                            <input type="hidden" name="zone_id" value="<?= (int)$z['zone_id'] ?>">
                            <button class="btn btn-warning btn-sm">Unwatch</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>

