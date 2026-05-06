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
        route_url('/driver/dashboard') => 'Dashboard',
        route_url('/driver/search') => 'Find Parking',
        route_url('/driver/bookings') => 'My Bookings',
        route_url('/driver/fines') => 'Fines',
        route_url('/driver/vehicles') => 'Vehicles',
        route_url('/driver/favorites') => 'Favorites',
        route_url('/driver/notifications') => 'Notifications',
    ];
} elseif ($role === 'owner') {
    $nav = [
        route_url('/owner/dashboard') => 'Dashboard',
        route_url('/owner/spots') => 'My Spots',
        route_url('/owner/earnings') => 'Earnings',
        route_url('/owner/reports') => 'Reports',
        route_url('/owner/verify') => 'Verification',
    ];
} elseif ($role === 'admin') {
    $nav = [
        route_url('/admin/dashboard') => 'Dashboard',
        route_url('/admin/spots') => 'Spots',
        route_url('/admin/fines') => 'Fines',
        route_url('/admin/appeals') => 'Appeals',
        route_url('/admin/zones') => 'Zones',
        route_url('/admin/owners') => 'Owners',
        route_url('/admin/heatmap') => 'Heatmap',
    ];
} elseif ($role === 'officer') {
    $nav = [
        route_url('/officer/dashboard') => 'Dashboard',
        route_url('/officer/violation') => 'Violations',
    ];
}
$current = $_SERVER['REQUEST_URI'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'CitySlot') ?> — CitySlot</title>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/css/style.css')) ?>">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="<?= htmlspecialchars(route_url('/')) ?>">City<span>Slot</span></a>
    <div class="navbar-links">
        <?php foreach ($nav as $href => $label): ?>
            <a href="<?= $href ?>" class="<?= str_contains($current, $href) ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <?php if ($role): ?>
            <form method="post" action="<?= htmlspecialchars(route_url('/logout')) ?>" style="display:inline">
                <button type="submit" class="btn-logout" style="background:none;border:none;padding:6px 14px;border-radius:8px;cursor:pointer">Logout</button>
            </form>
        <?php endif; ?>
    </div>
</nav>
<div class="page">
<?php
$flash_ok  = flash('ok');
$flash_err = flash('err');
if ($flash_ok)  echo '<div class="alert alert-success">' . htmlspecialchars($flash_ok)  . '</div>';
if ($flash_err) echo '<div class="alert alert-error">'   . htmlspecialchars($flash_err) . '</div>';
?>
