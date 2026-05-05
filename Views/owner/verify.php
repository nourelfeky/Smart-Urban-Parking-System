<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Account Verification</h1>

<div class="card mb-3">
    <div class="card-title">Verification Status</div>
    <?php
    $vb = ['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'];
    echo '<span class="badge ' . ($vb[$vst] ?? 'badge-gray') . '" style="font-size:14px;padding:6px 14px">' . ucfirst($vst) . '</span>';
    ?>
    <?php if ($vst === 'approved'): ?>
    <p class="text-muted mt-2">Your account is verified. You can list spots and receive payouts.</p>
    <?php elseif ($vst === 'pending'): ?>
    <p class="text-muted mt-2">Your documents are under review. We'll notify you once approved.</p>
    <?php else: ?>
    <p class="text-muted mt-2">Please upload your ID and a utility bill to verify your right to list spaces.</p>
    <?php endif; ?>
</div>

<?php if ($vst !== 'approved'): ?>
<div class="card mb-3">
    <div class="card-title">Submit Documents</div>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>National ID / Passport (JPG, PNG or PDF)</label>
            <input type="file" name="id_doc" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>
        <div class="form-group">
            <label>Utility Bill showing property ownership (JPG, PNG or PDF)</label>
            <input type="file" name="utility_bill" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit for Review</button>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($docs)): ?>
<div class="card">
    <div class="card-title">Submission History</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Submitted</th><th>Status</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
            <tr>
                <td><?= date('d M Y H:i', strtotime($d['submitted_at'])) ?></td>
                <td><span class="badge <?= ['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'][$d['status']] ?? 'badge-gray' ?>"><?= $d['status'] ?></span></td>
                <td class="text-muted"><?= htmlspecialchars($d['decision_note'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>