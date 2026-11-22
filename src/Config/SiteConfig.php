<?php

declare(strict_types=1);

namespace Rezepte\Config;

use Symfony\Component\Filesystem\Path;

/**
 * Typed, validated view of config/site.dist.php merged with config/site.php.
 *
 * config/site.dist.php is the only place that defines default values; every
 * key is therefore required here.
 */
final readonly class SiteConfig
{
    /**
     * @param array<string, array{url: string, integrity: string}> $vendorAssets
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $language,
        public string $locale,
        public string $baseUrl,
        public RobotsPolicy $robots,
        public string $projectDir,
        public string $recipesDir,
        public string $outputDir,
        public string $templatesDir,
        public string $assetsDir,
        public ?string $defaultImage,
        public int $perPage,
        public int $newestCount,
        public string $headingIdPrefix,
        public string $permalinkTitle,
        public array $vendorAssets,
    ) {
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#', $baseUrl) !== 1) {
            throw new ConfigException(sprintf('Invalid base_url "%s".', $baseUrl));
        }
        if ($perPage < 1 || $newestCount < 0) {
            throw new ConfigException('pagination.per_page must be >= 1 and home.newest_count >= 0.');
        }
    }

    /**
     * Loads the versioned default configuration and optionally applies a
     * local configuration on top (only differing values are needed).
     */
    public static function fromFiles(
        string $distFile,
        ?string $localFile,
        string $projectDir,
        ?string $baseUrlOverride = null,
    ): self {
        $config = self::load($distFile);
        if ($localFile !== null && is_file($localFile)) {
            $config = self::merge($config, self::load($localFile));
        }

        return self::fromArray($config, $projectDir, $baseUrlOverride);
    }

    /**
     * @param array<mixed> $config complete configuration (all keys of config/site.dist.php)
     */
    public static function fromArray(array $config, string $projectDir, ?string $baseUrlOverride = null): self
    {
        $value = new ConfigReader($config);
        $path = static fn (string $key): string => Path::makeAbsolute($value->string("paths.$key"), $projectDir);

        return new self(
            name: $value->string('name'),
            description: $value->string('description'),
            language: $value->string('language'),
            locale: $value->string('locale'),
            baseUrl: rtrim($baseUrlOverride ?? $value->string('base_url'), '/'),
            robots: $value->enum('robots', RobotsPolicy::class),
            projectDir: Path::canonicalize($projectDir),
            recipesDir: $path('recipes'),
            outputDir: $path('output'),
            templatesDir: $path('templates'),
            assetsDir: $path('assets'),
            defaultImage: $value->optionalString('default_image'),
            perPage: $value->int('pagination.per_page'),
            newestCount: $value->int('home.newest_count'),
            headingIdPrefix: $value->string('markdown.heading_id_prefix'),
            permalinkTitle: $value->string('markdown.permalink_title'),
            vendorAssets: $value->vendorAssets('vendor'),
        );
    }

    /**
     * Associative arrays are merged recursively; lists (e.g. headings)
     * and scalar values are replaced completely.
     *
     * @param array<mixed> $base
     * @param array<mixed> $override
     *
     * @return array<mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && !array_is_list($value) && is_array($base[$key] ?? null)
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }

    /**
     * @return array<mixed>
     */
    public static function load(string $file): array
    {
        if (!is_file($file)) {
            throw new ConfigException(sprintf('Configuration file "%s" not found.', $file));
        }

        $config = require $file;
        if (!is_array($config)) {
            throw new ConfigException(sprintf('"%s" must return an array.', $file));
        }

        return $config;
    }
}
