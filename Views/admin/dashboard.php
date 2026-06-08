<?php require __DIR__ . '/../layout/header.php'; ?>

<section class="page-hero">
    <div class="page-hero-content">
        <p class="hero-eyebrow">Administrator</p>
        <h1>Platform Overview</h1>
        <p class="hero-sub">Monitor spots, revenue, appeals, and owner verifications from one command center.</p>
    </div>
    <div class="page-hero-visual">
        <div class="hero-stat-pill">
            <div class="num" data-count="<?= (int)$total_spots ?>">0</div>
            <div class="lbl">Total Spots</div>
        </div>
        <div class="hero-stat-pill">
            <div class="num" data-count="<?= (int)$active_res ?>">0</div>
            <div class="lbl">Active</div>
        </div>
        <div class="hero-stat-pill">
            <div class="num" data-count="<?= (float)$total_rev ?>">0</div>
            <div class="lbl">EGP Revenue</div>
        </div>
    </div>
</section>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Spots</div>
        <div class="value"><?= $total_spots ?></div>
        <div class="sub"><?= $avail_spots ?> available</div>
    </div>
    <div class="stat-card">
        <div class="label">Active Bookings</div>
        <div class="value"><?= $active_res ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Pending Fines</div>
        <div class="value stat-value--danger"><?= $pending_fin ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Pending Appeals</div>
        <div class="value stat-value--warning"><?= $pending_app ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Owner Verifications</div>
        <div class="value"><?= $pending_ver ?></div>
        <div class="sub">awaiting review</div>
    </div>
    <div class="stat-card">
        <div class="label">Spot listings</div>
        <div class="value stat-value--warning"><?= (int)($pending_spot_listings ?? 0) ?></div>
        <div class="sub">pending document review</div>
    </div>
    <div class="stat-card">
        <div class="label">Total Revenue</div>
        <div class="value"><?= number_format($total_rev, 0) ?></div>
        <div class="sub">EGP (platform)</div>
    </div>
</div>

<div class="grid-2">
    <a href="<?= htmlspecialchars(route_url('/admin/appeals')) ?>" class="card card-link">
        <div class="card-title">Pending Appeals</div>
        <p class="text-muted">Review driver fine appeals and make decisions.</p>
        <span class="badge badge-amber"><?= $pending_app ?> pending</span>
    </a>
    <a href="<?= htmlspecialchars(route_url('/admin/owners')) ?>" class="card card-link">
        <div class="card-title">Owner Verifications</div>
        <p class="text-muted">Approve or reject space owner documents.</p>
        <span class="badge badge-blue"><?= $pending_ver ?> pending</span>
    </a>
    <a href="<?= htmlspecialchars(route_url('/admin/spot-approvals')) ?>" class="card card-link">
        <div class="card-title">Spot listing approvals</div>
        <p class="text-muted">Review per-spot documents before drivers can book.</p>
        <span class="badge badge-amber"><?= (int)($pending_spot_listings ?? 0) ?> pending</span>
    </a>
    <a href="<?= htmlspecialchars(route_url('/admin/zones')) ?>" class="card card-link">
        <div class="card-title">Zone Management</div>
        <p class="text-muted">Lock zones for events, manage restrictions.</p>
    </a>
    <a href="<?= htmlspecialchars(route_url('/admin/heatmap')) ?>" class="card card-link">
        <div class="card-title">Revenue Heatmap</div>
        <p class="text-muted">See which areas generate the most parking revenue.</p>
    </a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
