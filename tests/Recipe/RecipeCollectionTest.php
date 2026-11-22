<?php

declare(strict_types=1);

namespace Rezepte\Tests\Recipe;

use PHPUnit\Framework\TestCase;
use Rezepte\Recipe\Difficulty;
use Rezepte\Recipe\Recipe;
use Rezepte\Recipe\RecipeCollection;
use Rezepte\Recipe\RecipeImage;
use Rezepte\Recipe\RecipeMetadata;
use Rezepte\Recipe\RecipeTime;
use Rezepte\Recipe\RecipeYield;
use Rezepte\Recipe\TimeType;

final class RecipeCollectionTest extends TestCase
{
    public function testSortsTitlesIgnoringCaseAndAccents(): void
    {
        $collection = new RecipeCollection([
            $this->recipe('kuchen', 'zimtschnecken', 'Zimtschnecken'),
            $this->recipe('kuchen', 'creme-brulee', 'Crème brûlée'),
            $this->recipe('kuchen', 'kaesekuchen', 'Käsekuchen'),
            $this->recipe('kuchen', 'apfelkuchen', 'apfelkuchen'),
            $this->recipe('kuchen', 'kirschkuchen', 'Kirschkuchen'),
        ]);

        self::assertSame(
            ['apfelkuchen', 'Crème brûlée', 'Käsekuchen', 'Kirschkuchen', 'Zimtschnecken'],
            array_map(static fn (Recipe $recipe): string => $recipe->meta->title, $collection->all()),
        );
    }

    public function testGroupsCategoriesSortedByDisplayName(): void
    {
        $collection = new RecipeCollection([
            $this->recipe('suesses', 'eis', 'Eis', 'Süßes'),
            $this->recipe('brot', 'brioche', 'Brioche', 'Brot'),
            $this->recipe('obst', 'salat', 'Obstsalat', 'Obst'),
        ]);

        self::assertSame(['Brot', 'Obst', 'Süßes'], array_map(
            static fn ($category): string => $category->name,
            $collection->categories(),
        ));
        self::assertSame('Süßes', $collection->category('suesses')->name);
    }

    public function testNewestOnlyIncludesRecipesWithCreationDate(): void
    {
        $collection = new RecipeCollection([
            $this->recipe('brot', 'alt', 'Alt', created: '2026-01-01'),
            $this->recipe('brot', 'ohne-datum', 'Ohne Datum'),
            $this->recipe('brot', 'neu', 'Neu', created: '2026-11-01'),
        ]);

        self::assertSame(['Neu', 'Alt'], array_map(
            static fn (Recipe $recipe): string => $recipe->meta->title,
            $collection->newest(3),
        ));
    }

    private function recipe(
        string $category,
        string $slug,
        string $title,
        string $categoryName = 'Kuchen',
        ?string $created = null,
    ): Recipe {
        return new Recipe(
            $category,
            $slug,
            new RecipeMetadata(
                $title,
                'Beschreibung',
                $categoryName,
                [new RecipeTime(TimeType::Preparation, 10)],
                new RecipeYield(1),
                Difficulty::Easy,
                created: $created !== null ? new \DateTimeImmutable($created) : null,
            ),
            '',
            new RecipeImage('/tmp/image.jpg', 'jpg', 1, 1),
        );
    }
}
