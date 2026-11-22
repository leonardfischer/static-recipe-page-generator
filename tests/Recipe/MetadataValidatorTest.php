<?php

declare(strict_types=1);

namespace Rezepte\Tests\Recipe;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rezepte\Recipe\Difficulty;
use Rezepte\Recipe\MetadataValidator;
use Rezepte\Recipe\RecipeProblemException;
use Rezepte\Recipe\TimeType;
use Rezepte\Tests\RecipeFixtures;

final class MetadataValidatorTest extends TestCase
{
    use RecipeFixtures;

    public function testAcceptsCompleteMetadata(): void
    {
        $meta = (new MetadataValidator())->validate(self::validMeta());

        self::assertSame('Brioche', $meta->title);
        self::assertSame(Difficulty::Medium, $meta->difficulty);
        self::assertSame(['Hefeteig', 'Frühstück'], $meta->tags);
        self::assertSame('2026-11-15', $meta->created?->format('Y-m-d'));
        self::assertCount(3, $meta->times);
        self::assertSame(TimeType::Resting, $meta->times[1]->type);
        self::assertSame('Gehzeit', $meta->times[1]->label);
        self::assertNull($meta->times[0]->label, 'no label given – the template uses a default');
        self::assertSame(185, $meta->totalTime());
        self::assertSame(35, $meta->minutesOf(TimeType::Cooking));
        self::assertSame('1 Brioche', $meta->yield->text());
    }

    public function testOptionalFieldsMayBeMissing(): void
    {
        $data = array_intersect_key(self::validMeta(), array_flip([
            'title', 'description', 'category', 'difficulty',
        ])) + [
            'times' => [['type' => 'preparation', 'minutes' => 30]],
            'yield' => ['amount' => 4],
        ];

        $meta = (new MetadataValidator())->validate($data);

        self::assertSame([], $meta->tags);
        self::assertNull($meta->created);
        self::assertSame(0, $meta->minutesOf(TimeType::Cooking));
        self::assertSame(30, $meta->totalTime());
        self::assertNull($meta->yield->unit);
        self::assertSame('4', $meta->yield->text());
    }

    public function testMultipleTimesOfSameTypeAreSummed(): void
    {
        $meta = (new MetadataValidator())->validate(['times' => [
            ['type' => 'preparation', 'minutes' => 20],
            ['type' => 'resting', 'minutes' => 90, 'label' => 'Stockgare'],
            ['type' => 'resting', 'minutes' => 30, 'label' => 'Stückgare'],
        ]] + self::validMeta());

        self::assertSame(120, $meta->minutesOf(TimeType::Resting));
        self::assertSame([null, 'Stockgare', 'Stückgare'], array_map(static fn ($t) => $t->label, $meta->times));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'missing title' => [['title' => null], '"title"'];
        yield 'empty description' => [['description' => '   '], '"description"'];
        yield 'missing times' => [['times' => null], '"times"'];
        yield 'empty times list' => [['times' => []], '"times"'];
        yield 'times as object' => [['times' => ['preparation' => 30]], '"times"'];
        yield 'minutes as string' => [['times' => [['type' => 'preparation', 'minutes' => '30']]], 'times[0]: "minutes"'];
        yield 'zero minutes' => [['times' => [['type' => 'cooking', 'minutes' => 0]]], 'times[0]: "minutes"'];
        yield 'unknown time type' => [['times' => [['type' => 'baking', 'minutes' => 30]]], 'times[0]: "type"'];
        yield 'empty label' => [['times' => [['type' => 'cooking', 'minutes' => 5, 'label' => ' ']]], 'times[0]: "label"'];
        yield 'typo in time segment' => [['times' => [['type' => 'cooking', 'minuts' => 5]]], 'unknown key "minuts"'];
        yield 'missing yield' => [['yield' => null], '"yield"'];
        yield 'yield as number' => [['yield' => 4], '"yield"'];
        yield 'zero amount' => [['yield' => ['amount' => 0]], 'yield: "amount"'];
        yield 'empty unit' => [['yield' => ['amount' => 2, 'unit' => '']], 'yield: "unit"'];
        yield 'legacy field preparation_time' => [['preparation_time' => 30], 'Field "preparation_time" no longer exists – please use "times"'];
        yield 'legacy field servings' => [['servings' => 4], 'Field "servings" no longer exists – please use "yield"'];
        yield 'unknown difficulty' => [['difficulty' => 'extrem'], '"difficulty"'];
        yield 'tags not an array' => [['tags' => 'Brot'], '"tags"'];
        yield 'invalid date' => [['created' => '2026-02-30'], '"created"'];
        yield 'updated before created' => [['updated' => '2026-01-01'], '"updated" is earlier'];
        yield 'image path with directory' => [['image' => '../secret.jpg'], '"image"'];
        yield 'image with wrong extension' => [['image' => 'image.gif'], '"image"'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(array $override, string $expectedMessage): void
    {
        try {
            (new MetadataValidator())->validate($override + self::validMeta());
            self::fail('Expected a RecipeProblemException.');
        } catch (RecipeProblemException $exception) {
            self::assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }

    public function testCollectsAllProblemsAtOnce(): void
    {
        $this->expectException(RecipeProblemException::class);

        try {
            (new MetadataValidator())->validate([]);
        } catch (RecipeProblemException $exception) {
            self::assertCount(6, $exception->problems);

            throw $exception;
        }
    }

    public function testDetectsUnknownFields(): void
    {
        self::assertSame(
            ['tims'],
            (new MetadataValidator())->unknownFields(['title' => 'x', 'tims' => [], 'servings' => 2]),
            'legacy fields are reported as errors, not additionally as unknown',
        );
    }
}
