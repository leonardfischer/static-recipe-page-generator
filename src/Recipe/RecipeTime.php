<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * One time segment of a recipe, e.g. "Bulk fermentation, 90 minutes (resting)".
 */
final readonly class RecipeTime
{
    /**
     * @param ?string $label optional display name; the templates fall back to a label per type
     */
    public function __construct(
        public TimeType $type,
        public int $minutes,
        public ?string $label = null,
    ) {
    }
}
