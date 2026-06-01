<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $templateFile = APP_BASE_PATH . '/app/Views/' . $template . '.php';
        $layoutFile = APP_BASE_PATH . '/app/Views/' . $layout . '.php';

        if (!is_file($templateFile) || !is_file($layoutFile)) {
            throw new RuntimeException('Vista no encontrada: ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        ob_start();
        require $layoutFile;

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function partial(string $template, array $data = []): string
    {
        $templateFile = APP_BASE_PATH . '/app/Views/' . $template . '.php';

        if (!is_file($templateFile)) {
            throw new RuntimeException('Parcial no encontrado: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;

        return (string) ob_get_clean();
    }
}

