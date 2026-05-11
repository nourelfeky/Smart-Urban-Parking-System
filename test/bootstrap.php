<?php

declare(strict_types=1);

error_reporting(E_ALL);

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Africa/Cairo');
}
date_default_timezone_set(APP_TIMEZONE);

if (!defined('PEAK_MULTIPLIER')) {
    define('PEAK_MULTIPLIER', 1.25);
}
if (!defined('PEAK_MULTIPLIER_MIN')) {
    define('PEAK_MULTIPLIER_MIN', 1.0);
}
if (!defined('PEAK_MULTIPLIER_MAX')) {
    define('PEAK_MULTIPLIER_MAX', 3.0);
}
if (!defined('PEAK_HOURS')) {
    define('PEAK_HOURS', [
        ['start' => '08:00', 'end' => '10:00'],
        ['start' => '17:00', 'end' => '20:00'],
    ]);
}
if (!defined('SPECIAL_EVENT_WINDOWS')) {
    define('SPECIAL_EVENT_WINDOWS', []);
}
if (!defined('PENALTY_RATE_PER_MINUTE')) {
    define('PENALTY_RATE_PER_MINUTE', 0.5);
}

require_once __DIR__ . '/../Core/AppClock.php';
require_once __DIR__ . '/../Models/PeakPricingConfig.php';
require_once __DIR__ . '/../Models/PricingModel.php';
require_once __DIR__ . '/../Models/PricingEngine.php';
require_once __DIR__ . '/../Models/PenaltyModel.php';
require_once __DIR__ . '/../Models/ReservationTimeService.php';
require_once __DIR__ . '/../Models/LoyaltyProgram.php';
require_once __DIR__ . '/../Models/FineIssueValidator.php';
require_once __DIR__ . '/../Models/DriverSanctionPolicy.php';
require_once __DIR__ . '/Support/CsvFixtureReader.php';
