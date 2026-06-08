<?php
/**
 * Shared layout header for MVC views.
 * Expects $pageTitle to be set before inclusion.
 */
require_once __DIR__ . '/../../Core/Auth.php';
$u = current_user();
$role = $u['role'];
$nav = [];
if ($role === 'driver') {
    $nav = [
        route_url('/driver/dashboard') => ['label' => 'Dashboard', 'icon' => 'grid'],
        route_url('/driver/search') => ['label' => 'Find Parking', 'icon' => 'search'],
        route_url('/driver/bookings') => ['label' => 'My Bookings', 'icon' => 'calendar'],
        route_url('/driver/fines') => ['label' => 'Fines', 'icon' => 'alert'],
        route_url('/driver/vehicles') => ['label' => 'Vehicles', 'icon' => 'car'],
        route_url('/driver/favorites') => ['label' => 'Favorites', 'icon' => 'heart'],
        route_url('/driver/notifications') => ['label' => 'Notifications', 'icon' => 'bell'],
    ];
} elseif ($role === 'owner') {
    $nav = [
        route_url('/owner/dashboard') => ['label' => 'Dashboard', 'icon' => 'grid'],
        route_url('/owner/spots') => ['label' => 'My Spots', 'icon' => 'pin'],
        route_url('/owner/earnings') => ['label' => 'Earnings', 'icon' => 'wallet'],
        route_url('/owner/reports') => ['label' => 'Reports', 'icon' => 'chart'],
        route_url('/owner/verify') => ['label' => 'Verification', 'icon' => 'shield'],
        route_url('/owner/notifications') => ['label' => 'Notifications', 'icon' => 'bell'],
    ];
} elseif ($role === 'admin') {
    $nav = [
        route_url('/admin/dashboard') => ['label' => 'Dashboard', 'icon' => 'grid'],
        route_url('/admin/spots') => ['label' => 'Spots', 'icon' => 'pin'],
        route_url('/admin/fines') => ['label' => 'Fines', 'icon' => 'alert'],
        route_url('/admin/appeals') => ['label' => 'Appeals', 'icon' => 'scale'],
        route_url('/admin/booking-disputes') => ['label' => 'Disputes', 'icon' => 'chat'],
        route_url('/admin/notifications') => ['label' => 'Notifications', 'icon' => 'bell'],
        route_url('/admin/zones') => ['label' => 'Zones', 'icon' => 'map'],
        route_url('/admin/owners') => ['label' => 'Owners', 'icon' => 'users'],
        route_url('/admin/spot-approvals') => ['label' => 'Approvals', 'icon' => 'check'],
        route_url('/admin/heatmap') => ['label' => 'Heatmap', 'icon' => 'fire'],
    ];
} elseif ($role === 'officer') {
    $nav = [
        route_url('/officer/dashboard') => ['label' => 'Dashboard', 'icon' => 'grid'],
        route_url('/officer/violation') => ['label' => 'Violations', 'icon' => 'alert'],
    ];
}
$current = $_SERVER['REQUEST_URI'];
$userInitial = strtoupper(substr((string)($u['name'] ?: $role ?: 'U'), 0, 1));
$roleLabels = ['driver' => 'Driver', 'owner' => 'Space Owner', 'admin' => 'Administrator', 'officer' => 'Officer'];
$roleLabel = $roleLabels[$role] ?? ucfirst($role);
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
    <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <div class="sidebar-header">
            <a class="sidebar-brand" href="<?= htmlspecialchars(route_url('/')) ?>">
                <span class="brand-mark">CS</span>
                <span class="brand-text">City<span>Slot</span></span>
            </a>
            <span class="sidebar-role-badge"><?= htmlspecialchars($roleLabel) ?></span>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($nav as $href => $item):
                $label = is_array($item) ? $item['label'] : $item;
                $icon = is_array($item) ? ($item['icon'] ?? 'grid') : 'grid';
                $active = str_contains($current, $href);
            ?>
            <a href="<?= $href ?>" class="sidebar-link<?= $active ? ' active' : '' ?>" data-icon="<?= htmlspecialchars($icon) ?>">
                <span class="sidebar-link-icon" aria-hidden="true"></span>
                <span class="sidebar-link-text"><?= htmlspecialchars($label) ?></span>
                <?php if ($active): ?><span class="sidebar-link-indicator"></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                <span class="theme-toggle-track">
                    <span class="theme-toggle-thumb"></span>
                </span>
                <span class="theme-toggle-label">Dark mode</span>
            </button>
            <form method="post" action="<?= htmlspecialchars(route_url('/logout')) ?>" class="sidebar-logout-form">
                <button type="submit" class="sidebar-logout">
                    <span class="sidebar-link-icon icon-logout" aria-hidden="true"></span>
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </aside>
    <div class="app-main">
        <header class="topbar">
            <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-center">
                <p class="topbar-eyebrow"><?= htmlspecialchars($roleLabel) ?></p>
                <h1 class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'CitySlot') ?></h1>
            </div>
            <div class="topbar-actions">
                <button type="button" class="topbar-theme-btn" id="topbar-theme-toggle" aria-label="Toggle theme">
                    <span class="icon-sun" aria-hidden="true"></span>
                    <span class="icon-moon" aria-hidden="true"></span>
                </button>
                <div class="user-chip">
                    <span class="user-avatar"><?= htmlspecialchars($userInitial) ?></span>
                    <span class="user-name"><?= htmlspecialchars($u['name'] ?: $roleLabel) ?></span>
                </div>
            </div>
        </header>
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
