<?php

declare(strict_types=1);

namespace Rezepte\Tests;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Creates recipe directories in a temporary folder and cleans up afterwards.
 */
trait RecipeFixtures
{
    private string $tempDir;

    protected function createTempDir(): string
    {
        $this->tempDir = Path::join(sys_get_temp_dir(), 'rezepte-test-' . bin2hex(random_bytes(6)));
        (new Filesystem())->mkdir($this->tempDir);

        return $this->tempDir;
    }

    protected function removeTempDir(): void
    {
        if (isset($this->tempDir)) {
            (new Filesystem())->remove($this->tempDir);
        }
    }

    /**
     * @param array<string, mixed>|string|null $meta null = no meta.json, string = raw content
     */
    protected function createRecipe(
        string $baseDir,
        string $path,
        array|string|null $meta = [],
        ?string $markdown = "# Titel\n\nText",
        ?string $image = 'image.jpg',
    ): string {
        $filesystem = new Filesystem();
        $directory = Path::join($baseDir, $path);
        $filesystem->mkdir($directory);

        if ($meta !== null) {
            $json = is_string($meta)
                ? $meta
                : json_encode($meta + self::validMeta(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $filesystem->dumpFile($directory . '/meta.json', $json);
        }
        if ($markdown !== null) {
            $filesystem->dumpFile($directory . '/content.md', $markdown);
        }
        if ($image !== null) {
            $gd = imagecreatetruecolor(4, 3);
            imagejpeg($gd, $directory . '/' . $image);
        }

        return $directory;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function validMeta(): array
    {
        return [
            'title' => 'Brioche',
            'description' => 'Ein lockeres, leicht süßliches Brioche.',
            'category' => 'Brot',
            'tags' => ['Hefeteig', 'Frühstück'],
            'created' => '2026-11-15',
            'updated' => '2026-11-15',
            'times' => [
                ['type' => 'preparation', 'minutes' => 30],
                ['type' => 'resting', 'minutes' => 120, 'label' => 'Gehzeit'],
                ['type' => 'cooking', 'minutes' => 35, 'label' => 'Backen'],
            ],
            'yield' => ['amount' => 1, 'unit' => 'Brioche'],
            'difficulty' => 'mittel',
        ];
    }
}
