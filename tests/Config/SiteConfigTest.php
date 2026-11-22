<?php

declare(strict_types=1);

namespace Rezepte\Tests\Config;

use PHPUnit\Framework\TestCase;
use Rezepte\Config\SiteConfig;

final class SiteConfigTest extends TestCase
{
    public function testLocalConfigOverridesDistConfig(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $local = tempnam(sys_get_temp_dir(), 'site') . '.php';
        file_put_contents($local, "<?php return ['base_url' => 'https://lokal.example.net/', 'name' => 'Test'];");

        try {
            $config = SiteConfig::fromFiles($projectDir . '/config/site.dist.php', $local, $projectDir);
        } finally {
            unlink($local);
        }

        self::assertSame('https://lokal.example.net', $config->baseUrl);
        self::assertSame('Test', $config->name);
        self::assertNotSame([], $config->vendorAssets, 'values that are not overridden are kept');
    }

    public function testDistConfigWorksWithoutLocalConfig(): void
    {
        $projectDir = dirname(__DIR__, 2);

        $config = SiteConfig::fromFiles($projectDir . '/config/site.dist.php', null, $projectDir);

        self::assertStringStartsWith('https://', $config->baseUrl);
    }

    public function testMergeReplacesListsAndMergesMaps(): void
    {
        $merged = SiteConfig::merge(
            ['paths' => ['recipes' => 'a', 'output' => 'b'], 'markdown' => ['headings' => ['Zutaten', 'Teig']]],
            ['paths' => ['output' => 'dist'], 'markdown' => ['headings' => ['Einkauf']]],
        );

        self::assertSame(['recipes' => 'a', 'output' => 'dist'], $merged['paths']);
        self::assertSame(['Einkauf'], $merged['markdown']['headings']);
    }
}
