<?php require __DIR__ . '/../layout/header.php';

function heat_class(float $val, float $max): string {
    if ($max <= 0) return 'heat-cell--low';
    $pct = $val / $max;
    if ($pct > 0.75) return 'heat-cell--top';
    if ($pct > 0.50) return 'heat-cell--high';
    if ($pct > 0.25) return 'heat-cell--medium';
    return 'heat-cell--low';
}
?>

<h1 class="page-title">Revenue Heatmap</h1>

<div class="card mb-3">
    <div class="card-title">Heat Legend</div>
    <div class="heat-legend">
        <div class="heat-swatch heat-cell--low"></div><span class="text-muted">Low</span>
        <div class="heat-swatch heat-cell--medium"></div><span class="text-muted">Medium</span>
        <div class="heat-swatch heat-cell--high"></div><span class="text-muted">High</span>
        <div class="heat-swatch heat-cell--top"></div><span class="text-muted">Top</span>
    </div>
</div>

<div class="card">
    <div class="card-title">Spot Revenue Intensity</div>
    <?php if (empty($data)): ?>
        <p class="text-muted">No revenue data yet.</p>
    <?php else: ?>
    <div class="heat-grid">
        <?php foreach ($data as $row):
            $cls = heat_class((float)$row['revenue'], $max_rev);
        ?>
        <div class="heat-cell <?= $cls ?>">
            <div style="font-size:12px;opacity:.8;margin-bottom:4px"><?= htmlspecialchars($row['zone_name'] ?? 'No Zone') ?></div>
            <div style="font-size:13px;font-weight:600;margin-bottom:6px"><?= htmlspecialchars(substr($row['address'],0,35)) ?><?= strlen($row['address'])>35?'…':'' ?></div>
            <div style="font-size:18px;font-weight:700"><?= number_format($row['revenue'],0) ?> EGP</div>
            <div style="font-size:11px;opacity:.8"><?= $row['sessions'] ?> session<?= $row['sessions']!=1?'s':'' ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Full Breakdown Table</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Spot</th><th>Zone</th><th>Sessions</th><th>Revenue (EGP)</th><th>Intensity</th></tr></thead>
            <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['address']) ?></td>
                <td><?= htmlspecialchars($row['zone_name'] ?? '—') ?></td>
                <td><?= $row['sessions'] ?></td>
                <td><strong><?= number_format($row['revenue'],2) ?></strong></td>
                <td>
                    <?php
                    $pct = $max_rev > 0 ? round($row['revenue'] / $max_rev * 100) : 0;
                    $barCls = heat_class((float)$row['revenue'], $max_rev);
                    ?>
                    <div style="background:var(--gray-100);border-radius:4px;height:8px;width:120px">
                        <div class="heat-cell <?= $barCls ?>" style="height:8px;border-radius:4px;width:<?= $pct ?>%;min-height:0;padding:0;border:none"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
