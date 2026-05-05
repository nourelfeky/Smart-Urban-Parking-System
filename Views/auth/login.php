<?php
$pageTitle = 'Login';
include __DIR__ . '/../layout/header.php';
?>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">City<span>Slot</span></div>
        <p class="login-sub">Smart Urban Parking Management</p>
        <?php if ($alreadyLoggedIn): ?>
            <div class="alert alert-info">
                You are already logged in as <?= htmlspecialchars($loggedInName ?: $loggedInRole) ?>.
                Use the navigation bar to logout and sign in as a different user.
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!$alreadyLoggedIn): ?>
        <form method="post" action="<?= htmlspecialchars(route_url('/login')) ?>">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus
                           value="<?= htmlspecialchars($postedEmail ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-3">Sign in</button>
            </form>
            <p class="text-muted mt-3" style="text-align:center">
                Don't have an account? <a href="<?= htmlspecialchars(route_url('/register')) ?>">Register</a>
            </p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
