<?php

declare(strict_types=1);

namespace Rezepte\Config;

use Rezepte\Support\EnumValues;

/**
 * Controls which crawlers may access the website (robots.txt, noindex).
 */
enum RobotsPolicy: string
{
    use EnumValues;

    /** All crawlers allowed. */
    case All = 'all';

    /**
     * Only the major search engines are allowed; every other crawler –
     * including future AI bots – is excluded (allowlist instead of blocklist).
     */
    case SearchEngines = 'search_engines';

    /** No crawling, no indexing. */
    case None = 'none';

    /**
     * Crawlers of the major search engines. These names have been stable for years.
     * Tokens such as "Google-Extended" (AI training) are deliberately missing and
     * therefore fall under the general disallow rule.
     */
    private const array SEARCH_ENGINE_CRAWLERS = ['Googlebot', 'Bingbot', 'DuckDuckBot', 'Applebot'];

    /**
     * @return list<string> explicitly allowed crawlers (SearchEngines only)
     */
    public function allowedCrawlers(): array
    {
        return $this === self::SearchEngines ? self::SEARCH_ENGINE_CRAWLERS : [];
    }

    public function allowsEveryone(): bool
    {
        return $this === self::All;
    }

    public function noIndex(): bool
    {
        return $this === self::None;
    }
}
