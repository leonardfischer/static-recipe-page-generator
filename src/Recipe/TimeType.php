<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

use Rezepte\Support\EnumValues;

/**
 * Kind of time segment. The default labels shown on the website live in the templates.
 */
enum TimeType: string
{
    use EnumValues;

    /** Active work: weighing, kneading, chopping … */
    case Preparation = 'preparation';

    /** Cooking: baking, boiling, roasting … */
    case Cooking = 'cooking';

    /** Passive waiting: proofing, resting, marinating, chilling … */
    case Resting = 'resting';
}
