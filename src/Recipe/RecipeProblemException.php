<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

/**
 * One or more problems with a recipe's files or metadata. The recipe path is
 * added later by the repository (see InvalidRecipeException).
 */
final class RecipeProblemException extends \RuntimeException
{
    /**
     * @param list<string> $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
