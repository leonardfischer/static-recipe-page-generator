<?php

declare(strict_types=1);

namespace Rezepte\Search;

use Rezepte\Recipe\Recipe;
use Rezepte\Recipe\RecipeCollection;
use Rezepte\Support\UrlGenerator;

/**
 * Builds the data for assets/data/recipes.json.
 *
 * Deliberately contains only what search, filtering, sorting and the
 * result cards need – no Markdown content.
 */
final readonly class SearchIndexBuilder
{
    public function __construct(private UrlGenerator $urls)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(RecipeCollection $recipes): array
    {
        return array_map($this->entry(...), $recipes->all());
    }

    public function toJson(RecipeCollection $recipes): string
    {
        return json_encode(
            $this->build($recipes),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(Recipe $recipe): array
    {
        $meta = $recipe->meta;

        return [
            'title' => $meta->title,
            'description' => $meta->description,
            'category' => $meta->category,
            'category_slug' => $recipe->categorySlug,
            'tags' => $meta->tags,
            'url' => $this->urls->path($recipe->urlPath()),
            'image' => $this->urls->path($recipe->imageUrlPath()),
            'difficulty' => $meta->difficulty->value,
            'total_time' => $meta->totalTime(),
            'created' => $meta->created?->format('Y-m-d'),
        ];
    }
}
