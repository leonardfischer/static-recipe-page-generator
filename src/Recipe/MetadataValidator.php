<?php

declare(strict_types=1);

namespace Rezepte\Recipe;

use DateTimeImmutable;

/**
 * Validates the raw data of a meta.json file and creates RecipeMetadata from it.
 */
final class MetadataValidator
{
    private const array KNOWN_FIELDS = [
        'title', 'description', 'category', 'tags', 'created', 'updated',
        'times', 'yield', 'difficulty', 'image',
    ];

    /** Fields of the old format; rejected with a hint to the new format. */
    private const array LEGACY_FIELDS = [
        'preparation_time' => 'times',
        'baking_time' => 'times',
        'resting_time' => 'times',
        'servings' => 'yield',
    ];

    private const int MAX_MINUTES = 10080; // one week

    /** @var list<string> */
    private array $problems = [];

    /**
     * @param array<mixed> $data
     *
     * @throws RecipeProblemException
     */
    public function validate(array $data): RecipeMetadata
    {
        $this->problems = [];

        $title = $this->requiredString($data, 'title', 150);
        $description = $this->requiredString($data, 'description', 500);
        $category = $this->requiredString($data, 'category', 100);
        $this->rejectLegacyFields($data);
        $times = $this->times($data);
        $yield = $this->yield($data);
        $difficulty = $this->difficulty($data);
        $tags = $this->tags($data);
        $created = $this->date($data, 'created');
        $updated = $this->date($data, 'updated');
        $image = $this->image($data);

        if ($created !== null && $updated !== null && $updated < $created) {
            $this->problems[] = '"updated" is earlier than "created".';
        }

        if ($this->problems !== [] || $times === [] || $yield === null || $difficulty === null) {
            throw new RecipeProblemException($this->problems);
        }

        return new RecipeMetadata(
            title: $title,
            description: $description,
            category: $category,
            times: $times,
            yield: $yield,
            difficulty: $difficulty,
            tags: $tags,
            created: $created,
            updated: $updated,
            image: $image,
        );
    }

    /**
     * Fields that are neither known nor legacy fields (legacy fields cause an error).
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public function unknownFields(array $data): array
    {
        return array_values(array_diff(
            array_map('strval', array_keys($data)),
            self::KNOWN_FIELDS,
            array_keys(self::LEGACY_FIELDS),
        ));
    }

    /**
     * @param array<mixed> $data
     */
    private function requiredString(array $data, string $field, int $maxLength): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->problems[] = sprintf('Required field "%s" is missing or not a non-empty string.', $field);

