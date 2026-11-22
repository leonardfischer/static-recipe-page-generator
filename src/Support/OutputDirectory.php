<?php

declare(strict_types=1);

namespace Rezepte\Support;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Write access to the output directory, with the project's safety rules on
 * top of Symfony Filesystem:
 *
 * - in dry-run mode nothing is changed; write operations are only counted,
 * - files can only be written below the output directory,
 * - the directory is only emptied if a previous build left its marker file
 *   and it does not overlap with a source directory.
 */
final class OutputDirectory
{
    private const string MARKER_FILE = '.recipe-build';

    private readonly string $path;
    private int $writtenFiles = 0;

    /**
     * @param string       $path           absolute path of the output directory
     * @param list<string> $protectedPaths source directories that must never be cleaned
     */
    public function __construct(
        string $path,
        private readonly array $protectedPaths = [],
        private readonly bool $dryRun = false,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        $this->path = Path::canonicalize($path);
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function writtenFiles(): int
    {
        return $this->writtenFiles;
    }

    /**
     * Empties the output directory in a controlled way.
     *
     * An existing, non-empty directory is only emptied if it contains the
     * marker file of a previous build.
     */
    public function clean(): void
    {
        $this->assertNoOverlapWithSources();
        if ($this->dryRun) {
            return;
        }

        if (is_dir($this->path)) {
            $contents = Finder::create()->in($this->path)->depth(0)->ignoreDotFiles(false)->ignoreVCS(false);
            if ($contents->hasResults() && !is_file(Path::join($this->path, self::MARKER_FILE))) {
                throw new OutputDirectoryException(sprintf(
                    'Output directory "%s" is not empty and was not created by a previous build. '
                    . 'Please check and empty it manually.',
                    $this->path,
                ));
            }
            $this->filesystem->remove(iterator_to_array($contents, false));
        }

        $this->put(self::MARKER_FILE, "Generated automatically. Replaced completely on every build.\n");
    }

    /**
     * Writes a file relative to the output directory (atomically, creating directories as needed).
     */
    public function put(string $relativePath, string $content): void
    {
        $target = $this->target($relativePath);
        $this->writtenFiles++;
        if (!$this->dryRun) {
            $this->filesystem->dumpFile($target, $content);
        }
    }

    /**
     * Copies a source file to a path relative to the output directory.
     */
    public function copy(string $source, string $relativePath): void
    {
        if (!is_file($source)) {
            throw new OutputDirectoryException(sprintf('Source file "%s" does not exist.', $source));
        }
        $target = $this->target($relativePath);
        $this->writtenFiles++;
        if (!$this->dryRun) {
            $this->filesystem->copy($source, $target, true);
        }
    }

    private function target(string $relativePath): string
    {
        $target = Path::join($this->path, $relativePath);
        if ($target === $this->path || !Path::isBasePath($this->path, $target)) {
            throw new OutputDirectoryException(sprintf('Invalid output path "%s".', $relativePath));
        }

        return $target;
    }

    private function assertNoOverlapWithSources(): void
    {
        $output = self::comparable($this->path);
        foreach ($this->protectedPaths as $protected) {
            $source = self::comparable($protected);
            if (Path::isBasePath($output, $source) || Path::isBasePath($source, $output)) {
                throw new OutputDirectoryException(sprintf(
                    'Output directory "%s" overlaps with the source directory "%s".',
                    $this->path,
                    $protected,
                ));
            }
        }
    }

    /**
     * Resolves symlinks where possible and compares case-insensitively, so that
     * e.g. "C:/Site" and "c:/site" are recognised as the same directory on Windows.
     */
    private static function comparable(string $path): string
    {
        $real = realpath($path);

        return strtolower(Path::canonicalize($real !== false ? $real : $path));
    }
}
