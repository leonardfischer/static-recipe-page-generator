<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

use Rezepte\Support\EnumValues;

/**
 * Difficulty of a recipe. The values are part of the meta.json format.
 */
enum Difficulty: string
{
    use EnumValues;

    case Easy = 'leicht';
    case Medium = 'mittel';
    case Hard = 'schwer';
}
