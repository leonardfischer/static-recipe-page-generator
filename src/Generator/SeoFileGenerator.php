<?php

declare(strict_types=1);

namespace Rezepte\Generator;

use Rezepte\Support\OutputDirectory;
use Twig\Environment;

/**
 * Generates sitemap.xml, robots.txt and the Apache configuration (.htaccess).
 */
final readonly class SeoFileGenerator
{
    public function __construct(
        private Environment $twig,
        private OutputDirectory $output,
    ) {
    }

    /**
     * @param array<string, ?\DateTimeImmutable> $entries URL path => last modification
     */
    public function generateSitemap(array $entries): void
    {
        $this->output->put('sitemap.xml', $this->twig->render('seo/sitemap.xml.twig', ['entries' => $entries]));
    }

    public function generateRobots(): void
    {
        $this->output->put('robots.txt', $this->twig->render('seo/robots.txt.twig'));
    }

    public function generateHtaccess(): void
    {
        $this->output->put('.htaccess', $this->twig->render('seo/htaccess.txt.twig'));
    }
}
