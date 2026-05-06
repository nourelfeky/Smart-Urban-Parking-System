<?php

declare(strict_types=1);

require_once __DIR__ . '/Core/Session.php';
Session::start();

require_once __DIR__ . '/Core/View.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Core/Router.php';


function base_url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

function route_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    $front = base_url('/index.php');
    return $front . ($path === '/' ? '' : $path);
}

function asset_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return base_url('/assets' . $path);
}

spl_autoload_register(function (string $class): void {
    $candidates = [
        __DIR__ . '/Controllers/' . $class . '.php',
        __DIR__ . '/Models/' . $class . '.php',
        __DIR__ . '/Core/' . $class . '.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

