<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

use DateTimeImmutable;

/**
 * Validated contents of a meta.json file.
 */
final readonly class RecipeMetadata
{
    /**
     * @param list<RecipeTime> $times time segments in source order (at least one)
     * @param list<string> $tags
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $category,
        public array $times,
        public RecipeYield $yield,
        public Difficulty $difficulty,
        public array $tags = [],
        public ?DateTimeImmutable $created = null,
        public ?DateTimeImmutable $updated = null,
        public ?string $image = null,
    ) {
        if ($times === []) {
            throw new \InvalidArgumentException('A recipe needs at least one time segment.');
        }
    }

    /**
     * Total time in minutes across all time segments.
     */
    public function totalTime(): int
    {
        return array_sum(array_map(static fn (RecipeTime $time): int => $time->minutes, $this->times));
    }

    /**
     * Sum of all segments of one type (0 if there are none).
     */
    public function minutesOf(TimeType $type): int
    {
        $minutes = 0;
        foreach ($this->times as $time) {
            if ($time->type === $type) {
                $minutes += $time->minutes;
            }
        }

        return $minutes;
    }

    /**
     * Date of the last modification, falling back to the creation date.
     */
    public function lastModified(): ?DateTimeImmutable
    {
        return $this->updated ?? $this->created;
    }
}
