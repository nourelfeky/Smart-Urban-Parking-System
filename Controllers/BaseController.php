<?php

require_once __DIR__ . '/../Core/View.php';

class BaseController
{
    protected static function render(string $view, array $data = []): void
    {
        View::render($view, $data);
    }
}
