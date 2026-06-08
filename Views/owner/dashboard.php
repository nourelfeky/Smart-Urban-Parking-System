<?php require __DIR__ . '/../layout/header.php'; ?>

<section class="page-hero">
    <div class="page-hero-content">
        <p class="hero-eyebrow">Space Owner</p>
        <h1>Your Parking Business</h1>
        <p class="hero-sub">Track earnings, manage spots, and grow your revenue with real-time booking insights.</p>
        <a href="<?= htmlspecialchars(route_url('/owner/spots')) ?>" class="btn btn-primary btn-lg">Manage Spots</a>
    </div>
    <div class="page-hero-visual">
        <div class="hero-stat-pill">
            <div class="num" data-count="<?= (int)$spot_count ?>">0</div>
            <div class="lbl">Spots</div>
        </div>
        <div class="hero-stat-pill">
            <div class="num" data-count="<?= (float)$total_rev ?>">0</div>
            <div class="lbl">EGP Revenue</div>
        </div>
        <div class="hero-stat-pill">
            <div class="num" data-count="<?= (float)($odata['earnings_balance'] ?? 0) ?>">0</div>
            <div class="lbl">Balance</div>
        </div>
    </div>
</section>

<?php if ($odata && $odata['verification_status'] !== 'approved'): ?>
<div class="alert alert-info">
    Your account verification is <strong><?= $odata['verification_status'] ?></strong>.
    <a href="<?= htmlspecialchars(route_url('/owner/verify')) ?>">Complete verification</a> to receive payouts.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">My Spots</div>
        <div class="value"><?= $spot_count ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Revenue</div>
        <div class="value"><?= number_format($total_rev, 0) ?></div>
        <div class="sub">EGP (gross)</div>
    </div>
    <div class="stat-card">
        <div class="label">Balance</div>
        <div class="value stat-value--success"><?= number_format($odata['earnings_balance'] ?? 0, 0) ?></div>
        <div class="sub">EGP available</div>
    </div>
    <div class="stat-card">
        <div class="label">My Share</div>
        <div class="value"><?= number_format($total_rev * 0.85, 0) ?></div>
        <div class="sub">EGP (85%)</div>
    </div>
    <div class="stat-card">
        <div class="label">Trust Score</div>
        <div class="value"><?= number_format((float)($odata['trust_score'] ?? 0), 1) ?></div>
        <div class="sub">out of 100</div>
    </div>
</div>

<div class="card">
    <div class="page-header mb-0">
        <div class="card-title mb-0">Recent Bookings on My Spots</div>
        <div class="flex gap-2">
            <a href="<?= htmlspecialchars(route_url('/owner/reports')) ?>" class="btn btn-outline btn-sm">Monthly Reports</a>
            <a href="<?= htmlspecialchars(route_url('/owner/spots')) ?>" class="btn btn-primary btn-sm">Manage Spots</a>
        </div>
    </div>
    <?php if (empty($recent)): ?>
        <p class="text-muted mt-3">No bookings yet.</p>
    <?php else: ?>
    <div class="table-wrap mt-3">
        <table>
            <thead><tr><th>Driver</th><th>Spot</th><th>Start</th><th>End</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php
            $badges = ['confirmed'=>'badge-blue','active'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red','no_show'=>'badge-amber'];
            foreach ($recent as $r):
                $bc = $badges[$r['status']] ?? 'badge-gray';
            ?>
            <tr>
                <td><?= htmlspecialchars($r['driver_name']) ?></td>
                <td><?= htmlspecialchars($r['address']) ?></td>
                <td><?= date('d M, H:i', strtotime($r['start_time'])) ?></td>
                <td><?= date('d M, H:i', strtotime($r['end_time'])) ?></td>
                <td><?= number_format($r['final_cost'], 2) ?> EGP</td>
                <td><span class="badge <?= $bc ?>"><?= $r['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
