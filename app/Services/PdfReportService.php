<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PdfReportService
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        $tmpDir = base_path('tmp/pdfs');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $payloadFile = tempnam($tmpDir, 'report_');
        $outputFile = tempnam($tmpDir, 'report_');

        if ($payloadFile === false || $outputFile === false) {
            throw new RuntimeException('No se pudo reservar archivos temporales para el PDF.');
        }

        file_put_contents($payloadFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $command = sprintf(
            'python3 %s %s %s 2>&1',
            escapeshellarg(base_path('tools/render_report_pdf.py')),
            escapeshellarg($payloadFile),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $status);
        @unlink($payloadFile);

        if ($status !== 0 || !is_file($outputFile)) {
            @unlink($outputFile);
            throw new RuntimeException('No se pudo generar el PDF: ' . implode("\n", $output));
        }

        $contents = file_get_contents($outputFile);
        @unlink($outputFile);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('El PDF generado está vacío.');
        }

        return $contents;
    }
}
