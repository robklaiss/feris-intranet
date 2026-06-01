<?php

declare(strict_types=1);

namespace App\Services;

final class ExportService
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $headers
     */
    public function toCsv(array $rows, array $headers): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $contents = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $contents;
    }
}

