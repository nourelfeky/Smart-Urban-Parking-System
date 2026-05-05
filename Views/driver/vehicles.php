<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">My Vehicles</h1>

<div class="card mb-3">
    <div class="card-title">Add Vehicle</div>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>License Plate</label>
                <input type="text" name="plate" class="form-control" placeholder="ABC-1234" required>
            </div>
            <div class="form-group">
                <label>Height (cm)</label>
                <input type="number" name="height" class="form-control" step="0.1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Width (cm)</label>
                <input type="number" name="width" class="form-control" step="0.1">
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:22px">
                <input type="checkbox" name="ev_capable" id="ev">
                <label for="ev" style="text-transform:none;letter-spacing:0;font-size:14px">EV capable</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Vehicle</button>
    </form>
</div>

<?php if (empty($vehicles)): ?>
<div class="card"><p class="text-muted">No vehicles added yet.</p></div>
<?php else: ?>
<div class="card">
    <div class="card-title">Registered Vehicles</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Plate</th><th>Height</th><th>Width</th><th>EV</th><th>Default</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($vehicles as $v): ?>
            <tr>
                <td><strong><?= htmlspecialchars($v['license_plate']) ?></strong></td>
                <td><?= $v['height_cm'] ? $v['height_cm'] . ' cm' : '—' ?></td>
                <td><?= $v['width_cm'] ? $v['width_cm'] . ' cm' : '—' ?></td>
                <td><?= $v['is_ev_capable'] ? '<span class="badge badge-green">Yes</span>' : '—' ?></td>
                <td><?= $v['is_default'] ? '<span class="badge badge-blue">Default</span>' : '' ?></td>
                <td class="flex gap-2">
                    <?php if (!$v['is_default']): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="set_default">
                        <input type="hidden" name="vehicle_id" value="<?= $v['vehicle_id'] ?>">
                        <button class="btn btn-outline btn-sm">Set Default</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Remove this vehicle?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="vehicle_id" value="<?= $v['vehicle_id'] ?>">
                        <button class="btn btn-danger btn-sm">Remove</button>
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