            return '';
        }
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            $this->problems[] = sprintf('"%s" is longer than %d characters.', $field, $maxLength);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function rejectLegacyFields(array $data): void
    {
        foreach (self::LEGACY_FIELDS as $legacy => $replacement) {
            if (array_key_exists($legacy, $data)) {
                $this->problems[] = sprintf(
                    'Field "%s" no longer exists – please use "%s" instead (see README).',
                    $legacy,
                    $replacement,
                );
            }
        }
    }

    /**
     * "times": [{"type": "preparation", "minutes": 30, "label": "…"}, …]
     *
     * @param array<mixed> $data
     *
     * @return list<RecipeTime>
     */
    private function times(array $data): array
    {
        $value = $data['times'] ?? null;
        if (!is_array($value) || $value === [] || !array_is_list($value)) {
            $this->problems[] = 'Required field "times" is missing or not a non-empty list of time segments.';

            return [];
        }

        $times = [];
        foreach ($value as $index => $entry) {
            $time = $this->time($entry, sprintf('times[%d]', $index));
            if ($time !== null) {
                $times[] = $time;
            }
        }

        return count($times) === count($value) ? $times : [];
    }

    private function time(mixed $entry, string $path): ?RecipeTime
    {
        if (!is_array($entry) || array_is_list($entry)) {
            $this->problems[] = sprintf('%s must be an object with "type" and "minutes".', $path);

            return null;
        }
        $problemCount = count($this->problems);

        $this->rejectUnknownKeys($entry, ['type', 'minutes', 'label'], $path);
        $type = is_string($entry['type'] ?? null) ? TimeType::tryFrom($entry['type']) : null;
        if ($type === null) {
            $this->problems[] = sprintf(
                '%s: "type" is missing or invalid (allowed: %s).',
                $path,
                implode(', ', TimeType::values()),
            );
        }
        $minutes = $entry['minutes'] ?? null;
        if (!is_int($minutes) || $minutes < 1 || $minutes > self::MAX_MINUTES) {
            $this->problems[] = sprintf('%s: "minutes" must be an integer between 1 and %d.', $path, self::MAX_MINUTES);
        }
        $label = $this->optionalText($entry, 'label', $path, 50);

        if (count($this->problems) > $problemCount || $type === null || !is_int($minutes)) {
            return null;
        }

        return new RecipeTime($type, $minutes, $label);
    }

    /**
     * "yield": {"amount": 1, "unit": "Brot"}
     *
     * @param array<mixed> $data
     */
    private function yield(array $data): ?RecipeYield
    {
        $value = $data['yield'] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            $this->problems[] = 'Required field "yield" is missing or not an object with "amount" (and optionally "unit").';

            return null;
        }
        $problemCount = count($this->problems);

        $this->rejectUnknownKeys($value, ['amount', 'unit'], 'yield');
        $amount = $value['amount'] ?? null;
        if (!is_int($amount) || $amount < 1 || $amount > 1000) {
            $this->problems[] = 'yield: "amount" must be an integer between 1 and 1000.';
        }
        $unit = $this->optionalText($value, 'unit', 'yield', 30);

        if (count($this->problems) > $problemCount || !is_int($amount)) {
            return null;
        }

        return new RecipeYield($amount, $unit);
    }

    /**
     * @param array<mixed> $object
     * @param list<string> $allowedKeys
     */
    private function rejectUnknownKeys(array $object, array $allowedKeys, string $path): void
    {
        foreach (array_diff(array_map('strval', array_keys($object)), $allowedKeys) as $key) {
            $this->problems[] = sprintf('%s: unknown key "%s".', $path, $key);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function optionalText(array $data, string $key, string $path, int $maxLength): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        $value = $data[$key];
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            $this->problems[] = sprintf('%s: "%s" must be a non-empty string with at most %d characters.', $path, $key, $maxLength);

            return null;
        }

        return trim($value);
    }

    /**
     * @param array<mixed> $data
     */
    private function difficulty(array $data): ?Difficulty
    {
        $value = $data['difficulty'] ?? null;
        $difficulty = is_string($value) ? Difficulty::tryFrom(mb_strtolower(trim($value))) : null;
        if ($difficulty === null) {
            $this->problems[] = sprintf(
                'Required field "difficulty" is missing or invalid (allowed: %s).',
                implode(', ', Difficulty::values()),
            );
        }

        return $difficulty;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function tags(array $data): array
    {
        $value = $data['tags'] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            $this->problems[] = '"tags" must be a list of strings.';

            return [];
        }
        $tags = [];
        foreach ($value as $tag) {
            if (!is_string($tag) || trim($tag) === '') {
                $this->problems[] = '"tags" may only contain non-empty strings.';

                return [];
            }
            $tags[] = trim($tag);
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param array<mixed> $data
     */
    private function date(array $data, string $field): ?DateTimeImmutable
    {
        $value = $data[$field] ?? null;
        if ($value === null) {
            return null;
        }
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->problems[] = sprintf('"%s" must be a valid date in the format YYYY-MM-DD.', $field);

            return null;
        }

        return $date;
    }

    /**
     * @param array<mixed> $data
     */
    private function image(array $data): ?string
    {
        $value = $data['image'] ?? null;
        if ($value === null) {
            return null;
        }
        $extension = is_string($value) ? strtolower(pathinfo($value, PATHINFO_EXTENSION)) : '';
        if (
            !is_string($value)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value) !== 1
            || !in_array($extension, RecipeImage::EXTENSIONS, true)
        ) {
            $this->problems[] = sprintf(
                '"image" must be a plain file name inside the recipe directory (extensions: %s).',
                implode(', ', RecipeImage::EXTENSIONS),
            );

            return null;
        }

        return $value;
    }
}
