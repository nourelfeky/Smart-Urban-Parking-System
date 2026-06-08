<?php
$pageTitle = 'Login';
include __DIR__ . '/../layout/header.php';
?>
<div class="auth-page">
    <!-- Animated city grid background -->
    <div class="auth-bg">
        <div class="auth-bg-grid"></div>
        <div class="auth-bg-glow auth-bg-glow--1"></div>
        <div class="auth-bg-glow auth-bg-glow--2"></div>
        <div class="auth-bg-glow auth-bg-glow--3"></div>
        <div class="auth-floating-dots">
            <span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Theme toggle -->
    <button type="button" class="auth-theme-toggle topbar-theme-btn" id="topbar-theme-toggle" aria-label="Toggle theme">
        <span class="icon-sun" aria-hidden="true"></span>
        <span class="icon-moon" aria-hidden="true"></span>
    </button>

    <div class="auth-stage">
        <!-- Left: Brand story -->
        <div class="auth-story">
            <div class="auth-story-inner">
                <a href="<?= htmlspecialchars(route_url('/')) ?>" class="auth-logo-link">
                    <span class="auth-logo-mark">
                        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="8" fill="url(#lg1)"/>
                            <path d="M16 8C12.134 8 9 11.134 9 15c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" fill="white"/>
                            <defs>
                                <linearGradient id="lg1" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#B58863"/>
                                    <stop offset="1" stop-color="#6B4F35"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </span>
                    <span class="auth-logo-text">City<em>Slot</em></span>
                </a>

                <div class="auth-headline">
                    <h1>Park smarter.<br>Move faster.</h1>
                    <p>Real-time spots, instant booking, and zero parking headaches — all in one place.</p>
                </div>

                <div class="auth-stat-row">
                    <div class="auth-stat">
                        <strong>2,400+</strong>
                        <span>Active spots</span>
                    </div>
                    <div class="auth-stat-divider"></div>
                    <div class="auth-stat">
                        <strong>98%</strong>
                        <span>On-time check-in</span>
                    </div>
                    <div class="auth-stat-divider"></div>
                    <div class="auth-stat">
                        <strong>4.9★</strong>
                        <span>Driver rating</span>
                    </div>
                </div>

                <ul class="auth-pill-list">
                    <li>
                        <span class="auth-pill-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                        </span>
                        Real-time spot availability
                    </li>
                    <li>
                        <span class="auth-pill-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"/><path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"/></svg>
                        </span>
                        Live earnings &amp; analytics dashboard
                    </li>
                    <li>
                        <span class="auth-pill-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        </span>
                        Secure payments &amp; escrow protection
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right: Login form -->
        <div class="auth-card-wrap">
            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Welcome back</h2>
                    <p>Sign in to your CitySlot account</p>
                </div>

                <?php if ($alreadyLoggedIn): ?>
                    <div class="alert alert-info">
                        You are already signed in as <?= htmlspecialchars($loggedInName ?: $loggedInRole) ?>.
                        Use the navigation to sign out first.
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!$alreadyLoggedIn): ?>
                <form method="post" action="<?= htmlspecialchars(route_url('/login')) ?>" class="auth-form">
                    <div class="auth-field">
                        <label for="email">
                            <span class="auth-field-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                            </span>
                            Email address
                        </label>
                        <input type="email" id="email" name="email" class="auth-input" placeholder="you@example.com" required autofocus
                               value="<?= htmlspecialchars($postedEmail ?? '') ?>">
                    </div>

                    <div class="auth-field">
                        <label for="password">
                            <span class="auth-field-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            </span>
                            Password
                        </label>
                        <div class="auth-input-wrap">
                            <input type="password" id="password" name="password" class="auth-input" placeholder="••••••••" required>
                            <button type="button" class="auth-pw-toggle" aria-label="Show password" onclick="
                                var i=document.getElementById('password');
                                i.type=i.type==='password'?'text':'password';
                                this.classList.toggle('is-visible');
                            ">
                                <svg class="eye-show" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                <svg class="eye-hide" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.064 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit">
                        Sign in
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </form>

                <p class="auth-switch">
                    New to CitySlot? <a href="<?= htmlspecialchars(route_url('/register')) ?>">Create a free account →</a>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
