<?php

require_once __DIR__ . '/Session.php';

class Auth
{
    public static function isLoggedIn(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect(route_url('/login'));
        }
        $last = Session::get('last_activity');
        if ($last && (time() - $last > 1800)) {
            Session::destroy();
            redirect(route_url('/login'));
        }
        Session::set('last_activity', time());
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (Session::get('role') !== $role) {
            redirect(route_url('/login'));
        }
    }

    public static function currentUser(): array
    {
        return [
            'id' => Session::get('user_id') ?? 0,
            'name' => Session::get('name') ?? '',
            'role' => Session::get('role') ?? '',
            'email' => Session::get('email') ?? '',
        ];
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    public static function flash(string $key, string $msg = '')
    {
        $flash = Session::get('flash') ?: [];
        if ($msg === '') {
            $val = $flash[$key] ?? '';
            unset($flash[$key]);
            Session::set('flash', $flash);
            return $val;
        }
        $flash[$key] = $msg;
        Session::set('flash', $flash);
        return null;
    }
}

function is_logged_in(): bool
{
    return Auth::isLoggedIn();
}

function require_login(): void
{
    Auth::requireLogin();
}

function require_role(string $role): void
{
    Auth::requireRole($role);
}

function current_user(): array
{
    return Auth::currentUser();
}

function redirect(string $url): void
{
    Auth::redirect($url);
}

function flash(string $key, string $msg = '')
{
    return Auth::flash($key, $msg);
}
