<?php

declare(strict_types=1);

namespace App\core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'public'): void
    {
        $viewFile = BASE_PATH . '/app/views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/views/layouts/' . $layout . '.php';

        if (!is_file($viewFile) || !is_file($layoutFile)) {
            Response::notFound();
            return;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        include $layoutFile;
    }
}
