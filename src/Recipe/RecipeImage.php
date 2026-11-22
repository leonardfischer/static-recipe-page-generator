<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * Main image of a recipe. Also defines which image formats are supported.
 */
final readonly class RecipeImage
{
    /** Allowed file name extensions, e.g. for the "image" field in meta.json. */
    public const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Detected image type => extension used in the output. */
    public const array TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function __construct(
        public string $sourcePath,
        public string $extension,
        public int $width,
        public int $height,
    ) {
    }
}
