<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <div class="card-title">404 — Page not found</div>
    <p class="text-muted" style="margin-bottom:12px">
        The requested page could not be found.
    </p>
    <?php if (!empty($path)): ?>
        <div class="text-muted" style="font-family:monospace">
            Path: <?= htmlspecialchars((string)$path) ?>
        </div>
    <?php endif; ?>

    <div class="mt-3">
        <a href="<?= htmlspecialchars(route_url('/')) ?>" class="btn btn-primary">Go to Home</a>
        <?php if (!empty($u['role'])): ?>
            <?php
                $dash = $u['role'] === 'driver' ? '/driver/dashboard'
                    : ($u['role'] === 'owner' ? '/owner/dashboard'
                    : ($u['role'] === 'admin' ? '/admin/dashboard'
                    : ($u['role'] === 'officer' ? '/officer/dashboard' : '/')));
            ?>
            <a href="<?= htmlspecialchars(route_url($dash)) ?>" class="btn btn-outline">Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

