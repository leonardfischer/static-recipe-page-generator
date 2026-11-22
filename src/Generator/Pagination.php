<?php

declare(strict_types=1);

namespace Rezepte\Generator;

/**
 * Pagination of a list; page 1 lives at the base path,
 * further pages at "<base>seite-<n>/" (German URL segment).
 */
final readonly class Pagination
{
    public const string SEGMENT_PREFIX = 'seite-';

    public function __construct(
        public int $current,
        public int $total,
        public string $basePath,
    ) {
    }

    /**
     * Whether a URL segment (e.g. a recipe directory name) looks like a page segment such as "seite-2".
     */
    public static function isPageSegment(string $segment): bool
    {
        return preg_match('/^' . preg_quote(self::SEGMENT_PREFIX, '/') . '\d+$/', $segment) === 1;
    }

    public static function pageCount(int $items, int $perPage): int
    {
        return max(1, (int) ceil($items / $perPage));
    }

    public function urlPath(int $page): string
    {
        return $page <= 1 ? $this->basePath : $this->basePath . self::SEGMENT_PREFIX . $page . '/';
    }

    public function hasPrevious(): bool
    {
        return $this->current > 1;
    }

    public function hasNext(): bool
    {
        return $this->current < $this->total;
    }

    /**
     * @return list<int>
     */
    public function pages(): array
    {
        return range(1, $this->total);
    }
}
