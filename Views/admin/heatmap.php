<?php require __DIR__ . '/../layout/header.php'; 

function heat_color(float $val, float $max): string {
    if ($max <= 0) return '#dbeafe';
    $pct = $val / $max;
    if ($pct > 0.75) return '#1d4ed8';
    if ($pct > 0.50) return '#3b82f6';
    if ($pct > 0.25) return '#93c5fd';
    return '#dbeafe';
}

function heat_text(float $val, float $max): string {
    return ($val / ($max ?: 1) > 0.5) ? '#ffffff' : '#1e3a8a';
}
?>

<h1 class="page-title">Revenue Heatmap</h1>

<div class="card mb-3">
    <div class="card-title">Heat Legend</div>
    <div class="flex gap-2 items-center" style="flex-wrap:wrap">
        <div style="width:28px;height:20px;background:#dbeafe;border-radius:4px"></div><span class="text-muted">Low</span>
        <div style="width:28px;height:20px;background:#93c5fd;border-radius:4px"></div><span class="text-muted">Medium</span>
        <div style="width:28px;height:20px;background:#3b82f6;border-radius:4px"></div><span class="text-muted">High</span>
        <div style="width:28px;height:20px;background:#1d4ed8;border-radius:4px"></div><span class="text-muted">Top</span>
    </div>
</div>

<div class="card">
    <div class="card-title">Spot Revenue Intensity</div>
    <?php if (empty($data)): ?>
        <p class="text-muted">No revenue data yet.</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
        <?php foreach ($data as $row):
            $bg   = heat_color((float)$row['revenue'], $max_rev);
            $col  = heat_text((float)$row['revenue'], $max_rev);
        ?>
        <div style="background:<?= $bg ?>;color:<?= $col ?>;border-radius:8px;padding:14px;min-height:80px">
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
                    ?>
                    <div style="background:var(--gray-100);border-radius:4px;height:8px;width:120px">
                        <div style="background:<?= heat_color((float)$row['revenue'],$max_rev) ?>;height:8px;border-radius:4px;width:<?= $pct ?>%"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>