<?php

declare(strict_types=1);

namespace Rezepte\Tests\Recipe;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rezepte\Recipe\Difficulty;
use Rezepte\Recipe\MetadataValidator;
use Rezepte\Recipe\RecipeLoadException;
use Rezepte\Recipe\RecipeRepository;
use Rezepte\Tests\RecipeFixtures;

final class RecipeRepositoryTest extends TestCase
{
    use RecipeFixtures;

    private string $recipesDir;

    protected function setUp(): void
    {
        $this->recipesDir = $this->createTempDir() . '/recipes';
        mkdir($this->recipesDir);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir();
    }

    public function testDiscoversAllRecipesFromDirectoryStructure(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/brioche');
        $this->createRecipe($this->recipesDir, 'brot/sauerteigbrot', ['title' => 'Sauerteigbrot']);
        $this->createRecipe($this->recipesDir, 'kuchen/apfelkuchen', ['title' => 'Apfelkuchen', 'category' => 'Kuchen']);

        $recipes = $this->repository()->loadAll();

        self::assertCount(3, $recipes);
        self::assertSame(
            ['kuchen/apfelkuchen', 'brot/brioche', 'brot/sauerteigbrot'],
            array_map(static fn ($recipe) => $recipe->path(), $recipes->all()),
        );
        self::assertSame(['Brot', 'Kuchen'], array_map(static fn ($c) => $c->name, $recipes->categories()));
    }

    public function testLoadsMetadataMarkdownAndImage(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/brioche', [], "# Brioche\n\nInhalt");

        $recipe = $this->repository()->loadAll()->all()[0];

        self::assertSame('brot', $recipe->categorySlug);
        self::assertSame('brioche', $recipe->slug);
        self::assertSame('Brioche', $recipe->meta->title);
        self::assertSame(Difficulty::Medium, $recipe->meta->difficulty);
        self::assertSame(185, $recipe->meta->totalTime());
        self::assertStringContainsString('Inhalt', $recipe->markdown);
        self::assertStringEndsWith('brot/brioche/image.jpg', str_replace('\\', '/', $recipe->image->sourcePath));
        self::assertSame(4, $recipe->image->width);
    }

    public function testUsesImageFromMetadataWhenGiven(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/brioche', ['image' => 'foto.jpg'], image: 'foto.jpg');

        $recipe = $this->repository()->loadAll()->all()[0];

        self::assertStringEndsWith('foto.jpg', $recipe->image->sourcePath);
    }

    public function testRefusesAnEmptyRecipesDirectory(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/_entwurf');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No recipes found');

        $this->repository()->loadAll();
    }

    public function testIgnoresHiddenAndUnderscoreDirectories(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/brioche');
        $this->createRecipe($this->recipesDir, 'brot/_entwurf');
        $this->createRecipe($this->recipesDir, '.git/objekt');

        self::assertCount(1, $this->repository()->loadAll());
    }

    public function testReportsMissingFiles(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/ohne-meta', meta: null);
        $this->createRecipe($this->recipesDir, 'brot/ohne-inhalt', markdown: null);
        $this->createRecipe($this->recipesDir, 'brot/ohne-bild', image: null);

        $problems = $this->collectProblems();

        self::assertSame(['File meta.json is missing.'], $problems['brot/ohne-meta']);
        self::assertSame(['File content.md is missing.'], $problems['brot/ohne-inhalt']);
        self::assertSame(['Image file image.jpg is missing.'], $problems['brot/ohne-bild']);
    }

    public function testReportsInvalidJson(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/kaputt', '{"title": "Brioche",');

        $problems = $this->collectProblems();

        self::assertStringContainsString('does not contain valid JSON', $problems['brot/kaputt'][0]);
    }

    public function testReportsInvalidMetadataAndContinuesWithOtherRecipes(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/gut');
        $this->createRecipe($this->recipesDir, 'brot/schlecht', ['yield' => ['amount' => 0], 'difficulty' => 'extrem']);

        $problems = $this->collectProblems();

        self::assertArrayNotHasKey('brot/gut', $problems);
        self::assertCount(2, $problems['brot/schlecht']);
    }

    public function testReportsMisplacedAndEmptyRecipeDirectories(): void
    {
        $this->createRecipe($this->recipesDir, 'zu-flach'); // recipes/zu-flach/content.md
        mkdir($this->recipesDir . '/brot/leer', 0775, true);
        $this->createRecipe($this->recipesDir, 'brot/ungueltig', ['difficulty' => 'extrem']);

        $problems = $this->collectProblems();

        self::assertStringContainsString('<category>/<recipe>', $problems['zu-flach'][0]);
        self::assertStringContainsString('neither content.md nor meta.json', $problems['brot/leer'][0]);
        self::assertArrayHasKey('brot/ungueltig', $problems, 'reported together with the misplaced directories');
    }

    public function testRejectsDirectoryNamesThatAreNotUrlSafe(): void
    {
        $this->createRecipe($this->recipesDir, 'brot/Käse Brot');
        $this->createRecipe($this->recipesDir, 'brot/seite-2');

        $problems = $this->collectProblems();

        self::assertStringContainsString('not URL-safe', $problems['brot/Käse Brot'][0]);
        self::assertStringContainsString('reserved', $problems['brot/seite-2'][0]);
    }

    public function testRejectsFilesThatAreNotImages(): void
    {
        $directory = $this->createRecipe($this->recipesDir, 'brot/brioche', image: null);
        file_put_contents($directory . '/image.jpg', 'kein Bild');

        $problems = $this->collectProblems();

        self::assertStringContainsString('not a valid', $problems['brot/brioche'][0]);
    }

    private function repository(): RecipeRepository
    {
        return new RecipeRepository($this->recipesDir, new MetadataValidator(), new NullLogger());
    }

    /**
     * @return array<string, list<string>>
     */
    private function collectProblems(): array
    {
        try {
            $this->repository()->loadAll();
        } catch (RecipeLoadException $exception) {
            $problems = [];
            foreach ($exception->errors as $error) {
                $problems[$error->recipePath] = $error->problems;
            }

            return $problems;
        }

        self::fail('Expected a RecipeLoadException.');
    }
}
