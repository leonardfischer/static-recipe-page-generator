<?php

declare(strict_types=1);

namespace Rezepte\Support;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Provides front-end libraries (Bootstrap, jQuery) locally.
 *
 * Each file is downloaded from the CDN once, verified against its SRI hash and
 * stored in the cache directory (assets/vendor/). Later builds only use the
 * local copy – the finished website does not depend on a CDN.
 */
final readonly class VendorAssets
{
    /**
     * @param array<string, array{url: string, integrity: string}> $assets
     */
    public function __construct(
        private array $assets,
        private string $cacheDir,
        private LoggerInterface $logger,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return array<string, string> file name => local path
     */
    public function resolveAll(bool $allowDownload = true): array
    {
        $paths = [];
        foreach ($this->assets as $name => $asset) {
            $path = $this->resolve($name, $asset['url'], $asset['integrity'], $allowDownload);
            if ($path !== null) {
                $paths[$name] = $path;
            }
        }

        return $paths;
    }

    private function resolve(string $name, string $url, string $integrity, bool $allowDownload): ?string
    {
        $path = Path::join($this->cacheDir, $name);
        if (is_file($path) && self::matches((string) file_get_contents($path), $integrity)) {
            return $path;
        }
        if (!$allowDownload) {
            $this->logger->warning('{name} is not available locally yet and would be downloaded during a build.', ['name' => $name]);

            return null;
        }

        $this->logger->notice('Downloading {name} from {url}', ['name' => $name, 'url' => $url]);
        $content = $this->download($url);
        if (!self::matches($content, $integrity)) {
            throw new \RuntimeException(sprintf('Integrity check for "%s" failed (%s).', $name, $url));
        }
        $this->filesystem->dumpFile($path, $content);

        return $path;
    }

    private function download(string $url): string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 30, 'follow_location' => 1, 'user_agent' => 'recipe-site-builder'],
        ]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false || $content === '') {
            throw new \RuntimeException(sprintf('Download of "%s" failed.', $url));
        }

        return $content;
    }

    /**
     * Checks a Subresource Integrity value such as "sha384-…".
     */
    public static function matches(string $content, string $integrity): bool
    {
        [$algorithm, $expected] = explode('-', $integrity, 2) + [1 => ''];
        if (!in_array($algorithm, ['sha256', 'sha384', 'sha512'], true)) {
            return false;
        }

        return hash_equals($expected, base64_encode(hash($algorithm, $content, true)));
    }
}
