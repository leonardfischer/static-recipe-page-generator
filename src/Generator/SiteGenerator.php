<?php

declare(strict_types=1);

namespace Rezepte\Generator;

use Psr\Log\LoggerInterface;
use Rezepte\Markdown\MarkdownService;
use Rezepte\Markdown\RenderedMarkdown;
use Rezepte\Recipe\InvalidRecipeException;
use Rezepte\Recipe\Recipe;
use Rezepte\Recipe\RecipeCollection;
use Rezepte\Recipe\RecipeLoadException;
use Rezepte\Recipe\RecipeRepository;
use Rezepte\Support\OutputDirectory;

/**
 * Orchestrates the build in a fixed order.
 *
 * Everything that can fail without touching files (loading, validation,
 * Markdown) happens before the output directory is cleaned. An invalid
 * recipe therefore leaves the previously generated website untouched.
 */
final readonly class SiteGenerator
{
    public function __construct(
        private RecipeRepository $repository,
        private MarkdownService $markdown,
        private PageGenerator $pages,
        private AssetGenerator $assets,
        private SeoFileGenerator $seo,
        private OutputDirectory $output,
        private LoggerInterface $logger,
    ) {
    }

    public function build(): BuildReport
    {
        $start = hrtime(true);

        $this->logger->notice('Discovering, loading and validating recipes …');
        $recipes = $this->repository->loadAll();
        $categories = $recipes->categories();
        $this->logger->notice('Found {recipes} recipes in {categories} categories.', [
            'recipes' => count($recipes),
            'categories' => count($categories),
        ]);

        $this->logger->notice('Converting Markdown …');
        $rendered = $this->renderMarkdown($recipes);

        $this->logger->notice($this->output->isDryRun() ? 'Dry run: pages are rendered but not written.' : 'Cleaning output directory …');
        $this->output->clean();

        /** @var array<string, ?\DateTimeImmutable> $sitemap */
        $sitemap = [];
        $this->logger->notice('Generating recipe pages …');
        foreach ($recipes as $recipe) {
            foreach ($this->pages->generateRecipePage($recipe, $rendered[$recipe->path()], $recipes) as $path) {
                $sitemap[$path] = $recipe->meta->lastModified();
            }
        }

        $this->logger->notice('Generating category pages …');
        foreach ($categories as $category) {
            foreach ($this->pages->generateCategoryPages($category, $recipes) as $path) {
                $sitemap[$path] = null;
            }
        }

        $this->logger->notice('Generating home page …');
        $this->pages->generateHomePage($recipes);
        $this->pages->generateNotFoundPage($recipes);
        $sitemap = ['/' => null] + $sitemap;

        $this->logger->notice('Generating search index …');
        $this->assets->writeSearchIndex($recipes);

        $this->logger->notice('Copying images and assets …');
        $images = $this->assets->copyRecipeImages($recipes);
        $this->assets->compileStyles();
        $this->assets->copyScripts();
        $this->assets->copyStaticFiles();
        $this->assets->copyVendorAssets();

        $this->logger->notice('Generating sitemap, robots.txt and .htaccess …');
        $this->seo->generateSitemap($sitemap);
        $this->seo->generateRobots();
        $this->seo->generateHtaccess();

        return new BuildReport(
            recipes: count($recipes),
            categories: count($categories),
            pages: count($sitemap) + 1,
            images: $images,
            files: $this->output->writtenFiles(),
            seconds: (hrtime(true) - $start) / 1e9,
            dryRun: $this->output->isDryRun(),
        );
    }

    /**
     * @return array<string, RenderedMarkdown> recipe path => result
     *
     * @throws RecipeLoadException if content.md embeds images that are not in the recipe directory
     */
    private function renderMarkdown(RecipeCollection $recipes): array
    {
        $rendered = [];
        $errors = [];
        foreach ($recipes as $recipe) {
            $result = $this->markdown->render($recipe->markdown);
            $problems = self::checkEmbeddedImages($recipe, $result);
            if ($problems !== []) {
                $errors[] = new InvalidRecipeException($recipe->path(), $problems);
            }
            $rendered[$recipe->path()] = $result;
            $this->logger->info('Markdown converted: {path}', ['path' => $recipe->path()]);
        }

        if ($errors !== []) {
            throw new RecipeLoadException($errors);
        }

        return $rendered;
    }

    /**
     * Images in content.md must be files from the recipe directory, e.g. ![Teig](schritt.jpg).
     * External images would be blocked by the Content Security Policy anyway.
     *
     * @return list<string>
     */
    private static function checkEmbeddedImages(Recipe $recipe, RenderedMarkdown $content): array
    {
        $problems = [];
        foreach ($content->imageSources as $source) {
            $fileName = rawurldecode(str_starts_with($source, './') ? substr($source, 2) : $source);
            if ($fileName === '' || str_contains($fileName, '/') || str_contains($fileName, ':')) {
                $problems[] = sprintf(
                    'content.md: image "%s" must be a file from the recipe directory, e.g. ![Beschreibung](schritt.jpg).',
                    $source,
                );
            } elseif (!isset($recipe->attachments[$fileName])) {
                $problems[] = sprintf('content.md: image file "%s" does not exist in the recipe directory.', $fileName);
            }
        }

        return $problems;
    }
}
