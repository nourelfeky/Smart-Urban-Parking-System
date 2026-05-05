<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Session.php';

class HomeController
{
    public static function index(): void
    {
        Session::start();
        if (Session::get('user_id')) {
            $role = Session::get('role') ?: 'driver';
            redirect(route_url('/' . $role . '/dashboard'));
        }
        redirect(route_url('/login'));
    }
}

