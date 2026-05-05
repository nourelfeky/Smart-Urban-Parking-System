<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">My Parking Spots</h1>

<div class="card mb-3">
    <div class="card-title">List a New Spot</div>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-control" placeholder="12 Street, District" required>
            </div>
            <div class="form-group">
                <label>Hourly Rate (EGP)</label>
                <input type="number" name="base_rate" class="form-control" step="0.5" min="1" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Max Vehicle Height (cm)</label>
                <input type="number" name="height" class="form-control" step="1">
            </div>
            <div class="form-group">
                <label>Max Vehicle Width (cm)</label>
                <input type="number" name="width" class="form-control" step="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Available From</label>
                <input type="time" name="avail_start" class="form-control" value="08:00">
            </div>
            <div class="form-group">
                <label>Available Until</label>
                <input type="time" name="avail_end" class="form-control" value="22:00">
            </div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="ev" id="ev">
            <label for="ev" style="text-transform:none;letter-spacing:0;font-size:14px">Has EV Charger</label>
        </div>
        <button type="submit" class="btn btn-primary">Add Spot</button>
    </form>
</div>

<?php if (empty($spots)): ?>
<div class="card"><p class="text-muted">No spots listed yet.</p></div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Address</th><th>Rate</th><th>Status</th><th>EV</th><th>Hours</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($spots as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['address']) ?></td>
                <td><?= number_format($s['base_rate'],2) ?> EGP</td>
                <td>
                    <?php
                    $sc = ['available'=>'badge-green','occupied'=>'badge-amber','reserved'=>'badge-blue','maintenance'=>'badge-gray','owner_use'=>'badge-gray','locked'=>'badge-red'];
                    echo '<span class="badge ' . ($sc[$s['status']] ?? 'badge-gray') . '">' . $s['status'] . '</span>';
                    ?>
                </td>
                <td><?= $s['has_ev_charger'] ? '<span class="badge badge-green">⚡</span>' : '—' ?></td>
                <td><?= substr($s['availability_start'],0,5) ?> – <?= substr($s['availability_end'],0,5) ?></td>
                <td class="flex gap-2" style="flex-wrap:wrap">
                    <?php if ($s['status'] === 'available'): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <input type="hidden" name="new_status" value="maintenance">
                        <button class="btn btn-warning btn-sm">Maintenance</button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <input type="hidden" name="new_status" value="owner_use">
                        <button class="btn btn-outline btn-sm">Owner Use</button>
                    </form>
                    <?php elseif (in_array($s['status'], ['maintenance','owner_use'])): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <input type="hidden" name="new_status" value="available">
                        <button class="btn btn-success btn-sm">Set Available</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this spot?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>