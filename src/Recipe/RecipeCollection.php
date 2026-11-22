<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

use function Symfony\Component\String\u;

/**
 * Immutable, alphabetically sorted set of all loaded recipes.
 *
 * @implements \IteratorAggregate<int, Recipe>
 */
final readonly class RecipeCollection implements \IteratorAggregate, \Countable
{
    /** @var list<Recipe> */
    private array $recipes;

    /** @var array<string, Category> slug => category, sorted by display name */
    private array $categories;

    /**
     * @param iterable<Recipe> $recipes
     */
    public function __construct(iterable $recipes)
    {
        $list = [...$recipes];
        usort($list, static fn (Recipe $a, Recipe $b): int => self::sortKey($a) <=> self::sortKey($b));
        $this->recipes = $list;
        $this->categories = self::groupByCategory($list);
    }

    /**
     * @return list<Recipe>
     */
    public function all(): array
    {
        return $this->recipes;
    }

    /**
     * Categories in alphabetical order of their display name.
     *
     * @return list<Category>
     */
    public function categories(): array
    {
        return array_values($this->categories);
    }

    public function category(string $slug): Category
    {
        return $this->categories[$slug]
            ?? throw new \OutOfBoundsException(sprintf('Unknown category "%s".', $slug));
    }

    /**
     * Newest recipes by creation date; recipes without a "created" date are left out.
     *
     * @return list<Recipe>
     */
    public function newest(int $limit): array
    {
        $list = array_values(array_filter($this->recipes, static fn (Recipe $recipe): bool => $recipe->meta->created !== null));
        usort($list, static fn (Recipe $a, Recipe $b): int => $b->meta->created <=> $a->meta->created);

        return array_slice($list, 0, $limit);
    }

    /**
     * @return \ArrayIterator<int, Recipe>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->recipes);
    }

    public function count(): int
    {
        return count($this->recipes);
    }

    /**
     * @param list<Recipe> $recipes
     *
     * @return array<string, Category>
     */
    private static function groupByCategory(array $recipes): array
    {
        $grouped = [];
        foreach ($recipes as $recipe) {
            $grouped[$recipe->categorySlug][] = $recipe;
        }

        $categories = [];
        foreach ($grouped as $slug => $members) {
            $categories[(string) $slug] = new Category((string) $slug, $members[0]->meta->category, $members);
        }
        uasort(
            $categories,
            static fn (Category $a, Category $b): int => self::normalize($a->name) <=> self::normalize($b->name),
        );

        return $categories;
    }

    private static function sortKey(Recipe $recipe): string
    {
        return self::normalize($recipe->meta->title) . '|' . $recipe->path();
    }

    /**
     * Sort key that ignores case and accents ("Käsekuchen" sorts like "kasekuchen",
     * "Crème brûlée" like "creme brulee"); works without ext-intl.
     */
    private static function normalize(string $value): string
    {
        return u($value)->ascii()->lower()->toString();
    }
}
