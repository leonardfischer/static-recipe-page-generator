<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * Collects all invalid recipes of one run so that every problem
 * can be reported at once.
 */
final class RecipeLoadException extends \RuntimeException
{
    /**
     * @param list<InvalidRecipeException> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(sprintf('%d recipe(s) could not be loaded.', count($errors)));
    }
}
