<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * A single recipe: metadata, Markdown source and main image.
 *
 * The path (e.g. "brot/brioche") is derived solely from the directory
 * structure below the recipes directory.
 */
final readonly class Recipe
{
    /**
     * @param array<string, string> $attachments further images in the recipe directory: file name => absolute path
     */
    public function __construct(
        public string $categorySlug,
        public string $slug,
        public RecipeMetadata $meta,
        public string $markdown,
        public RecipeImage $image,
        public array $attachments = [],
    ) {
    }

    /**
     * Path relative to the site root, e.g. "brot/brioche".
     */
    public function path(): string
    {
        return $this->categorySlug . '/' . $this->slug;
    }

    /**
     * URL path relative to the site root, e.g. "/brot/brioche/".
     */
    public function urlPath(): string
    {
        return '/' . $this->path() . '/';
    }

    /**
     * Unique file name of the image in the central image folder. The double hyphen
     * cannot occur inside a directory name, so "a-b/c" and "a/b-c" never collide.
     */
    public function imageFileName(): string
    {
        return $this->categorySlug . '--' . $this->slug . '.' . $this->image->extension;
    }

    public function imageUrlPath(): string
    {
        return '/assets/images/' . $this->imageFileName();
    }

    public function imageAlt(): string
    {
        return sprintf('%s – %s', $this->meta->title, $this->meta->description);
    }
}
