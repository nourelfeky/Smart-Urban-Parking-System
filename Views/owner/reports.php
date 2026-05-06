<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">Monthly Reports</h1>
    <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars(route_url('/owner/report-pdf?month=' . urlencode($month))) ?>">
        Download PDF
    </a>
</div>

<div class="card mb-3">
    <div class="card-title">Select Month</div>
    <form method="get" action="<?= htmlspecialchars(route_url('/owner/reports')) ?>" class="flex gap-2" style="flex-wrap:wrap;align-items:end">
        <div>
            <label class="text-muted" style="display:block;margin-bottom:6px">Month (YYYY-MM)</label>
            <input name="month" value="<?= htmlspecialchars($month) ?>" class="form-control" style="width:160px" />
        </div>
        <div>
            <button class="btn btn-primary">View</button>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Spots</div>
        <div class="value"><?= (int)$metrics['spot_count'] ?></div>
        <div class="sub">owned by you</div>
    </div>
    <div class="stat-card">
        <div class="label">Occupancy Rate</div>
        <div class="value"><?= number_format((float)$metrics['occupancy_rate'], 2) ?>%</div>
        <div class="sub">booked minutes / month minutes</div>
    </div>
    <div class="stat-card">
        <div class="label">Booked Minutes</div>
        <div class="value"><?= (int)$metrics['booked_minutes'] ?></div>
        <div class="sub">in <?= htmlspecialchars($month) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-title">Top-performing time slots</div>
    <?php if (empty($metrics['top_slots'])): ?>
        <p class="text-muted">No bookings found for this month.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table style="max-width:520px">
                <thead><tr><th>Start hour</th><th>Sessions</th></tr></thead>
                <tbody>
                <?php foreach ($metrics['top_slots'] as $row): ?>
                    <tr>
                        <td><?= str_pad((string)(int)$row['hour'], 2, '0', STR_PAD_LEFT) ?>:00</td>
                        <td><strong><?= (int)$row['sessions'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="mt-3">
    <a href="<?= htmlspecialchars(route_url('/owner/dashboard')) ?>" class="btn btn-outline">← Back to Dashboard</a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

