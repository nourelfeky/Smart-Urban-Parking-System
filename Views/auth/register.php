<?php
$pageTitle = 'Register';
include __DIR__ . '/../layout/header.php';
?>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">City<span>Slot</span></div>
        <p class="login-sub">Create your account</p>
        <?php if ($alreadyLoggedIn): ?>
            <div class="alert alert-info">You are already logged in. Please logout from the navigation bar first to continue.</div>
        <?php endif; ?>
        <?php if (!$alreadyLoggedIn): ?>
            <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(route_url('/register')) ?>">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($postedName ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($postedEmail ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>I am a</label>
                    <select name="role" class="form-control">
                        <option value="driver" <?= ($postedRole ?? '') === 'driver' ? 'selected' : '' ?>>Driver</option>
                        <option value="owner" <?= ($postedRole ?? '') === 'owner' ? 'selected' : '' ?>>Space Owner</option>
                        <option value="officer" <?= ($postedRole ?? '') === 'officer' ? 'selected' : '' ?>>Officer</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-3">Create Account</button>
            </form>
            <p class="text-muted mt-3" style="text-align:center">
                Already have an account? <a href="<?= htmlspecialchars(route_url('/login')) ?>">Sign in</a>
            </p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
