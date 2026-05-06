<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="flex items-center justify-between mb-3">
    <h1 class="page-title" style="margin-bottom:0">Monthly Reports</h1>
    <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars(route_url('/owner/report-pdf?month=' . urlencode($month))) ?>">
        Download PDF
    </a>
</div>

<div class="card mb-3">
    <div class="card-title">Select Month</div>
    <form method="get" action="<?= htmlspecialchars(route_url('/owner/reports')) ?>" class="flex gap-2" style="flex-wrap:wrap;align-items:end">
        <div>
            <label class="text-muted" style="display:block;margin-bottom:6px">Month (YYYY-MM)</label>
            <input name="month" value="<?= htmlspecialchars($month) ?>" class="form-control" style="width:160px" />
        </div>
        <div>
            <button class="btn btn-primary">View</button>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Spots</div>
        <div class="value"><?= (int)$metrics['spot_count'] ?></div>
        <div class="sub">owned by you</div>
    </div>
    <div class="stat-card">
        <div class="label">Occupancy Rate</div>
        <div class="value"><?= number_format((float)$metrics['occupancy_rate'], 2) ?>%</div>
        <div class="sub">booked minutes / month minutes</div>
    </div>
    <div class="stat-card">
        <div class="label">Booked Minutes</div>
        <div class="value"><?= (int)$metrics['booked_minutes'] ?></div>
        <div class="sub">in <?= htmlspecialchars($month) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-title">Top-performing time slots</div>
    <?php if (empty($metrics['top_slots'])): ?>
        <p class="text-muted">No bookings found for this month.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table style="max-width:520px">
                <thead><tr><th>Start hour</th><th>Sessions</th></tr></thead>
                <tbody>
                <?php foreach ($metrics['top_slots'] as $row): ?>
                    <tr>
                        <td><?= str_pad((string)(int)$row['hour'], 2, '0', STR_PAD_LEFT) ?>:00</td>
                        <td><strong><?= (int)$row['sessions'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-title">Analytics (Graphs)</div>
    <p class="text-muted" style="margin-top:-6px">Sessions and occupancy for <?= htmlspecialchars($month) ?>.</p>

    <div class="grid" style="display:grid;grid-template-columns:1fr;gap:16px">
        <div class="card" style="margin:0">
            <div class="card-title" style="margin-bottom:10px">Sessions per day</div>
            <canvas id="dailySessionsChart" height="110"></canvas>
        </div>
        <div class="card" style="margin:0">
            <div class="card-title" style="margin-bottom:10px">Sessions by start hour</div>
            <canvas id="hourlySessionsChart" height="110"></canvas>
        </div>
        <div class="card" style="margin:0">
            <div class="card-title" style="margin-bottom:10px">Occupancy</div>
            <canvas id="occupancyChart" height="110"></canvas>
        </div>
    </div>

    <noscript>
        <p class="text-muted">Enable JavaScript to see the graphs.</p>
    </noscript>
</div>

<div class="mt-3">
    <a href="<?= htmlspecialchars(route_url('/owner/dashboard')) ?>" class="btn btn-outline">← Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  if (!window.Chart) return;

  const daily = <?= json_encode($daily ?? [], JSON_UNESCAPED_SLASHES) ?>;
  const hourly = <?= json_encode($hourly ?? [], JSON_UNESCAPED_SLASHES) ?>;

  const labelsDaily = daily.map(r => (r.date || '').slice(8));
  const dailySessions = daily.map(r => Number(r.sessions || 0));
  const dailyGross = daily.map(r => Number(r.gross || 0));

  const labelsHourly = hourly.map(r => String(r.hour).padStart(2, '0') + ':00');
  const hourlySessions = hourly.map(r => Number(r.sessions || 0));

  const booked = Number(<?= json_encode((int)($metrics['booked_minutes'] ?? 0)) ?>);
  const available = Number(<?= json_encode((int)($metrics['available_minutes'] ?? 0)) ?>);
  const free = Math.max(0, available - booked);

  const gridColor = 'rgba(148,163,184,0.25)';
  const textColor = '#334155';

  Chart.defaults.color = textColor;
  Chart.defaults.font.family = 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';

  const dailyCtx = document.getElementById('dailySessionsChart');
  if (dailyCtx) {
    new Chart(dailyCtx, {
      type: 'line',
      data: {
        labels: labelsDaily,
        datasets: [
          {
            label: 'Sessions',
            data: dailySessions,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 2
          },
          {
            label: 'Gross (EGP)',
            data: dailyGross,
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.10)',
            tension: 0.35,
            fill: false,
            pointRadius: 2,
            yAxisID: 'y2'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { color: gridColor } },
          y: { beginAtZero: true, grid: { color: gridColor }, title: { display: true, text: 'Sessions' } },
          y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'EGP' } }
        }
      }
    });
  }

  const hourlyCtx = document.getElementById('hourlySessionsChart');
  if (hourlyCtx) {
    new Chart(hourlyCtx, {
      type: 'bar',
      data: {
        labels: labelsHourly,
        datasets: [
          {
            label: 'Sessions',
            data: hourlySessions,
            backgroundColor: 'rgba(99,102,241,0.35)',
            borderColor: 'rgba(99,102,241,0.85)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: gridColor } }
        }
      }
    });
  }

  const occCtx = document.getElementById('occupancyChart');
  if (occCtx) {
    new Chart(occCtx, {
      type: 'doughnut',
      data: {
        labels: ['Booked minutes', 'Free minutes'],
        datasets: [
          {
            data: [booked, free],
            backgroundColor: ['rgba(245,158,11,0.70)', 'rgba(148,163,184,0.40)'],
            borderColor: ['rgba(245,158,11,1)', 'rgba(148,163,184,0.7)'],
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.label}: ${Number(ctx.raw || 0).toLocaleString()}`
            }
          }
        }
      }
    });
  }
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

