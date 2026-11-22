<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

use Psr\Log\LoggerInterface;
use Rezepte\Generator\Pagination;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Discovers and loads all recipes below the recipes directory.
 *
 * Expected structure: <recipes>/<category>/<recipe>/{content.md, meta.json, image.jpg}
 * Categories and recipes are determined solely from the file system.
 */
final readonly class RecipeRepository
{
    public const string CONTENT_FILE = 'content.md';
    public const string META_FILE = 'meta.json';
    public const string DEFAULT_IMAGE = 'image.jpg';

    private const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    /** Would collide with the static files in /assets/. */
    private const string RESERVED_CATEGORY = 'assets';

    public function __construct(
        private string $recipesDir,
        private MetadataValidator $validator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Loads every recipe. All problems of all recipes are collected first and
     * reported together.
     *
     * @throws RecipeLoadException if at least one recipe is invalid
     */
    public function loadAll(): RecipeCollection
    {
        if (!is_dir($this->recipesDir)) {
            throw new \RuntimeException(sprintf('Recipes directory "%s" does not exist.', $this->recipesDir));
        }

        [$directories, $errors] = $this->discover();
        if ($directories === [] && $errors === []) {
            // Most likely a wrong paths.recipes setting – do not replace the website with an empty one.
            throw new \RuntimeException(sprintf('No recipes found in "%s". Please check paths.recipes in the configuration.', $this->recipesDir));
        }
        $recipes = [];
        foreach ($directories as $relativePath => $directory) {
            try {
                $recipes[] = $this->load($relativePath, $directory);
                $this->logger->info('Recipe loaded: {path}', ['path' => $relativePath]);
            } catch (InvalidRecipeException $exception) {
                $errors[] = $exception;
            }
        }

        if ($errors !== []) {
            throw new RecipeLoadException($errors);
        }

        $collection = new RecipeCollection($recipes);
        $this->checkCategoryNames($collection);

        return $collection;
    }

    /**
     * Recursively searches the recipes directory for recipe directories.
     * Directories starting with "." or "_" are skipped.
     *
     * @return array{array<string, string>, list<InvalidRecipeException>}
     *     relative path => absolute directory, plus misplaced or empty directories
     */
    private function discover(): array
    {
        // Skips hidden directories (".git") and drafts ("_entwurf") at any depth.
        $finder = Finder::create()->in($this->recipesDir)->ignoreDotFiles(true)->notPath('#(^|/)_#');

        $found = [];
        $errors = [];
        foreach ((clone $finder)->files()->name([self::CONTENT_FILE, self::META_FILE]) as $file) {
            $directory = Path::normalize($file->getRelativePath());
            if (substr_count($directory, '/') === 1) {
                $found[$directory] = $file->getPath();
            } else {
                $errors[$directory] ??= new InvalidRecipeException($directory, [
                    'Recipes must be located at <category>/<recipe>/.',
                ]);
            }
        }

        foreach ((clone $finder)->directories()->depth(1) as $candidate) {
            $directory = Path::normalize($candidate->getRelativePathname());
            if (!isset($found[$directory])) {
                $errors[$directory] = new InvalidRecipeException($directory, [
                    sprintf('Directory contains neither %s nor %s.', self::CONTENT_FILE, self::META_FILE),
                ]);
            }
        }
        ksort($found);

        return [$found, array_values($errors)];
    }

    /**
     * @throws InvalidRecipeException with every problem found in this recipe
     */
    private function load(string $relativePath, string $directory): Recipe
    {
        [$categorySlug, $slug] = explode('/', $relativePath, 2);
        $problems = $this->checkDirectoryNames($categorySlug, $slug);

        $meta = $this->collect($problems, fn (): RecipeMetadata => $this->loadMetadata(
            $relativePath,
            $directory . '/' . self::META_FILE,
        ));
        $markdown = $this->collect($problems, fn (): string => $this->loadMarkdown($directory . '/' . self::CONTENT_FILE));
        $image = $this->collect($problems, fn (): RecipeImage => $this->loadImage(
            $directory,
            $meta->image ?? self::DEFAULT_IMAGE,
        ));

        if ($problems !== [] || $meta === null || $markdown === null || $image === null) {
            throw new InvalidRecipeException($relativePath, $problems);
        }

        $attachments = $this->findAttachments($directory, basename($image->sourcePath));

        return new Recipe($categorySlug, $slug, $meta, $markdown, $image, $attachments);
    }

    /**
     * Further images in the recipe directory that content.md may embed, e.g. ![Teig](schritt-1.jpg).
     *
     * @return array<string, string> file name => absolute path
     */
    private function findAttachments(string $directory, string $mainImage): array
    {
        $images = Finder::create()->files()->in($directory)->depth(0)
            ->name('/\.(' . implode('|', RecipeImage::EXTENSIONS) . ')$/i')
            ->sortByName();
        $attachments = [];
        foreach ($images as $file) {
            if ($file->getFilename() !== $mainImage) {
                $attachments[$file->getFilename()] = $file->getPathname();
            }
        }

        return $attachments;
    }

    /**
     * Runs one loading step; its problems are added to $problems instead of aborting.
     *
     * @template T
     *
     * @param list<string> $problems
     * @param callable(): T $step
     *
     * @return T|null
     */
    private function collect(array &$problems, callable $step): mixed
    {
        try {
            return $step();
        } catch (RecipeProblemException $exception) {
            array_push($problems, ...$exception->problems);

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function checkDirectoryNames(string $categorySlug, string $slug): array
    {
        $problems = [];
        foreach ([$categorySlug, $slug] as $segment) {
            if (preg_match(self::SLUG_PATTERN, $segment) !== 1) {
                $problems[] = sprintf('Directory name "%s" is not URL-safe (allowed: a-z, 0-9, hyphen).', $segment);
            }
        }
        // Would collide with pagination URLs such as /brot/seite-2/.
        if (Pagination::isPageSegment($slug)) {
            $problems[] = sprintf('Directory name "%s" is reserved for pagination.', $slug);
        }
        if ($categorySlug === self::RESERVED_CATEGORY) {
            $problems[] = sprintf('The category name "%s" is reserved for static files.', self::RESERVED_CATEGORY);
        }

        return $problems;
    }

    private function loadMetadata(string $relativePath, string $file): RecipeMetadata
    {
        if (!is_file($file)) {
            throw new RecipeProblemException([sprintf('File %s is missing.', self::META_FILE)]);
        }
        try {
            $data = json_decode($this->read($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RecipeProblemException([
                sprintf('%s does not contain valid JSON: %s.', self::META_FILE, $exception->getMessage()),
            ]);
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new RecipeProblemException([sprintf('%s must contain a JSON object.', self::META_FILE)]);
        }

        foreach ($this->validator->unknownFields($data) as $field) {
            $this->logger->warning('{path}: unknown field "{field}" in meta.json is ignored.', [
                'path' => $relativePath,
                'field' => $field,
            ]);
        }

        return $this->validator->validate($data);
    }

    private function loadMarkdown(string $file): string
    {
        if (!is_file($file)) {
            throw new RecipeProblemException([sprintf('File %s is missing.', self::CONTENT_FILE)]);
        }
        $markdown = $this->read($file);
        if (!mb_check_encoding($markdown, 'UTF-8')) {
            throw new RecipeProblemException([sprintf('%s is not UTF-8 encoded.', self::CONTENT_FILE)]);
        }
        if (trim($markdown) === '') {
            throw new RecipeProblemException([sprintf('%s is empty.', self::CONTENT_FILE)]);
        }

        return $markdown;
    }

    private function loadImage(string $directory, string $fileName): RecipeImage
    {
        $file = $directory . '/' . $fileName;
        if (!is_file($file)) {
            throw new RecipeProblemException([sprintf('Image file %s is missing.', $fileName)]);
        }
        $info = @getimagesize($file);
        $extension = $info === false ? null : (RecipeImage::TYPES[$info[2]] ?? null);
        if ($info === false || $extension === null) {
            throw new RecipeProblemException([sprintf('%s is not a valid JPEG, PNG or WebP image.', $fileName)]);
        }

        return new RecipeImage($file, $extension, $info[0], $info[1]);
    }

    private function checkCategoryNames(RecipeCollection $collection): void
    {
        foreach ($collection->categories() as $category) {
            foreach ($category->recipes as $recipe) {
                if ($recipe->meta->category !== $category->name) {
                    $this->logger->warning(
                        '{path}: category "{actual}" differs from "{expected}"; using "{expected}".',
                        ['path' => $recipe->path(), 'actual' => $recipe->meta->category, 'expected' => $category->name],
                    );
                }
            }
        }
    }

    private function read(string $file): string
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            throw new RecipeProblemException([sprintf('File %s is not readable.', basename($file))]);
        }

        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

}
