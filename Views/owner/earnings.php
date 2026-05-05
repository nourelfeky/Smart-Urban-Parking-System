<?php require __DIR__ . '/../layout/header.php'; ?>

<h1 class="page-title">Earnings & Payouts</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Available Balance</div>
        <div class="value"><?= number_format($odata['earnings_balance'] ?? 0, 2) ?></div>
        <div class="sub">EGP</div>
    </div>
    <div class="stat-card">
        <div class="label">Platform Commission</div>
        <div class="value">15%</div>
        <div class="sub">per booking</div>
    </div>
    <div class="stat-card">
        <div class="label">Verification</div>
        <div class="value" style="font-size:16px;text-transform:capitalize"><?= $odata['verification_status'] ?></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-title">Request Payout</div>
    <?php if (($odata['earnings_balance'] ?? 0) >= 100): ?>
    <p class="text-muted mb-3">Your balance of <strong><?= number_format($odata['earnings_balance'],2) ?> EGP</strong> is ready to withdraw.</p>
    <form method="post">
        <button type="submit" class="btn btn-success">Request Payout</button>
    </form>
    <?php else: ?>
    <p class="text-muted">Minimum payout is 100 EGP. Current balance: <?= number_format($odata['earnings_balance'] ?? 0, 2) ?> EGP.</p>
    <?php endif; ?>
</div>

<?php if (!empty($monthly)): ?>
<div class="card mb-3">
    <div class="card-title">Monthly Summary</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Month</th><th>Sessions</th><th>Gross Revenue</th><th>Your Share (85%)</th></tr></thead>
            <tbody>
            <?php foreach ($monthly as $m): ?>
            <tr>
                <td><?= $m['month'] ?></td>
                <td><?= $m['sessions'] ?></td>
                <td><?= number_format($m['gross'], 2) ?> EGP</td>
                <td><strong><?= number_format($m['net'], 2) ?> EGP</strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($payouts)): ?>
<div class="card">
    <div class="card-title">Payout History</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Period</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($payouts as $p): ?>
            <tr>
                <td><?= substr($p['week_start'],0,10) ?> – <?= substr($p['week_end'],0,10) ?></td>
                <td><?= number_format($p['amount'],2) ?> EGP</td>
                <td><span class="badge <?= ['pending'=>'badge-amber','paid'=>'badge-green','failed'=>'badge-red'][$p['status']] ?? 'badge-gray' ?>"><?= $p['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>