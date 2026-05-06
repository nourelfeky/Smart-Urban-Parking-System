<?php require __DIR__ . '/../layout/header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<h1 class="page-title">My Parking Spots</h1>

<div class="card mb-3">
    <div class="card-title">List a New Spot</div>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="latitude" id="spot_lat" value="">
        <input type="hidden" name="longitude" id="spot_lng" value="">
        <div class="form-row">
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" id="spot_address" class="form-control" placeholder="12 Street, District" required>
            </div>
            <div class="form-group">
                <label>Hourly Rate (EGP)</label>
                <input type="number" name="base_rate" class="form-control" step="0.5" min="1" required>
            </div>
        </div>

        <div class="form-group">
            <label>Pick location on map</label>
            <div id="ownerSpotMap" style="height: 340px; border-radius: 8px; border: 1px solid #e5e7eb;"></div>
            <div class="flex gap-2 mt-2" style="flex-wrap:wrap">
                <button type="button" class="btn btn-outline btn-sm" id="btnUseMyLocation">Use my current location</button>
                <button type="button" class="btn btn-outline btn-sm" id="btnFindByAddress">Find from address</button>
                <span class="text-muted" id="pickedCoords" style="align-self:center">No location selected yet.</span>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Max Vehicle Height (cm)</label>
                <input type="number" name="height" class="form-control" step="1">
            </div>
            <div class="form-group">
                <label>Max Vehicle Width (cm)</label>
                <input type="number" name="width" class="form-control" step="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Available From</label>
                <input type="time" name="avail_start" class="form-control" value="08:00">
            </div>
            <div class="form-group">
                <label>Available Until</label>
                <input type="time" name="avail_end" class="form-control" value="22:00">
            </div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="ev" id="ev">
            <label for="ev" style="text-transform:none;letter-spacing:0;font-size:14px">Has EV Charger</label>
        </div>
        <button type="submit" class="btn btn-primary">Add Spot</button>
    </form>
</div>

<?php if (empty($spots)): ?>
<div class="card"><p class="text-muted">No spots listed yet.</p></div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Address</th><th>Rate</th><th>Status</th><th>EV</th><th>Hours</th><th>Location</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($spots as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['address']) ?></td>
                <td><?= number_format($s['base_rate'],2) ?> EGP</td>
                <td>
                    <?php
                    $sc = ['available'=>'badge-green','occupied'=>'badge-amber','reserved'=>'badge-blue','maintenance'=>'badge-gray','owner_use'=>'badge-gray','locked'=>'badge-red'];
                    echo '<span class="badge ' . ($sc[$s['status']] ?? 'badge-gray') . '">' . $s['status'] . '</span>';
                    ?>
                </td>
                <td><?= $s['has_ev_charger'] ? '<span class="badge badge-green">⚡</span>' : '—' ?></td>
                <td><?= substr($s['availability_start'],0,5) ?> – <?= substr($s['availability_end'],0,5) ?></td>
                <td>
                    <?php if (!empty($s['latitude']) && !empty($s['longitude'])): ?>
                        <span class="text-muted" style="font-size:12px">
                            <?= number_format((float)$s['latitude'], 6) ?>, <?= number_format((float)$s['longitude'], 6) ?>
                        </span>
                        <div>
                            <a class="text-muted" style="font-size:12px" target="_blank"
                               href="https://maps.google.com/?q=<?= urlencode($s['latitude'] . ',' . $s['longitude']) ?>">
                                Open map
                            </a>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="flex gap-2" style="flex-wrap:wrap">
                    <?php if ($s['status'] === 'available'): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <input type="hidden" name="new_status" value="maintenance">
                        <button class="btn btn-warning btn-sm">Maintenance</button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <input type="hidden" name="new_status" value="owner_use">
                        <button class="btn btn-outline btn-sm">Owner Use</button>
                    </form>
                    <?php elseif (in_array($s['status'], ['maintenance','owner_use'])): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <input type="hidden" name="new_status" value="available">
                        <button class="btn btn-success btn-sm">Set Available</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this spot?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="spot_id" value="<?= $s['spot_id'] ?>">
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>

<script>
(() => {
  if (!window.L) return;

  const latInput = document.getElementById('spot_lat');
  const lngInput = document.getElementById('spot_lng');
  const addressInput = document.getElementById('spot_address');
  const coordsLabel = document.getElementById('pickedCoords');
  const btnMyLoc = document.getElementById('btnUseMyLocation');
  const btnFind = document.getElementById('btnFindByAddress');

  const defaultCenter = [30.0444, 31.2357]; // Cairo default
  const map = L.map('ownerSpotMap', { scrollWheelZoom: false }).setView(defaultCenter, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);

  const marker = L.marker(defaultCenter, { draggable: true });

  const setPicked = (lat, lng, zoomTo = true) => {
    const la = Number(lat), ln = Number(lng);
    if (!Number.isFinite(la) || !Number.isFinite(ln)) return;
    latInput.value = la.toFixed(7);
    lngInput.value = ln.toFixed(7);
    coordsLabel.textContent = `Picked: ${la.toFixed(6)}, ${ln.toFixed(6)}`;
    if (!map.hasLayer(marker)) marker.addTo(map);
    marker.setLatLng([la, ln]);
    if (zoomTo) map.setView([la, ln], Math.max(map.getZoom(), 15));
  };

  map.on('click', (e) => setPicked(e.latlng.lat, e.latlng.lng));
  marker.on('dragend', () => {
    const p = marker.getLatLng();
    setPicked(p.lat, p.lng, false);
  });

  // If browser geolocation is available, center map (but don't auto-pick).
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => map.setView([pos.coords.latitude, pos.coords.longitude], 14),
      () => {},
      { enableHighAccuracy: true, timeout: 3000, maximumAge: 60000 }
    );
  }

  btnMyLoc?.addEventListener('click', () => {
    if (!navigator.geolocation) return alert('Geolocation is not supported in this browser.');
    navigator.geolocation.getCurrentPosition(
      (pos) => setPicked(pos.coords.latitude, pos.coords.longitude),
      () => alert('Could not get your location. Please allow location access or click on the map.'),
      { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
    );
  });

  // Lightweight client-side geocoding (Nominatim) to help pick a point.
  btnFind?.addEventListener('click', async () => {
    const q = (addressInput?.value || '').trim();
    if (!q) return alert('Enter an address first.');
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (!Array.isArray(data) || !data[0]) return alert('Address not found. Please click on the map.');
      setPicked(Number(data[0].lat), Number(data[0].lon));
    } catch (e) {
      alert('Could not search address right now. Please click on the map.');
    }
  });
})();
</script>