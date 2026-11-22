<?php

declare(strict_types=1);

namespace Rezepte\Twig;

use Rezepte\Support\UrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig functions for building URLs relative to the configured base URL.
 * Display formats (durations etc.) live in templates/components/format.twig.
 */
final class SiteExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGenerator $urls,
        private readonly string $assetVersion,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', $this->urls->path(...)),
            new TwigFunction('absolute_url', $this->urls->absolute(...)),
            new TwigFunction('asset', $this->asset(...)),
        ];
    }

    /**
     * URL of a file below /assets/ with a version parameter against stale caches.
     */
    public function asset(string $path): string
    {
        return $this->urls->path('/assets/' . ltrim($path, '/')) . '?v=' . rawurlencode($this->assetVersion);
    }
}
