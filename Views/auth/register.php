<?php
$pageTitle = 'Register';
include __DIR__ . '/../layout/header.php';
?>
<button type="button" class="auth-theme-toggle topbar-theme-btn" id="topbar-theme-toggle" aria-label="Toggle theme">
    <span class="icon-sun" aria-hidden="true"></span>
    <span class="icon-moon" aria-hidden="true"></span>
</button>
<div class="auth-layout">
    <div class="auth-brand-panel">
        <div class="auth-brand-content">
            <div class="auth-brand-logo">City<span>Slot</span></div>
            <p class="auth-brand-tagline">Join thousands of drivers and space owners transforming urban parking.</p>
            <ul class="auth-features">
                <li><span class="auth-feature-icon">🚗</span> Book parking as a driver</li>
                <li><span class="auth-feature-icon">🏠</span> List your space as an owner</li>
                <li><span class="auth-feature-icon">👮</span> Enforce rules as an officer</li>
                <li><span class="auth-feature-icon">✨</span> Free to get started</li>
            </ul>
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="auth-form-box">
            <h2>Create account</h2>
            <p class="auth-form-sub">Set up your CitySlot profile in under a minute</p>
            <?php if ($alreadyLoggedIn): ?>
                <div class="alert alert-info">You are already logged in. Please sign out first to register a new account.</div>
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
                <button type="submit" class="btn btn-primary btn-block btn-lg mt-3">Create Account</button>
            </form>
            <p class="text-muted mt-3 text-center">
                Already have an account? <a href="<?= htmlspecialchars(route_url('/login')) ?>">Sign in</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
