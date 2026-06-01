<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class ErrorHandler
{
    public static function register(bool $debug): void
    {
        set_exception_handler(static function (Throwable $exception) use ($debug): void {
            http_response_code(500);

            $logLine = sprintf(
                "[%s] %s in %s:%d\n",
                date('Y-m-d H:i:s'),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );

            file_put_contents(APP_BASE_PATH . '/storage/logs/app.log', $logLine, FILE_APPEND);

            if ($debug) {
                echo '<pre>' . e((string) $exception) . '</pre>';
                return;
            }

            echo '<h1>Error interno</h1><p>Revisar storage/logs/app.log.</p>';
        });
    }
}

