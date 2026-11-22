<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * Yield of a recipe, e.g. 1 "Brot" or 12 "Stücke". Without a unit the
 * templates show a default unit (servings).
 */
final readonly class RecipeYield
{
    public function __construct(
        public int $amount,
        public ?string $unit = null,
    ) {
    }

    /**
     * Amount followed by the unit if there is one, e.g. "1 Brot" or "4".
     */
    public function text(): string
    {
        return $this->unit === null ? (string) $this->amount : $this->amount . ' ' . $this->unit;
    }
}
