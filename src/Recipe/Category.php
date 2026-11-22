<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

final readonly class Category
{
    /**
     * @param list<Recipe> $recipes sorted alphabetically by title
     */
    public function __construct(
        public string $slug,
        public string $name,
        public array $recipes,
    ) {
    }

    public function urlPath(): string
    {
        return '/' . $this->slug . '/';
    }

    public function count(): int
    {
        return count($this->recipes);
    }
}
