<?php

class View
{
    public static function render(string $view, array $params = []): void
    {
        extract($params, EXTR_SKIP);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new RuntimeException('View not found: ' . $viewFile);
        }
        require $viewFile;
    }
}
