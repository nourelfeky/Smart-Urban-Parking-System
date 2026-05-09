<?php

/**
 * Optional: run on a schedule (e.g. Windows Task Scheduler) so no-shows are marked
 * even when no one opens the site.
 *
 * C:\xampp\php\php.exe C:\xampp\htdocs\Smart-Urban-Parking-System\cron_no_show.php
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Models/ReservationTimeService.php';

$n = ReservationTimeService::syncNoShowStatuses(Database::getConnection());
echo "no_show updates: {$n}\n";
