<?php
require_once __DIR__ . '/../../Core/Auth.php';
$u    = current_user();
$role = $u['role'] ?? '';
?>
<?php if ($role): ?>
        </main>
        <footer class="site-footer">
            <div class="footer-inner">
                <span class="footer-brand">City<span>Slot</span></span>
                <span class="footer-copy">&copy; <?= date('Y') ?> Smart Urban Parking System</span>
            </div>
        </footer>
    </div><!-- .page-wrapper -->
</div><!-- .app-shell -->
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= htmlspecialchars(asset_url('/js/ui.js')) ?>" defer></script>
</body>
</html>
