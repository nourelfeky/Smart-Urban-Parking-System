<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">My Bookings</h1>
    <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>" class="btn btn-primary">+ New Booking</a>
</div>

<div class="flex gap-2 mb-3">
    <?php foreach (['all','confirmed','active','completed','cancelled'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filter===$s ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if (empty($rows)): ?>
        <p class="text-muted">No bookings found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Spot</th><th>Start</th><th>End</th><th>Cost</th><th>Status</th><th>Type</th><th></th></tr></thead>
            <tbody>
            <?php
            $badges = ['confirmed'=>'badge-blue','active'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red','no_show'=>'badge-amber','pending'=>'badge-amber'];
            foreach ($rows as $b):
                $bc = $badges[$b['status']] ?? 'badge-gray';
            ?>
            <tr>
                <td><?= $b['reservation_id'] ?></td>
                <td><?= htmlspecialchars($b['address']) ?></td>
                <td><?= date('d M, H:i', strtotime($b['start_time'])) ?></td>
                <td><?= date('d M, H:i', strtotime($b['end_time'])) ?></td>
                <td><?= number_format($b['final_cost'], 2) ?> EGP</td>
                <td><span class="badge <?= $bc ?>"><?= $b['status'] ?></span></td>
                <td><?= !empty($b['subscription_id']) ? 'Subscription' : 'One-time' ?></td>
                <td><a href="<?= htmlspecialchars(route_url('/driver/bookingdetail?id=' . $b['reservation_id'])) ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-title">Commuter Subscriptions</div>
    <?php if (empty($subscriptions)): ?>
        <p class="text-muted">No active subscriptions yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Spot</th><th>Days</th><th>Time</th><th>Weeks</th><th>Discount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td>#<?= (int)$sub['subscription_id'] ?></td>
                    <td><?= htmlspecialchars($sub['address']) ?></td>
                    <td><?= htmlspecialchars($sub['days_of_week']) ?></td>
                    <td><?= date('H:i', strtotime($sub['start_time_of_day'])) ?> - <?= date('H:i', strtotime($sub['end_time_of_day'])) ?></td>
                    <td><?= (int)$sub['weeks'] ?></td>
                    <td><?= number_format((float)$sub['discount_percent'], 2) ?>%</td>
                    <td><?= htmlspecialchars($sub['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>