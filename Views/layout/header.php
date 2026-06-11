<?php
require_once __DIR__ . '/../../Core/Auth.php';
$u    = current_user();
$role = $u['role'];
$nav  = [];
if ($role === 'driver') {
    $nav = [
        route_url('/driver/dashboard')     => ['label' => 'Dashboard',     'icon' => 'grid'],
        route_url('/driver/search')        => ['label' => 'Find Parking',  'icon' => 'search'],
        route_url('/driver/bookings')      => ['label' => 'Bookings',      'icon' => 'calendar'],
        route_url('/driver/fines')         => ['label' => 'Fines',         'icon' => 'alert'],
        route_url('/driver/vehicles')      => ['label' => 'Vehicles',      'icon' => 'car'],
        route_url('/driver/favorites')     => ['label' => 'Favorites',     'icon' => 'heart'],
        route_url('/driver/notifications') => ['label' => 'Notifications', 'icon' => 'bell'],
    ];
} elseif ($role === 'owner') {
    $nav = [
        route_url('/owner/dashboard')      => ['label' => 'Dashboard',     'icon' => 'grid'],
        route_url('/owner/spots')          => ['label' => 'My Spots',      'icon' => 'pin'],
        route_url('/owner/earnings')       => ['label' => 'Earnings',      'icon' => 'wallet'],
        route_url('/owner/reports')        => ['label' => 'Reports',       'icon' => 'chart'],
        route_url('/owner/verify')         => ['label' => 'Verification',  'icon' => 'shield'],
        route_url('/owner/notifications')  => ['label' => 'Notifications', 'icon' => 'bell'],
    ];
} elseif ($role === 'admin') {
    $nav = [
        route_url('/admin/dashboard')        => ['label' => 'Overview',    'icon' => 'grid'],
        route_url('/admin/spots')            => ['label' => 'Spots',       'icon' => 'pin'],
        route_url('/admin/fines')            => ['label' => 'Fines',       'icon' => 'alert'],
        route_url('/admin/appeals')          => ['label' => 'Appeals',     'icon' => 'scale'],
        route_url('/admin/booking-disputes') => ['label' => 'Disputes',    'icon' => 'chat'],
        route_url('/admin/zones')            => ['label' => 'Zones',       'icon' => 'map'],
        route_url('/admin/owners')           => ['label' => 'Owners',      'icon' => 'users'],
        route_url('/admin/spot-approvals')   => ['label' => 'Approvals',   'icon' => 'check'],
        route_url('/admin/heatmap')          => ['label' => 'Heatmap',     'icon' => 'fire'],
    ];
} elseif ($role === 'officer') {
    $nav = [
        route_url('/officer/dashboard')  => ['label' => 'Dashboard',   'icon' => 'grid'],
        route_url('/officer/violation')  => ['label' => 'Violations',  'icon' => 'alert'],
    ];
}
$current     = $_SERVER['REQUEST_URI'];
$userInitial = strtoupper(substr((string)($u['name'] ?: $role ?: 'U'), 0, 1));
$roleLabels  = ['driver'=>'Driver','owner'=>'Space Owner','admin'=>'Admin','officer'=>'Officer'];
$roleLabel   = $roleLabels[$role] ?? ucfirst((string)$role);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#10232A">
<title><?= htmlspecialchars($pageTitle ?? 'CitySlot') ?> — CitySlot</title>
<script>
(function(){try{var t=localStorage.getItem('cityslot-theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}else if(window.matchMedia('(prefers-color-scheme:dark)').matches){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/css/style.css')) ?>">
</head>
<body>
<?php if ($role): ?>
<div class="app-shell">

  <div class="nav-overlay" id="nav-overlay"></div>

  <!-- ══ Top Navigation Bar ══ -->
  <header class="topnav" id="topnav">
    <div class="topnav-inner">

      <div class="topnav-brand">
        <a href="<?= htmlspecialchars(route_url('/')) ?>" class="tnav-logo">
          <span class="tnav-logo-mark">CS</span>
          <span class="tnav-logo-text">City<em>Slot</em></span>
        </a>
        <span class="tnav-role-chip"><?= htmlspecialchars($roleLabel) ?></span>
      </div>

      <nav class="topnav-links" id="topnav-links">
        <?php foreach ($nav as $href => $item):
          $label  = is_array($item) ? $item['label'] : $item;
          $icon   = is_array($item) ? ($item['icon'] ?? 'grid') : 'grid';
          $active = str_contains($current, $href);
        ?>
        <a href="<?= $href ?>" class="tnav-link<?= $active ? ' active' : '' ?>" data-icon="<?= htmlspecialchars($icon) ?>">
          <span class="tnav-link-icon" aria-hidden="true"></span>
          <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
      </nav>

      <div class="topnav-actions">
        <button type="button" class="tnav-icon-btn" id="topbar-theme-toggle" aria-label="Toggle theme">
          <span class="icon-sun" aria-hidden="true"></span>
          <span class="icon-moon" aria-hidden="true"></span>
        </button>
        <div class="tnav-user">
          <span class="tnav-user-avatar"><?= htmlspecialchars($userInitial) ?></span>
          <span class="tnav-user-name"><?= htmlspecialchars($u['name'] ?: $roleLabel) ?></span>
        </div>
        <form method="post" action="<?= htmlspecialchars(route_url('/logout')) ?>" style="margin:0">
          <button type="submit" class="tnav-icon-btn tnav-logout-btn" title="Sign out" aria-label="Sign out">
            <span class="tnav-link-icon icon-logout" aria-hidden="true"></span>
          </button>
        </form>
        <button type="button" class="tnav-hamburger" id="nav-toggle" aria-label="Open menu">
          <span></span><span></span><span></span>
        </button>
      </div>

    </div>
  </header>

  <!-- ══ Page wrapper ══ -->
  <div class="page-wrapper">

    <!-- Bold title band with particle canvas + live clock -->
    <div class="page-band">
      <canvas class="band-canvas" id="band-canvas" aria-hidden="true"></canvas>
      <div class="page-band-inner">
        <div class="page-band-text">
          <p class="page-band-eyebrow"><?= htmlspecialchars($roleLabel) ?></p>
          <h1 class="page-band-title"><?= htmlspecialchars($pageTitle ?? 'CitySlot') ?></h1>
        </div>
        <div class="page-band-clock" id="page-band-clock" aria-live="polite">
          <span class="band-clock-time" id="band-clock-time">--:--</span>
          <span class="band-clock-date" id="band-clock-date">--- --, ----</span>
        </div>
      </div>
    </div>

    <!-- Role-aware floating action button -->
    <?php
    $fabMap = [
        'driver'  => ['href' => route_url('/driver/search'),   'label' => 'Find Parking',  'icon' => 'search'],
        'owner'   => ['href' => route_url('/owner/spots'),     'label' => 'Add New Spot',  'icon' => 'plus'],
        'admin'   => ['href' => route_url('/admin/fines'),     'label' => 'Issue Fine',    'icon' => 'alert'],
        'officer' => ['href' => route_url('/officer/violation'),'label'=> 'Log Violation', 'icon' => 'alert'],
    ];
    $fab = $fabMap[$role] ?? null;
    if ($fab):
    ?>
    <a href="<?= htmlspecialchars($fab['href']) ?>" class="fab" id="page-fab" aria-label="<?= htmlspecialchars($fab['label']) ?>">
      <span class="fab-icon fab-icon-<?= htmlspecialchars($fab['icon']) ?>" aria-hidden="true"></span>
      <span class="fab-label"><?= htmlspecialchars($fab['label']) ?></span>
    </a>
    <?php endif; ?>

    <main class="page-content">
      <?php
      $flash_ok  = flash('ok');
      $flash_err = flash('err');
      if ($flash_ok)  echo '<div class="alert alert-success">' . htmlspecialchars($flash_ok)  . '</div>';
      if ($flash_err) echo '<div class="alert alert-error">'   . htmlspecialchars($flash_err) . '</div>';
      ?>
<?php else: ?>
<main class="auth-body">
<?php endif; ?>
