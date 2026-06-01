<?php

declare(strict_types=1);

namespace App\Support;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $content,
        private readonly int $status = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function make(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers ?: ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (self::isLocalPath($location)) {
            $location = \url($location);
        }

        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->htmlWithBaseUri();
    }

    private static function isLocalPath(string $location): bool
    {
        return str_starts_with($location, '/') && !str_starts_with($location, '//');
    }

    private function htmlWithBaseUri(): string
    {
        $baseUri = \app_base_uri();
        if ($baseUri === '' || !$this->isHtmlResponse()) {
            return $this->content;
        }

        $basePath = ltrim($baseUri, '/');
        $content = preg_replace_callback(
            '#\b(href|src|action)=(["\'])/(?!/)([^"\']*)\2#i',
            static function (array $matches) use ($baseUri, $basePath): string {
                $target = $matches[3];
                if ($target === $basePath || str_starts_with($target, $basePath . '/')) {
                    return $matches[0];
                }

                return $matches[1] . '=' . $matches[2] . $baseUri . '/' . $target . $matches[2];
            },
            $this->content
        );

        return $content ?? $this->content;
    }

    private function isHtmlResponse(): bool
    {
        $contentType = $this->headers['Content-Type'] ?? '';

        return str_starts_with(strtolower($contentType), 'text/html');
    }
}
