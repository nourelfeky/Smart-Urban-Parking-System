<?php
$tab = $tab ?? 'bookings';
$is_waitlist = $tab === 'waitlist';
$waitlist_entries = $waitlist_entries ?? [];
?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">My Bookings</h1>
    <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>" class="btn btn-primary">+ New Booking</a>
</div>

<div class="flex gap-2 mb-3" style="flex-wrap:wrap">
    <a href="<?= htmlspecialchars(route_url('/driver/bookings?tab=bookings')) ?>"
       class="btn btn-sm <?= !$is_waitlist ? 'btn-primary' : 'btn-outline' ?>">Bookings</a>
    <a href="<?= htmlspecialchars(route_url('/driver/bookings?tab=waitlist')) ?>"
       class="btn btn-sm <?= $is_waitlist ? 'btn-primary' : 'btn-outline' ?>">
        Waitlist
        <?php if (count($waitlist_entries) > 0): ?>
            <span class="badge badge-gray" style="margin-left:4px"><?= count($waitlist_entries) ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($is_waitlist): ?>

<div class="card">
    <div class="card-title">Your spot waitlists</div>
    <p class="text-muted" style="margin-top:-6px">When a watched spot frees up after checkout or cancellation, you receive an in-app notification.</p>
    <?php if (empty($waitlist_entries)): ?>
        <p class="text-muted">You are not on any waitlists. Join from a spot&apos;s booking page or from favorites.</p>
        <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>" class="btn btn-outline mt-2">Find parking</a>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Spot</th>
                    <th>Rate</th>
                    <th>Spot status</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sc = ['available' => 'badge-green', 'occupied' => 'badge-amber', 'reserved' => 'badge-blue', 'maintenance' => 'badge-gray', 'owner_use' => 'badge-gray', 'locked' => 'badge-red'];
            foreach ($waitlist_entries as $w):
                $bc = $sc[$w['spot_status']] ?? 'badge-gray';
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($w['address']) ?></strong></td>
                <td><?= number_format((float)$w['base_rate'], 2) ?> EGP/hr</td>
                <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($w['spot_status']) ?></span></td>
                <td class="text-muted"><?= date('d M Y, H:i', strtotime($w['joined_at'])) ?></td>
                <td class="flex gap-2" style="flex-wrap:wrap">
                    <a href="<?= htmlspecialchars(route_url('/driver/book?spot=' . (int)$w['spot_id'])) ?>" class="btn btn-outline btn-sm">Try to book</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Leave this waitlist?');">
                        <input type="hidden" name="action" value="leave_booking_waitlist">
                        <input type="hidden" name="spot_id" value="<?= (int)$w['spot_id'] ?>">
                        <button type="submit" class="btn btn-warning btn-sm">Leave</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="flex gap-2 mb-3" style="flex-wrap:wrap">
    <?php foreach (['all','confirmed','active','completed','cancelled'] as $s): ?>
        <a href="<?= htmlspecialchars(route_url('/driver/bookings?tab=bookings&status=' . $s)) ?>" class="btn btn-sm <?= $filter===$s ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if (empty($rows)): ?>
        <p class="text-muted">No bookings found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Spot</th><th>Start</th><th>End</th><th>Cost</th><th>Status</th><th>Payment</th><th>Type</th><th></th></tr></thead>
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
                <td>
                    <?php if (($b['payment_status'] ?? '') === 'completed'): ?>
                        <span class="badge badge-green">Payment Completed</span>
                    <?php elseif (($b['payment_status'] ?? '') === 'refunded'): ?>
                        <span class="badge badge-red">Refunded</span>
                    <?php else: ?>
                        <span class="badge badge-amber">Held in Escrow</span>
                    <?php endif; ?>
                </td>
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

<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
