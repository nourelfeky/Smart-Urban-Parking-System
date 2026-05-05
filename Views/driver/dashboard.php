<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">Welcome back, <?= htmlspecialchars($u['name']) ?></h1>
    <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>" class="btn btn-primary">Find Parking</a>
</div>

<?php if ($dinfo && !$dinfo['can_book']): ?>
<div class="alert alert-error">Your account is suspended from making bookings due to unpaid fines.</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Active Bookings</div>
        <div class="value"><?= $active_count ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Pending Fines</div>
        <div class="value" style="color:<?= $fine_count > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= $fine_count ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Wallet Balance</div>
        <div class="value" style="color:var(--green)"><?= number_format($dinfo['wallet_balance'] ?? 0, 2) ?> EGP</div>
    </div>
    <div class="stat-card">
        <div class="label">Notifications</div>
        <div class="value"><?= $notif_count ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Loyalty Tier</div>
        <div class="value" style="font-size:18px;text-transform:capitalize"><?= $loy['current_tier'] ?></div>
        <div class="sub"><?= $loy['booking_last_30_days'] ?> bookings this month</div>
    </div>
</div>

<div class="card">
    <div class="card-title">Recent Bookings</div>
    <?php if (empty($recent)): ?>
        <p class="text-muted">No bookings yet. <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>">Find a parking spot</a>.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Spot</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Cost</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $b): ?>
                <tr>
                    <td><?= $b['reservation_id'] ?></td>
                    <td><?= htmlspecialchars($b['address']) ?></td>
                    <td><?= date('d M, H:i', strtotime($b['start_time'])) ?></td>
                    <td><?= date('d M, H:i', strtotime($b['end_time'])) ?></td>
                    <td><?= number_format($b['final_cost'], 2) ?> EGP</td>
                    <td>
                        <?php
                        $badges = ['confirmed'=>'badge-blue','active'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red','no_show'=>'badge-amber'];
                        $bc = $badges[$b['status']] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $bc ?>"><?= $b['status'] ?></span>
                    </td>
                    <td>
                        <a href="<?= htmlspecialchars(route_url('/driver/bookingdetail?id=' . $b['reservation_id'])) ?>" class="btn btn-outline btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-title">Upcoming Commuter Reservations</div>
    <?php if (empty($subscriptions)): ?>
        <p class="text-muted">No subscription plan found. You can create one from the booking page by selecting "Commuter subscription".</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Spot</th><th>Days</th><th>Time Range</th><th>Weeks</th><th>Discount</th><th>Status</th></tr>
                </thead>
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