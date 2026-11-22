<?php

declare(strict_types=1);

namespace Rezepte\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rezepte\Support\OutputDirectory;
use Rezepte\Support\OutputDirectoryException;
use Rezepte\Tests\RecipeFixtures;

final class OutputDirectoryTest extends TestCase
{
    use RecipeFixtures;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->createTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir();
    }

    public function testWritesAndCopiesFilesBelowTheOutputDirectory(): void
    {
        $output = new OutputDirectory($this->root . '/public');
        file_put_contents($this->root . '/source.txt', 'copy me');

        $output->clean();
        $output->put('brot/index.html', '<html>');
        $output->copy($this->root . '/source.txt', 'assets/copy.txt');

        self::assertStringEqualsFile($this->root . '/public/brot/index.html', '<html>');
        self::assertStringEqualsFile($this->root . '/public/assets/copy.txt', 'copy me');
        self::assertSame(3, $output->writtenFiles(), 'marker file + two files');
    }

    public function testCleanRemovesEverythingFromAPreviousBuild(): void
    {
        $output = new OutputDirectory($this->root . '/public');
        $output->clean();
        $output->put('brot/brioche/index.html', 'old');
        $output->put('.htaccess', 'old');

        (new OutputDirectory($this->root . '/public'))->clean();

        self::assertFileDoesNotExist($this->root . '/public/brot');
        self::assertFileDoesNotExist($this->root . '/public/.htaccess');
        self::assertFileExists($this->root . '/public/.recipe-build');
    }

    public function testRefusesToCleanAForeignDirectory(): void
    {
        mkdir($this->root . '/public');
        file_put_contents($this->root . '/public/important.txt', 'do not delete');

        try {
            (new OutputDirectory($this->root . '/public'))->clean();
            self::fail('Expected an OutputDirectoryException.');
        } catch (OutputDirectoryException) {
            self::assertFileExists($this->root . '/public/important.txt');
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function overlappingDirectories(): iterable
    {
        yield 'same directory' => ['/site', '/site'];
        yield 'output inside source' => ['/site/recipes/out', '/site/recipes'];
        yield 'output contains source' => ['/site', '/site/recipes'];
        yield 'different case' => ['/Site', '/site/recipes'];
    }

    #[DataProvider('overlappingDirectories')]
    public function testRefusesOutputDirectoryOverlappingWithSources(string $output, string $source): void
    {
        $this->expectException(OutputDirectoryException::class);

        (new OutputDirectory($this->root . $output, [$this->root . $source]))->clean();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function escapingPaths(): iterable
    {
        yield 'parent directory' => ['../outside.txt'];
        yield 'nested parent directory' => ['brot/../../outside.txt'];
        yield 'empty path' => [''];
    }

    #[DataProvider('escapingPaths')]
    public function testRejectsPathsOutsideTheOutputDirectory(string $path): void
    {
        $this->expectException(OutputDirectoryException::class);

        (new OutputDirectory($this->root . '/public'))->put($path, 'x');
    }

    public function testDryRunCountsButWritesNothing(): void
    {
        $output = new OutputDirectory($this->root . '/public', dryRun: true);

        $output->clean();
        $output->put('index.html', '<html>');

        self::assertSame(1, $output->writtenFiles());
        self::assertDirectoryDoesNotExist($this->root . '/public');
    }
}
