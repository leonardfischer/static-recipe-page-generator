<?php

declare(strict_types=1);

namespace Rezepte\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rezepte\Recipe\Difficulty;
use Rezepte\Recipe\Recipe;
use Rezepte\Recipe\RecipeImage;
use Rezepte\Recipe\RecipeMetadata;
use Rezepte\Recipe\RecipeTime;
use Rezepte\Recipe\RecipeYield;
use Rezepte\Recipe\TimeType;
use Rezepte\Support\UrlGenerator;

final class UrlGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function baseUrls(): iterable
    {
        yield 'domain root' => ['https://example.org', '/brot/brioche/', '/brot/brioche/', 'https://example.org/brot/brioche/'];
        yield 'subdirectory' => ['http://localhost/rezepte/public/', 'brot/', '/rezepte/public/brot/', 'http://localhost/rezepte/public/brot/'];
        yield 'with port' => ['http://localhost:8080', '/', '/', 'http://localhost:8080/'];
    }

    #[DataProvider('baseUrls')]
    public function testBuildsPathsRelativeToBaseUrl(string $baseUrl, string $path, string $expectedPath, string $expectedAbsolute): void
    {
        $urls = new UrlGenerator($baseUrl);

        self::assertSame($expectedPath, $urls->path($path));
        self::assertSame($expectedAbsolute, $urls->absolute($path));
    }

    public function testRejectsInvalidBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlGenerator('kein-url');
    }

    public function testRecipeUrlFollowsDirectoryStructure(): void
    {
        $recipe = new Recipe(
            'brot',
            'brioche',
            new RecipeMetadata('Brioche', 'Lecker.', 'Brot', [new RecipeTime(TimeType::Preparation, 30)], new RecipeYield(1), Difficulty::Easy),
            '# Brioche',
            new RecipeImage('/tmp/foto.jpeg', 'jpg', 10, 10),
        );

        self::assertSame('/brot/brioche/', $recipe->urlPath());
        self::assertSame('/assets/images/brot--brioche.jpg', $recipe->imageUrlPath());
        self::assertSame('/rezepte/brot/brioche/', (new UrlGenerator('https://example.org/rezepte'))->path($recipe->urlPath()));
    }

}
