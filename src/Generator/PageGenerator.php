<?php

declare(strict_types=1);

namespace Rezepte\Generator;

use Rezepte\Config\SiteConfig;
use Rezepte\Markdown\RenderedMarkdown;
use Rezepte\Recipe\Category;
use Rezepte\Recipe\Recipe;
use Rezepte\Recipe\RecipeCollection;
use Rezepte\Support\OutputDirectory;
use Twig\Environment;
use Twig\Markup;

/**
 * Renders the HTML pages with Twig and writes them to the output directory.
 *
 * Every method returns the URL paths of the generated pages (for the sitemap).
 * Titles and descriptions passed here come from the recipe data; texts for
 * pages without such data (category, 404) are defined in their templates.
 */
final readonly class PageGenerator
{
    public function __construct(
        private Environment $twig,
        private OutputDirectory $output,
        private SiteConfig $config,
    ) {
    }

    /**
     * @return list<string>
     */
    public function generateRecipePage(Recipe $recipe, RenderedMarkdown $content, RecipeCollection $all): array
    {
        $category = $all->category($recipe->categorySlug);
        $related = array_values(array_filter(
            $category->recipes,
            static fn (Recipe $other): bool => $other !== $recipe,
        ));

        $this->write($recipe->urlPath(), 'pages/recipe.twig', $all, [
            'page' => $this->seo(
                title: $recipe->meta->title,
                description: $recipe->meta->description,
                urlPath: $recipe->urlPath(),
                imagePath: $recipe->imageUrlPath(),
                type: 'article',
            ),
            'recipe' => $recipe,
            'category' => $category,
            'related' => array_slice($related, 0, 3),
            // HTML already sanitized by the MarkdownService – deliberately marked
            // as trusted markup so Twig does not escape it.
            'content' => new Markup($content->html, 'UTF-8'),
        ]);

        return [$recipe->urlPath()];
    }

    /**
     * @return list<string>
     */
    public function generateCategoryPages(Category $category, RecipeCollection $all): array
    {
        $pages = Pagination::pageCount($category->count(), $this->config->perPage);
        $paths = [];

        for ($page = 1; $page <= $pages; $page++) {
            $pagination = new Pagination($page, $pages, $category->urlPath());
            $urlPath = $pagination->urlPath($page);
            $this->write($urlPath, 'pages/category.twig', $all, [
                'page' => $this->seo(
                    title: $category->name,
                    description: null,
                    urlPath: $urlPath,
                    imagePath: $category->recipes[0]->imageUrlPath(),
                ),
                'category' => $category,
                'recipes' => array_slice($category->recipes, ($page - 1) * $this->config->perPage, $this->config->perPage),
                'pagination' => $pagination,
            ]);
            $paths[] = $urlPath;
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function generateHomePage(RecipeCollection $all): array
    {
        $this->write('/', 'pages/home.twig', $all, [
            'page' => $this->seo(
                title: null,
                description: $this->config->description,
                urlPath: '/',
                imagePath: $this->defaultImagePath(),
            ),
            'recipes' => $all->all(),
            'newest' => $all->newest($this->config->newestCount),
        ]);

        return ['/'];
    }

    public function generateNotFoundPage(RecipeCollection $all): void
    {
        $this->write('/404.html', 'pages/404.twig', $all, [
            'page' => $this->seo(
                title: null,
                description: null,
                urlPath: '/404.html',
                imagePath: $this->defaultImagePath(),
                noIndex: true,
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $urlPath, string $template, RecipeCollection $all, array $context): void
    {
        $html = $this->twig->render($template, $context + [
            'navigation' => $all->categories(),
            'current_path' => $urlPath,
        ]);
        $file = str_ends_with($urlPath, '/') ? $urlPath . 'index.html' : $urlPath;
        $this->output->put($file, $html);
    }

    /**
     * Page metadata for layouts/base.twig. A null title means the site name;
     * a null description must be set by the page template.
     *
     * @return array<string, mixed>
     */
    private function seo(
        ?string $title,
        ?string $description,
        string $urlPath,
        ?string $imagePath,
        string $type = 'website',
        bool $noIndex = false,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'url_path' => $urlPath,
            'image_path' => $imagePath,
            'type' => $type,
            'noindex' => $noIndex,
        ];
    }

    private function defaultImagePath(): ?string
    {
        return $this->config->defaultImage !== null ? '/assets/' . ltrim($this->config->defaultImage, '/') : null;
    }
}
