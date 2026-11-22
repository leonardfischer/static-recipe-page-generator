<?php

declare(strict_types=1);

namespace Rezepte\Config;

/**
 * Typed access to nested configuration values via dot paths ("paths.recipes").
 * Every getter throws a ConfigException naming the key if the value is missing or invalid.
 */
final readonly class ConfigReader
{
    /**
     * @param array<mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    public function string(string $key): string
    {
        $value = $this->get($key);
        if (!is_string($value) || trim($value) === '') {
            throw $this->invalid($key, 'a non-empty string');
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        return $this->get($key) === null ? null : $this->string($key);
    }

    public function int(string $key): int
    {
        $value = $this->get($key);
        if (!is_int($value)) {
            throw $this->invalid($key, 'an integer');
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T
     */
    public function enum(string $key, string $enumClass): \BackedEnum
    {
        $value = $this->get($key);
        $case = is_string($value) || is_int($value) ? $enumClass::tryFrom($value) : null;
        if ($case === null) {
            $allowed = array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enumClass::cases());

            throw $this->invalid($key, 'one of: ' . implode(', ', $allowed));
        }

        return $case;
    }

    /**
     * @return array<string, array{url: string, integrity: string}>
     */
    public function vendorAssets(string $key): array
    {
        $value = $this->get($key);
        if (!is_array($value)) {
            throw $this->invalid($key, 'a map of file names to {url, integrity}');
        }
        foreach ($value as $name => $asset) {
            if (
                !is_string($name)
                || !is_array($asset)
                || !is_string($asset['url'] ?? null)
                || !is_string($asset['integrity'] ?? null)
            ) {
                throw $this->invalid($key, 'a map of file names to {url, integrity}');
            }
        }

        /** @var array<string, array{url: string, integrity: string}> $value */
        return $value;
    }

    private function get(string $key): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new ConfigException(sprintf('Configuration value "%s" is missing.', $key));
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function invalid(string $key, string $expected): ConfigException
    {
        return new ConfigException(sprintf('Configuration value "%s" must be %s.', $key, $expected));
    }
}
