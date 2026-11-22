<?php

declare(strict_types=1);

namespace Rezepte\Markdown;

/**
 * Result of the Markdown conversion. The HTML has already been sanitized.
 */
final readonly class RenderedMarkdown
{
    /**
     * @param list<string> $imageSources URLs of images embedded in the Markdown
     */
    public function __construct(
        public string $html,
        public array $imageSources = [],
    ) {
    }
}
