<?php

declare(strict_types=1);

namespace Rezepte\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rezepte\Config\ConfigException;
use Rezepte\Config\SiteConfig;
use Rezepte\Recipe\RecipeLoadException;
use Rezepte\SiteFactory;
use Rezepte\Support\OutputDirectoryException;
use Rezepte\Tests\RecipeFixtures;

/**
 * Integration test: complete build with the real templates and assets
 * into a temporary output directory (without vendor downloads).
 */
final class SiteGeneratorTest extends TestCase
{
    use RecipeFixtures;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createTempDir();
        mkdir($this->root . '/recipes');
    }

    protected function tearDown(): void
    {
        $this->removeTempDir();
    }

    public function testBuildsCompleteSite(): void
    {
        $this->createRecipe($this->root . '/recipes', 'brot/brioche', [], "# Brioche\n\n## Zutaten\n\n- 500 g Mehl\n");
        $this->createRecipe($this->root . '/recipes', 'kuchen/apfelkuchen', ['title' => 'Apfelkuchen', 'category' => 'Kuchen']);

        $report = $this->factory()->createSiteGenerator()->build();

        self::assertSame(2, $report->recipes);
        self::assertSame(2, $report->categories);
        $out = $this->root . '/public';
        foreach ([
            'index.html', '404.html', 'brot/index.html', 'brot/brioche/index.html', 'kuchen/apfelkuchen/index.html',
            'assets/data/recipes.json', 'assets/images/brot--brioche.jpg', 'assets/css/style.css',
            'assets/js/search.js', 'sitemap.xml', 'robots.txt', '.htaccess',
        ] as $file) {
            self::assertFileExists($out . '/' . $file);
        }

        $recipeHtml = (string) file_get_contents($out . '/brot/brioche/index.html');
        self::assertStringContainsString('<link rel="canonical" href="https://example.org/rezepte/brot/brioche/">', $recipeHtml);
        self::assertStringNotContainsString('application/ld+json', $recipeHtml);
        self::assertStringContainsString('href="/rezepte/brot/"', $recipeHtml);
        self::assertStringContainsString('<li>500 g Mehl</li>', $recipeHtml);

        $sitemap = (string) file_get_contents($out . '/sitemap.xml');
        self::assertStringContainsString('<loc>https://example.org/rezepte/kuchen/apfelkuchen/</loc>', $sitemap);

        $search = json_decode((string) file_get_contents($out . '/assets/data/recipes.json'), true);
        self::assertSame(['Apfelkuchen', 'Brioche'], array_column($search, 'title'));
    }

    public function testCopiesImagesEmbeddedInMarkdownNextToTheRecipePage(): void
    {
        $directory = $this->createRecipe($this->root . '/recipes', 'brot/brioche', [], "## Schritte\n\n![Teig](schritt-1.jpg)");
        imagejpeg(imagecreatetruecolor(2, 2), $directory . '/schritt-1.jpg');

        $report = $this->factory()->createSiteGenerator()->build();

        self::assertSame(2, $report->images);
        self::assertFileExists($this->root . '/public/brot/brioche/schritt-1.jpg');
        self::assertStringContainsString(
            '<img src="schritt-1.jpg" alt="Teig" />',
            (string) file_get_contents($this->root . '/public/brot/brioche/index.html'),
        );
    }

    public function testRejectsExternalAndMissingImagesInMarkdown(): void
    {
        $this->createRecipe($this->root . '/recipes', 'brot/extern', [], '![x](https://example.org/bild.jpg)');
        $this->createRecipe($this->root . '/recipes', 'brot/fehlt', [], '![x](gibtsnicht.jpg)');

        try {
            $this->factory()->createSiteGenerator()->build();
            self::fail('Expected a RecipeLoadException.');
        } catch (RecipeLoadException $exception) {
            $messages = [];
            foreach ($exception->errors as $error) {
                $messages[$error->recipePath] = implode(' ', $error->problems);
            }
            self::assertStringContainsString('must be a file from the recipe directory', $messages['brot/extern']);
            self::assertStringContainsString('"gibtsnicht.jpg" does not exist', $messages['brot/fehlt']);
            self::assertDirectoryDoesNotExist($this->root . '/public', 'nothing written');
        }
    }

    public function testInvalidRecipeAbortsBuildWithoutTouchingOutput(): void
    {
        mkdir($this->root . '/public');
        file_put_contents($this->root . '/public/.recipe-build', '');
        file_put_contents($this->root . '/public/index.html', 'old version');
        $this->createRecipe($this->root . '/recipes', 'brot/brioche', ['yield' => 'viele']);

        try {
            $this->factory()->createSiteGenerator()->build();
            self::fail('Expected a RecipeLoadException.');
        } catch (RecipeLoadException) {
            self::assertStringEqualsFile($this->root . '/public/index.html', 'old version');
        }
    }

    public function testRefusesToCleanForeignOutputDirectory(): void
    {
        mkdir($this->root . '/public');
        file_put_contents($this->root . '/public/important.txt', 'do not delete');
        $this->createRecipe($this->root . '/recipes', 'brot/brioche');

        $this->expectException(OutputDirectoryException::class);
        try {
            $this->factory()->createSiteGenerator()->build();
        } finally {
            self::assertFileExists($this->root . '/public/important.txt');
        }
    }

    public function testDryRunWritesNothing(): void
    {
        $this->createRecipe($this->root . '/recipes', 'brot/brioche');

        $report = $this->factory()->createSiteGenerator(dryRun: true)->build();

        self::assertTrue($report->dryRun);
        self::assertGreaterThan(0, $report->files);
        self::assertDirectoryDoesNotExist($this->root . '/public');
    }

    public function testRobotsPolicyAllAllowsEveryCrawler(): void
    {
        $this->createRecipe($this->root . '/recipes', 'brot/brioche');

        $this->factory()->createSiteGenerator()->build();

        self::assertSame(
            "User-agent: *\nAllow: /rezepte/\n\nSitemap: https://example.org/rezepte/sitemap.xml\n",
            file_get_contents($this->root . '/public/robots.txt'),
        );
        self::assertStringNotContainsString('noindex', (string) file_get_contents($this->root . '/public/index.html'));
        self::assertStringNotContainsString('X-Robots-Tag', (string) file_get_contents($this->root . '/public/.htaccess'));
    }

    public function testRobotsPolicySearchEnginesAllowsOnlyListedCrawlers(): void
    {
        $this->createRecipe($this->root . '/recipes', 'brot/brioche');

        $this->factory(['robots' => 'search_engines'])->createSiteGenerator()->build();

        $robots = (string) file_get_contents($this->root . '/public/robots.txt');
        self::assertStringContainsString("User-agent: Googlebot\nAllow: /rezepte/\n", $robots);
        self::assertStringContainsString("User-agent: Bingbot\nAllow: /rezepte/\n", $robots);
        self::assertStringContainsString("User-agent: *\nDisallow: /rezepte/\n", $robots);
        self::assertStringContainsString('Sitemap: https://example.org/rezepte/sitemap.xml', $robots);
        self::assertStringNotContainsString('Google-Extended', $robots);
        self::assertStringNotContainsString('noindex', (string) file_get_contents($this->root . '/public/index.html'));
    }

    public function testRobotsPolicyNoneBlocksCrawlingAndIndexing(): void
    {
        $this->createRecipe($this->root . '/recipes', 'brot/brioche');

        $this->factory(['robots' => 'none'])->createSiteGenerator()->build();

        $robots = (string) file_get_contents($this->root . '/public/robots.txt');
        self::assertStringContainsString("User-agent: *\nDisallow: /rezepte/\n", $robots);
        self::assertStringNotContainsString('Allow:', $robots);
        self::assertStringNotContainsString('Sitemap:', $robots);
        foreach (['index.html', 'brot/index.html', 'brot/brioche/index.html'] as $page) {
            self::assertStringContainsString(
                '<meta name="robots" content="noindex, nofollow">',
                (string) file_get_contents($this->root . '/public/' . $page),
            );
        }
        self::assertStringContainsString(
            'Header always set X-Robots-Tag "noindex, nofollow"',
            (string) file_get_contents($this->root . '/public/.htaccess'),
        );
    }

    public function testRejectsUnknownRobotsPolicy(): void
    {
        $this->expectException(ConfigException::class);

        $this->factory(['robots' => 'keine-ki']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function factory(array $overrides = []): SiteFactory
    {
        $projectDir = dirname(__DIR__, 2);
        $config = SiteConfig::fromArray(SiteConfig::merge(SiteConfig::load($projectDir . '/config/site.dist.php'), $overrides + [
            'name' => 'Test',
            'description' => 'Test site',
            'base_url' => 'https://example.org/rezepte',
            'paths' => [
                'recipes' => $this->root . '/recipes',
                'output' => $this->root . '/public',
                'templates' => $projectDir . '/templates',
                'assets' => $projectDir . '/assets',
            ],
            'vendor' => [], // no CDN downloads in tests
        ]), $projectDir);

        return new SiteFactory($config, new NullLogger());
    }
}
