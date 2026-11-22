<?php

declare(strict_types=1);

namespace Rezepte;

use Psr\Log\LoggerInterface;
use Rezepte\Config\SiteConfig;
use Rezepte\Generator\AssetGenerator;
use Rezepte\Generator\PageGenerator;
use Rezepte\Generator\SeoFileGenerator;
use Rezepte\Generator\SiteGenerator;
use Rezepte\Markdown\MarkdownService;
use Rezepte\Recipe\MetadataValidator;
use Rezepte\Recipe\RecipeRepository;
use Rezepte\Search\SearchIndexBuilder;
use Rezepte\Support\OutputDirectory;
use Rezepte\Support\UrlGenerator;
use Rezepte\Support\VendorAssets;
use Rezepte\Twig\SiteExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Composition root: wires all services via constructor injection.
 * Deliberately without a DI container – the dependency graph is small.
 */
final readonly class SiteFactory
{
    public function __construct(
        private SiteConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    public function createSiteGenerator(bool $dryRun = false): SiteGenerator
    {
        $config = $this->config;
        $urls = new UrlGenerator($config->baseUrl);
        $output = new OutputDirectory(
            $config->outputDir,
            [
                $config->recipesDir,
                $config->templatesDir,
                $config->assetsDir,
                $config->projectDir . '/src',
                $config->projectDir . '/config',
                $config->projectDir . '/vendor',
            ],
            $dryRun,
        );
        $twig = $this->createTwig($urls);

        return new SiteGenerator(
            repository: $this->createRepository(),
            markdown: $this->createMarkdownService(),
            pages: new PageGenerator($twig, $output, $config),
            assets: new AssetGenerator(
                $config->assetsDir,
                $output,
                new SearchIndexBuilder($urls),
                new VendorAssets($config->vendorAssets, $config->assetsDir . '/vendor', $this->logger),
                $this->logger,
            ),
            seo: new SeoFileGenerator($twig, $output),
            output: $output,
            logger: $this->logger,
        );
    }

    private function createRepository(): RecipeRepository
    {
        return new RecipeRepository($this->config->recipesDir, new MetadataValidator(), $this->logger);
    }

    private function createMarkdownService(): MarkdownService
    {
        return new MarkdownService(
            $this->config->headingIdPrefix,
            $this->config->permalinkTitle,
        );
    }

    private function createTwig(UrlGenerator $urls): Environment
    {
        $twig = new Environment(new FilesystemLoader($this->config->templatesDir), [
            'autoescape' => 'name', // HTML for *.twig, no escaping for *.txt.twig
            'strict_variables' => true,
            'cache' => false,
        ]);
        $twig->addExtension(new SiteExtension($urls, substr(sha1((string) microtime(true)), 0, 8)));
        $twig->addGlobal('site', [
            'name' => $this->config->name,
            'description' => $this->config->description,
            'language' => $this->config->language,
            'locale' => $this->config->locale,
            'base_path' => $urls->basePath(),
            'robots' => $this->config->robots,
        ]);

        return $twig;
    }
}
