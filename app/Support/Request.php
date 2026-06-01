<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly array $query,
        private readonly array $request,
        private readonly array $files,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');

        return \app_request_path($uri);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $files = $this->files($key);
        return $files[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function files(string $key): array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || !array_key_exists('name', $file)) {
            return [];
        }

        return array_values(array_filter(
            $this->normalizeFiles($file),
            static fn (array $item): bool => (int) ($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));
    }

    /**
     * @param array<string, mixed> $file
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFiles(array $file): array
    {
        $name = $file['name'] ?? null;

        if (!is_array($name)) {
            return [$file];
        }

        $normalized = [];

        foreach (array_keys($name) as $index) {
            $normalized[] = [
                'name' => $file['name'][$index] ?? '',
                'type' => $file['type'][$index] ?? '',
                'tmp_name' => $file['tmp_name'][$index] ?? '',
                'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }
}
