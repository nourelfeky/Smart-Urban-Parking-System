<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Fines Management</h1>

<div class="card mb-3">
    <div class="card-title">Issue New Fine</div>
    <form method="post">
        <input type="hidden" name="action" value="issue">
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
                <select name="type" class="form-control">
                    <option value="unauthorized">Unauthorized Parking</option>
                    <option value="overstay">Overstay</option>
                </select>
            </div>
            <div class="form-group">
                <label>Fine Amount (EGP)</label>
                <input type="number" name="amount" class="form-control" value="50" min="10" step="5">
            </div>
        </div>
        <button type="submit" class="btn btn-danger">Issue Fine</button>
    </form>
</div>

<div class="card">
    <div class="card-title">All Fines</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Driver</th><th>Spot</th><th>Type</th><th>Amount</th><th>Status</th><th>Appeal</th><th></th></tr></thead>
            <tbody>
            <?php
            $fb = ['pending'=>'badge-amber','paid'=>'badge-green','appealed'=>'badge-blue','cancelled'=>'badge-gray'];
            foreach ($fines as $f):
            ?>
            <tr>
                <td><?= $f['fine_id'] ?></td>
                <td><?= htmlspecialchars($f['driver_name']) ?></td>
                <td><?= htmlspecialchars($f['address']) ?></td>
                <td><?= $f['type'] ?></td>
                <td style="color:var(--red)"><?= number_format($f['penalty_amount'],2) ?> EGP</td>
                <td><span class="badge <?= $fb[$f['status']] ?? 'badge-gray' ?>"><?= $f['status'] ?></span></td>
                <td><?= $f['appeal_id'] ? '<span class="badge badge-blue">' . $f['appeal_status'] . '</span>' : '—' ?></td>
                <td>
                    <?php if ($f['status'] === 'pending'): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="fine_id" value="<?= $f['fine_id'] ?>">
                        <button class="btn btn-outline btn-sm">Cancel</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>