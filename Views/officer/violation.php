<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Violations</h1>

<div class="card mb-3">
    <div class="card-title">Report a Violation</div>
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>Driver</label>
                <select name="driver_id" class="form-control" required>
                    <option value="">— Select driver —</option>
                    <?php foreach ($drivers as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Spot</label>
                <select name="spot_id" class="form-control" required>
                    <option value="">— Select spot —</option>
                    <?php foreach ($spots as $s): ?>
                    <option value="<?= $s['spot_id'] ?>"><?= htmlspecialchars($s['address']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Violation Type</label>
                <select name="vtype" class="form-control">
                    <option value="unauthorized">Unauthorized Parking</option>
                    <option value="overstay">Overstay</option>
                    <option value="invalid_permit">Invalid Permit</option>
                    <option value="double_parking">Double Parking</option>
                </select>
            </div>
            <div class="form-group">
                <label>Penalty Amount (EGP)</label>
                <input type="number" name="penalty_amount" class="form-control" value="50" min="0">
            </div>
        </div>
        <button type="submit" class="btn btn-danger">Submit Violation Report</button>
    </form>
</div>

<div class="card">
    <div class="card-title">Recent Violations</div>
    <?php if (empty($recent_fines)): ?>
        <p class="text-muted">No violations recorded yet.</p>
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
                <?php foreach ($recent_fines as $f): ?>
                <tr>
                    <td><?= date('d M, H:i', strtotime($f['issued_at'])) ?></td>
                    <td><?= htmlspecialchars($f['driver_name']) ?></td>
                    <td><?= htmlspecialchars($f['address']) ?></td>
                    <td><?= htmlspecialchars($f['type']) ?></td>
                    <td><?= number_format($f['penalty_amount'], 2) ?> EGP</td>
                    <td>
                        <?php
                        $badges = ['pending'=>'badge-amber','paid'=>'badge-green','cancelled'=>'badge-gray','appealed'=>'badge-blue'];
                        $bc = $badges[$f['status']] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $bc ?>"><?= $f['status'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
