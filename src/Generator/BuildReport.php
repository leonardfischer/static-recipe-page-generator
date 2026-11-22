<?php

declare(strict_types=1);

namespace Rezepte\Generator;

final readonly class BuildReport
{
    public function __construct(
        public int $recipes,
        public int $categories,
        public int $pages,
        public int $images,
        public int $files,
        public float $seconds,
        public bool $dryRun,
    ) {
    }
}
