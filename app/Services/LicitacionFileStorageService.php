<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class LicitacionFileStorageService
{
    private const MAX_FILE_SIZE = 20971520;

    /** @var array<int, string> */
    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'jpg',
        'jpeg',
        'png',
        'zip',
    ];

    /**
     * @param array<string, mixed> $uploadedFile
     * @return array<string, mixed>
     */
    public function store(array $uploadedFile, string $category): array
    {
        $error = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $originalName = trim((string) ($uploadedFile['name'] ?? ''));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($originalName === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Formato de archivo no permitido. Usá PDF, Word, Excel, imágenes o ZIP.');
        }

        $size = (int) ($uploadedFile['size'] ?? 0);
        if ($size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Cada archivo debe pesar como máximo 20 MB.');
        }

        $tmpPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('No se pudo validar el archivo subido.');
        }

        $directory = storage_path('uploads/licitaciones');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar la carpeta de licitaciones.');
        }

        $storedName = sprintf(
            '%s-%s%s',
            $category,
            bin2hex(random_bytes(12)),
            $extension !== '' ? '.' . $extension : ''
        );
        $destination = $directory . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo en la carpeta de licitaciones.');
        }

        $mimeType = function_exists('mime_content_type') ? mime_content_type($destination) : null;

        return [
            'category' => $category,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => is_string($mimeType) && $mimeType !== '' ? $mimeType : ((string) ($uploadedFile['type'] ?? '') ?: null),
            'size_bytes' => filesize($destination) ?: $size,
        ];
    }

    public function delete(?string $storedName): void
    {
        $storedName = trim((string) $storedName);
        if ($storedName === '') {
            return;
        }

        $path = storage_path('uploads/licitaciones/' . basename($storedName));
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function path(string $storedName): string
    {
        return storage_path('uploads/licitaciones/' . basename($storedName));
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'La carga del archivo quedó incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
            default => 'No se pudo procesar el archivo subido.',
        };
    }
}
