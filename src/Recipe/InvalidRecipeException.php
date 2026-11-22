<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * A single recipe is invalid. Contains every problem that was found.
 */
final class InvalidRecipeException extends \RuntimeException
{
    /**
     * @param list<string> $problems
     */
    public function __construct(
        public readonly string $recipePath,
        public readonly array $problems,
    ) {
        parent::__construct(sprintf('Recipe "%s" is invalid: %s', $recipePath, implode(' ', $problems)));
    }
}
