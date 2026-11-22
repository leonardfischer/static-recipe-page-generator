<?php

declare(strict_types=1);

namespace Rezepte\Support;

/**
 * Builds links relative to the configured base URL.
 *
 * Example with base_url "https://example.org/rezepte":
 *   path('/brot/brioche/')     => "/rezepte/brot/brioche/"
 *   absolute('/brot/brioche/') => "https://example.org/rezepte/brot/brioche/"
 */
final readonly class UrlGenerator
{
    private string $origin;
    private string $basePath;

    public function __construct(string $baseUrl)
    {
        $parts = parse_url(rtrim($baseUrl, '/'));
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException(sprintf('Invalid base URL "%s".', $baseUrl));
        }

        $this->origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = trim($parts['path'] ?? '', '/');
        $this->basePath = $path === '' ? '/' : '/' . $path . '/';
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Path including the base path, e.g. for href attributes.
     */
    public function path(string $path = '/'): string
    {
        return $this->basePath . ltrim($path, '/');
    }

    public function absolute(string $path = '/'): string
    {
        return $this->origin . $this->path($path);
    }
}
