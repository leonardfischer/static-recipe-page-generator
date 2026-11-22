<?php

declare(strict_types=1);

namespace Rezepte\Tests\Search;

use PHPUnit\Framework\TestCase;
use Rezepte\Recipe\Difficulty;
use Rezepte\Recipe\Recipe;
use Rezepte\Recipe\RecipeCollection;
use Rezepte\Recipe\RecipeImage;
use Rezepte\Recipe\RecipeMetadata;
use Rezepte\Recipe\RecipeTime;
use Rezepte\Recipe\RecipeYield;
use Rezepte\Recipe\TimeType;
use Rezepte\Search\SearchIndexBuilder;
use Rezepte\Support\UrlGenerator;

final class SearchIndexBuilderTest extends TestCase
{
    public function testBuildsCompactSearchEntries(): void
    {
        $collection = new RecipeCollection([
            $this->recipe('kuchen', 'apfelkuchen', 'Apfelkuchen'),
            $this->recipe('brot', 'brioche', 'Brioche', ['Hefeteig', 'Frühstück']),
        ]);

        $index = (new SearchIndexBuilder(new UrlGenerator('https://example.org')))->build($collection);

        self::assertCount(2, $index);
        self::assertSame([
            'title' => 'Apfelkuchen',
            'description' => 'Beschreibung von Apfelkuchen',
            'category' => 'Kategorie kuchen',
            'category_slug' => 'kuchen',
            'tags' => [],
            'url' => '/kuchen/apfelkuchen/',
            'image' => '/assets/images/kuchen--apfelkuchen.jpg',
            'difficulty' => 'leicht',
            'total_time' => 75,
            'created' => '2026-11-15',
        ], $index[0]);
        self::assertSame(['Hefeteig', 'Frühstück'], $index[1]['tags']);
    }

    public function testJsonContainsNoMarkdownAndRespectsBasePath(): void
    {
        $collection = new RecipeCollection([$this->recipe('brot', 'brioche', 'Brioche')]);

        $json = (new SearchIndexBuilder(new UrlGenerator('https://example.org/rezepte')))->toJson($collection);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('GEHEIMER MARKDOWN', $json);
        self::assertArrayNotHasKey('markdown', $data[0]);
        self::assertSame('/rezepte/brot/brioche/', $data[0]['url']);
        self::assertSame('/rezepte/assets/images/brot--brioche.jpg', $data[0]['image']);
        self::assertStringContainsString('"Brioche"', $json, 'umlauts and slashes stay readable');
    }

    /**
     * @param list<string> $tags
     */
    private function recipe(string $category, string $slug, string $title, array $tags = []): Recipe
    {
        return new Recipe(
            $category,
            $slug,
            new RecipeMetadata(
                title: $title,
                description: 'Beschreibung von ' . $title,
                category: 'Kategorie ' . $category,
                times: [new RecipeTime(TimeType::Preparation, 30), new RecipeTime(TimeType::Cooking, 45)],
                yield: new RecipeYield(4),
                difficulty: Difficulty::Easy,
                tags: $tags,
                created: new \DateTimeImmutable('2026-11-15'),
            ),
            "# $title\n\nGEHEIMER MARKDOWN",
            new RecipeImage('/tmp/image.jpg', 'jpg', 10, 10),
        );
    }
}
