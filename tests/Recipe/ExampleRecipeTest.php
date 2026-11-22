<?php

declare(strict_types=1);

namespace Rezepte\Tests\Recipe;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rezepte\Markdown\MarkdownService;
use Rezepte\Recipe\MetadataValidator;
use Rezepte\Recipe\RecipeRepository;
use Rezepte\Tests\RecipeFixtures;

/**
 * Keeps the documented example recipe (recipes/_example/pancakes) valid.
 * The build ignores "_example", so the recipe is copied into a real category here.
 */
final class ExampleRecipeTest extends TestCase
{
    use RecipeFixtures;

    private const string EXAMPLE_DIR = __DIR__ . '/../../recipes/_example/pancakes';

    protected function tearDown(): void
    {
        $this->removeTempDir();
    }

    public function testExampleRecipeIsValidAndUsesAllFeatures(): void
    {
        $recipesDir = $this->createTempDir() . '/recipes';
        mkdir($recipesDir . '/examples/pancakes', 0775, true);
        foreach (['meta.json', 'content.md', 'image.jpg'] as $file) {
            copy(self::EXAMPLE_DIR . '/' . $file, $recipesDir . '/examples/pancakes/' . $file);
        }

        $recipe = (new RecipeRepository($recipesDir, new MetadataValidator(), new NullLogger()))->loadAll()->all()[0];
        $rendered = (new MarkdownService('section', 'Link'))->render($recipe->markdown);

        self::assertSame('Fluffy Pancakes', $recipe->meta->title);
        self::assertSame('8 pancakes', $recipe->meta->yield->text());
        self::assertSame(45, $recipe->meta->totalTime());
        foreach (['table-of-contents', '<table>', 'type="checkbox"', '<dl>', 'class="text-success"', '<del>', 'footnote-ref', '&lt;script&gt;'] as $feature) {
            self::assertStringContainsString($feature, $rendered->html);
        }
    }
}
