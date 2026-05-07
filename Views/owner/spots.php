<?php require __DIR__ . '/../layout/header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<h1 class="page-title">My Parking Spots</h1>

<div class="alert alert-info mb-3">
    <strong>New requirement:</strong> after you create a spot, upload <strong>lease/ownership proof</strong> and a <strong>spot photo</strong>.
    An <strong>admin must approve</strong> the listing before drivers can search or book it.
</div>

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
            <thead><tr><th>Address</th><th>Rate</th><th>Listing approval</th><th>Operational</th><th>EV</th><th>Hours</th><th>Location</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            /** @var array<int,array<string,mixed>> $latestSub */
            $latestSub = $latestSub ?? [];
            ?>
            <?php foreach ($spots as $s): ?>
            <?php
                $ap = $s['spot_approval_status'] ?? 'approved';
                $apBadge = ['pending_documents'=>'badge-amber','pending_review'=>'badge-blue','approved'=>'badge-green','rejected'=>'badge-red'];
                $apLabel = ['pending_documents'=>'Docs required','pending_review'=>'Awaiting admin','approved'=>'Approved','rejected'=>'Rejected'];
                $op = ['available'=>'badge-green','occupied'=>'badge-amber','reserved'=>'badge-blue','maintenance'=>'badge-gray','owner_use'=>'badge-gray','locked'=>'badge-red'];
            ?>
            <tr>
                <td><?= htmlspecialchars($s['address']) ?></td>
                <td><?= number_format($s['base_rate'],2) ?> EGP</td>
                <td>
                    <span class="badge <?= $apBadge[$ap] ?? 'badge-gray' ?>"><?= htmlspecialchars($apLabel[$ap] ?? $ap) ?></span>
                    <?php if ($ap === 'rejected' && !empty($latestSub[(int)$s['spot_id']]['admin_note'])): ?>
                        <div class="text-muted" style="font-size:12px;margin-top:4px">Note: <?= htmlspecialchars((string)$latestSub[(int)$s['spot_id']]['admin_note']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $op[$s['status']] ?? 'badge-gray' ?>"><?= htmlspecialchars($s['status']) ?></span>
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
                    <?php if ($ap !== 'approved'): ?>
                        <span class="text-muted" style="font-size:13px">Availability controls unlock after approval.</span>
                    <?php elseif ($s['status'] === 'available'): ?>
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
                    <?php elseif (in_array($s['status'], ['maintenance','owner_use','reserved'])): ?>
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
            <?php if (in_array($ap, ['pending_documents', 'rejected'], true)): ?>
            <tr>
                <td colspan="8" style="background:var(--gray-50);padding:16px 14px;">
                    <form method="post" enctype="multipart/form-data" class="flex gap-3" style="flex-wrap:wrap;align-items:flex-end">
                        <input type="hidden" name="action" value="submit_spot_documents">
                        <input type="hidden" name="spot_id" value="<?= (int)$s['spot_id'] ?>">
                        <div class="form-group" style="margin-bottom:0">
                            <label style="display:block;margin-bottom:4px;font-size:12px;font-weight:600">Lease / ownership proof</label>
                            <input type="file" name="lease_or_ownership" accept=".jpg,.jpeg,.png,.pdf" class="form-control" required style="padding:8px">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label style="display:block;margin-bottom:4px;font-size:12px;font-weight:600">Spot photo</label>
                            <input type="file" name="spot_photo" accept=".jpg,.jpeg,.png,.pdf" class="form-control" required style="padding:8px">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Submit for review</button>
                    </form>
                </td>
            </tr>
            <?php endif; ?>
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