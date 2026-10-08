<?php

namespace App\Data\Requirements;

/**
 * Everything said about one requirement over time, newest first, with the code's current behaviour last.
 */
final readonly class RequirementTimeline
{
    private const KEYS = ['date', 'who', 'where', 'said', 'current', 'conflict_with', 'related'];

    /**
     * @param  list<TimelineEntry>  $entries
     */
    public function __construct(public array $entries) {}

    /**
     * @param  list<array<string, mixed>>  $data  already passed errors()
     */
    public static function fromArray(array $data): self
    {
        return self::sorted(array_map(fn (array $entry): TimelineEntry => TimelineEntry::fromArray($entry), $data));
    }

    /**
     * Newest first; entries without a date (the code as it is now) last.
     *
     * @param  list<TimelineEntry>  $entries
     */
    public static function sorted(array $entries): self
    {
        usort($entries, fn (TimelineEntry $a, TimelineEntry $b): int => [$a->isCode(), $b->date ?? ''] <=> [$b->isCode(), $a->date ?? '']);

        return new self($entries);
    }

    /**
     * Why $raw is not a timeline; empty when it is one.
     *
     * @return list<string>
     */
    public static function errors(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            return ['timeline must be a list of {date, who, where?, said, current?, conflict_with?, related?}.'];
        }

        $errors = [];

        foreach ($raw as $index => $entry) {
            $errors = [...$errors, ...self::entryErrors($entry, $index)];
        }

        if (count(array_filter($raw, fn (mixed $entry): bool => is_array($entry) && ($entry['current'] ?? false) === true)) > 1) {
            $errors[] = 'timeline may mark at most one entry current.';
        }

        return $errors;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * The code's behaviour, when it was recorded as differing from the saying in force.
     */
    public function codeDisagrees(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->isCode() && filled($entry->conflictWith)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function entryErrors(mixed $entry, int $index): array
    {
        $at = "timeline[{$index}]";

        if (! is_array($entry) || array_is_list($entry)) {
            return ["{$at} must be an object."];
        }

        $errors = array_map(fn (string $key): string => "{$at} has unknown key \"{$key}\".", array_values(array_diff(array_keys($entry), self::KEYS)));

        if (isset($entry['date']) && (! is_string($entry['date']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['date']))) {
            $errors[] = "{$at}.date must be YYYY-MM-DD.";
        }

        if (! in_array($entry['who'] ?? null, TimelineEntry::WHO, true)) {
            $errors[] = "{$at}.who must be one of ".implode('/', TimelineEntry::WHO).'.';
        }

        if (! isset($entry['date']) && ($entry['who'] ?? null) !== TimelineEntry::CODE) {
            $errors[] = "{$at}.date is required (only 代码现状 may leave it out).";
        }

        if (! is_string($entry['said'] ?? null) || blank($entry['said'])) {
            $errors[] = "{$at}.said is required.";
        }

        foreach (['where', 'conflict_with'] as $key) {
            if (isset($entry[$key]) && ! is_string($entry[$key])) {
                $errors[] = "{$at}.{$key} must be a string.";
            }
        }

        foreach (['current', 'related'] as $key) {
            if (isset($entry[$key]) && ! is_bool($entry[$key])) {
                $errors[] = "{$at}.{$key} must be true or false.";
            }
        }

        return $errors;
    }
}
