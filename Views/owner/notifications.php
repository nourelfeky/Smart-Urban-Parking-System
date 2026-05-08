<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Notifications</h1>

<div class="card">
    <div class="row-between mb-12">
        <div class="card-title">Owner Notifications</div>
        <?php if (!empty($notifs)): ?>
            <form method="post" action="<?= htmlspecialchars(route_url('/owner/notifications')) ?>">
                <button class="btn btn-outline" type="submit">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifs)): ?>
        <p class="text-muted">No notifications yet.</p>
    <?php else: ?>
        <div class="grid gap-8">
            <?php foreach ($notifs as $n): ?>
                <div class="card <?= (int)($n['is_read'] ?? 0) === 0 ? 'border-accent' : '' ?>">
                    <div class="row-between">
                        <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($n['type'] ?? 'general')))) ?></strong>
                        <small class="text-muted"><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$n['created_at']))) ?></small>
                    </div>
                    <p class="mt-8 mb-0"><?= htmlspecialchars((string)($n['message'] ?? '')) ?></p>
                    <?php if ((int)($n['is_read'] ?? 0) === 0): ?>
                        <small class="text-muted">New</small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
