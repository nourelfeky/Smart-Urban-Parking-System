<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Officer Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Violations Detected</div>
        <div class="value"><?= $det_count ?></div>
        <div class="sub">system-wide</div>
    </div>
    <div class="stat-card">
        <div class="label">Flagged Spots</div>
        <div class="value" style="color:var(--red)"><?= $flagged ?></div>
        <div class="sub">pending fines</div>
    </div>
</div>

<div class="card mb-3">
    <div class="flex items-center justify-between mb-3">
        <div class="card-title" style="margin-bottom:0">Quick Actions</div>
    </div>
    <div class="flex gap-3" style="flex-wrap:wrap">
        <a href="<?= htmlspecialchars(route_url('/officer/violation')) ?>" class="btn btn-danger">Report Violation</a>
    </div>
</div>

<div class="card">
    <div class="card-title">Recent Violations</div>
    <?php if (empty($recent)): ?>
        <p class="text-muted">No violations reported yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Driver</th>
                    <th>Spot</th>
                    <th>Type</th>
                    <th>Penalty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $v): ?>
                <tr>
                    <td><?= date('d M, H:i', strtotime($v['issued_at'])) ?></td>
                    <td><?= htmlspecialchars($v['driver_name']) ?></td>
                    <td><?= htmlspecialchars($v['address']) ?></td>
                    <td><?= htmlspecialchars($v['type']) ?></td>
                    <td><?= number_format($v['penalty_amount'], 2) ?> EGP</td>
                    <td>
                        <?php
                        $badges = ['pending'=>'badge-amber','paid'=>'badge-green','cancelled'=>'badge-gray','appealed'=>'badge-blue'];
                        $bc = $badges[$v['status']] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $bc ?>"><?= $v['status'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
