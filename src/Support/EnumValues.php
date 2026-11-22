<?php

declare(strict_types=1);

namespace Rezepte\Support;

/**
 * Adds values() to string-backed enums, e.g. for "allowed: …" messages.
 */
trait EnumValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
