<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/PromotionalCodeValidator.php';
require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController
{
    public static function showLogin(): void
    {
        Session::start();
        $alreadyLoggedIn = (bool)Session::get('user_id');
        $loggedInName = (string)Session::get('name', '');
        $loggedInRole = (string)Session::get('role', '');
        self::render('auth/login', [
            'alreadyLoggedIn' => $alreadyLoggedIn,
            'loggedInName' => $loggedInName,
            'loggedInRole' => $loggedInRole,
            'error' => '',
            'postedEmail' => '',
            'pageTitle' => 'Login',
        ]);
    }

    public static function handleLogin(array $postData): void
    {
        Session::start();
        if (Session::get('user_id')) {
            redirect(route_url('/'));
        }
        $result = self::login($postData);
        if ($result['success']) {
            redirect($result['redirect']);
        }
        self::render('auth/login', [
            'alreadyLoggedIn' => false,
            'loggedInName' => '',
            'loggedInRole' => '',
            'error' => $result['error'] ?? 'Login failed.',
            'postedEmail' => $postData['email'] ?? '',
            'pageTitle' => 'Login',
        ]);
    }

    public static function showRegister(): void
    {
        Session::start();
        $alreadyLoggedIn = (bool)Session::get('user_id');
        self::render('auth/register', [
            'alreadyLoggedIn' => $alreadyLoggedIn,
            'error' => '',
            'postedName' => '',
            'postedEmail' => '',
            'postedRole' => 'driver',
            'pageTitle' => 'Register',
        ]);
    }

    public static function handleRegister(array $postData): void
    {
        Session::start();
        if (Session::get('user_id')) {
            redirect(route_url('/'));
        }
        $result = self::register($postData);
        if ($result['success']) {
            redirect($result['redirect']);
        }
        self::render('auth/register', [
            'alreadyLoggedIn' => false,
            'error' => $result['error'] ?? 'Registration failed.',
            'postedName' => $postData['name'] ?? '',
            'postedEmail' => $postData['email'] ?? '',
            'postedRole' => $postData['role'] ?? 'driver',
            'pageTitle' => 'Register',
        ]);
    }

    public static function login(array $postData): array
    {
        Session::start();
        $email = trim($postData['email'] ?? '');
        $password = $postData['password'] ?? '';

        $user = User::findByEmail($email);
        if ($user && password_verify($password, $user->passwordHash)) {
            Session::set('user_id', $user->id);
            Session::set('name', $user->name);
            Session::set('email', $user->email);
            Session::set('role', $user->role);
            Session::set('last_activity', time());
            session_regenerate_id(true);
            return ['success' => true, 'redirect' => route_url('/' . $user->role . '/dashboard')];
        }

        return ['success' => false, 'error' => 'Wrong email or password.'];
    }

    public static function register(array $postData): array
    {
        Session::start();
        $name = trim($postData['name'] ?? '');
        $email = trim($postData['email'] ?? '');
        $password = $postData['password'] ?? '';
        $role = $postData['role'] ?? 'driver';

        if (!in_array($role, ['driver', 'owner', 'officer'], true)) {
            $role = 'driver';
        }

        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            return ['success' => false, 'error' => 'Please fill all fields correctly (password min 6 chars).'];
        }

        if (User::emailExists($email)) {
            return ['success' => false, 'error' => 'Email already registered.'];
        }

        $userId = User::create($name, $email, $password, $role);
        $pdo = Database::getConnection();

        try {
            if ($role === 'driver') {
                $pdo->prepare('INSERT INTO drivers (driver_id) VALUES (?)')->execute([$userId]);
                $pdo->prepare('INSERT INTO loyalty_accounts (driver_id) VALUES (?)')->execute([$userId]);
                PromotionalCodeValidator::ensureDefaultPromotionalCodes($pdo);
                PromotionalCodeValidator::notifyNewDriverWelcomePromo($pdo, (int)$userId);
            } elseif ($role === 'owner') {
                $pdo->prepare('INSERT INTO space_owners (owner_id) VALUES (?)')->execute([$userId]);
            }
        } catch (Exception $e) {
            // If sub-table creation fails, we might want to log it or handle it.
            // For now, we continue to allow the session to be set so the user isn't stuck.
        }

        Session::set('user_id', $userId);
        Session::set('name', $name);
        Session::set('email', $email);
        Session::set('role', $role);

        return ['success' => true, 'redirect' => route_url('/' . $role . '/dashboard')];
    }

    public static function logout(): void
    {
        Session::destroy();
        redirect(route_url('/login'));
    }
}
