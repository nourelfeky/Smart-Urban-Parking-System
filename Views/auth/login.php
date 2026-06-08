<?php
$pageTitle = 'Login';
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
            <p class="auth-brand-tagline">Smart urban parking — find, book, and manage spots across the city in seconds.</p>
            <ul class="auth-features">
                <li><span class="auth-feature-icon">📍</span> Real-time spot availability</li>
                <li><span class="auth-feature-icon">⚡</span> Instant QR check-in &amp; checkout</li>
                <li><span class="auth-feature-icon">🛡️</span> Secure payments &amp; escrow</li>
                <li><span class="auth-feature-icon">📊</span> Live earnings &amp; analytics</li>
            </ul>
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="auth-form-box">
            <h2>Welcome back</h2>
            <p class="auth-form-sub">Sign in to your CitySlot account</p>
            <?php if ($alreadyLoggedIn): ?>
                <div class="alert alert-info">
                    You are already logged in as <?= htmlspecialchars($loggedInName ?: $loggedInRole) ?>.
                    Use the navigation to sign out and switch accounts.
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
                <button type="submit" class="btn btn-primary btn-block btn-lg mt-3">Sign in</button>
            </form>
            <p class="text-muted mt-3 text-center">
                Don't have an account? <a href="<?= htmlspecialchars(route_url('/register')) ?>">Create one free</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
