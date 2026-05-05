<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">All Parking Spots</h1>

<div class="flex gap-2 mb-3">
    <?php foreach (['all','available','occupied','reserved','maintenance','locked'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filter===$s ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Address</th><th>Owner</th><th>Zone</th><th>Rate</th><th>Status</th><th>Bookings</th><th>EV</th></tr></thead>
            <tbody>
            <?php if (empty($spots)): ?>
                <tr><td colspan="8" class="text-muted">No spots found.</td></tr>
            <?php endif; ?>
            <?php
            $sc = ['available'=>'badge-green','occupied'=>'badge-amber','reserved'=>'badge-blue','maintenance'=>'badge-gray','owner_use'=>'badge-gray','locked'=>'badge-red'];
            foreach ($spots as $s):
            ?>
            <tr>
                <td><?= $s['spot_id'] ?></td>
                <td><?= htmlspecialchars($s['address']) ?></td>
                <td><?= htmlspecialchars($s['owner_name']) ?></td>
                <td><?= htmlspecialchars($s['zone_name'] ?? '—') ?></td>
                <td><?= number_format($s['base_rate'],2) ?> EGP</td>
                <td><span class="badge <?= $sc[$s['status']] ?? 'badge-gray' ?>"><?= $s['status'] ?></span></td>
                <td><?= $s['total_bookings'] ?></td>
                <td><?= $s['has_ev_charger'] ? '<span class="badge badge-green">⚡</span>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>