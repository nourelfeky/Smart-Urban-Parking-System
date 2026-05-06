<?php require __DIR__ . '/../layout/header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<h1 class="page-title">Find Parking</h1>

<div class="card mb-3">
    <form method="get" class="flex gap-3" style="flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
            <label>Search by address</label>
            <input type="text" name="q" class="form-control" placeholder="e.g. Tahrir" value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Max vehicle height (cm)</label>
            <input type="number" name="max_h" class="form-control" style="width:140px" value="<?= htmlspecialchars($max_h) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Max vehicle width (cm)</label>
            <input type="number" name="max_w" class="form-control" style="width:140px" value="<?= htmlspecialchars($max_w) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;display:flex;align-items:center;gap:6px;padding-top:22px">
            <input type="checkbox" name="ev_only" id="ev" <?= $ev_only ? 'checked' : '' ?>>
            <label for="ev" style="text-transform:none;letter-spacing:0;font-size:14px;font-weight:500">EV charger only</label>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-bottom:0;align-self:flex-end">Search</button>
        <?php if ($q || $ev_only || $max_h || $max_w): ?>
            <a href="<?= htmlspecialchars(route_url('/driver/search')) ?>" class="btn btn-outline" style="align-self:flex-end">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($spots)): ?>
    <div class="card"><p class="text-muted">No available spots found. Try different filters.</p></div>
<?php else: ?>
    <p class="text-muted mb-3"><?= count($spots) ?> spot<?= count($spots) !== 1 ? 's' : '' ?> found</p>
    <div id="map" style="height: 400px; margin-bottom: 20px;"></div>
    <div class="spot-grid">
        <?php foreach ($spots as $s):
            $rate = $s['base_rate'] * ($s['default_multiplier'] ?? 1.0);
        ?>
        <div class="spot-card">
            <div class="spot-addr"><?= htmlspecialchars($s['address']) ?></div>
            <div class="spot-rate"><?= number_format($rate, 2) ?> EGP/hr</div>
            <div class="spot-meta">
                <?php if ($s['has_ev_charger']): ?>
                    <span class="badge badge-green">EV ⚡</span>
                <?php endif; ?>
                <?php if ($s['height_cm']): ?>
                    <span>H: <?= $s['height_cm'] ?>cm</span>
                <?php endif; ?>
                <?php if ($s['width_cm']): ?>
                    <span>W: <?= $s['width_cm'] ?>cm</span>
                <?php endif; ?>
                <?php if ($s['avg_rating'] > 0): ?>
                    <span>★ <?= number_format($s['avg_rating'], 1) ?> (<?= $s['rating_count'] ?>)</span>
                <?php endif; ?>
                <?php if ($s['difficulty_label']): ?>
                    <span class="badge badge-amber"><?= htmlspecialchars($s['difficulty_label']) ?></span>
                <?php endif; ?>
            </div>
            <a href="<?= htmlspecialchars(route_url('/driver/book?spot=' . $s['spot_id'])) ?>" class="btn btn-primary btn-block">Book Now</a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
var map = L.map('map').setView([30.0444, 31.2357], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

var bounds = [];
<?php foreach ($spots as $s): ?>
<?php if (!empty($s['latitude']) && !empty($s['longitude'])): ?>
(function () {
  var lat = <?= (float)$s['latitude'] ?>;
  var lng = <?= (float)$s['longitude'] ?>;
  bounds.push([lat, lng]);
  L.marker([lat, lng]).addTo(map)
    .bindPopup('<strong><?= addslashes(htmlspecialchars($s["address"])) ?></strong><br><?= number_format($s["base_rate"] * ($s["default_multiplier"] ?? 1.0), 2) ?> EGP/hr<br><a href="<?= addslashes(htmlspecialchars(route_url("/driver/book?spot=" . $s["spot_id"]))) ?>">Book Now</a>');
})();
<?php endif; ?>
<?php endforeach; ?>

if (bounds.length > 0) {
  map.fitBounds(bounds, { padding: [20, 20] });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>