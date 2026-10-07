<?php

namespace App\Data\Requirements;

use App\Enums\DecisionOutcome;
use App\Enums\RequirementStatus;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * What a 提议/冲突 asks, in plain words: how things are now and why, what would change, the difference, the risk,
 * and the answers to pick from. Stored as JSON on requirements.decision; requirements:save validates it with errors().
 */
final readonly class RequirementDecision implements Castable
{
    private const TEXT_KEYS = ['now', 'change', 'difference', 'risk', 'impact', 'why_boss'];

    /**
     * @param  list<DecisionOption>  $options
     */
    public function __construct(
        public string $now,
        public string $change,
        public array $options,
        public ?string $difference = null,
        public ?string $risk = null,
        public ?string $impact = null,
        public ?string $whyBoss = null,
    ) {}

    /**
     * The two plain answers every pending node has when nobody wrote a decision for it yet.
     */
    public static function fallbackFor(Requirement $requirement): self
    {
        if ($requirement->status === RequirementStatus::Conflict) {
            return new self(
                now: $requirement->supersedes->title ?? '（现行规则已不在）',
                change: $requirement->title,
                options: [
                    new DecisionOption('A', '改成新说法', DecisionOutcome::Accept, '现行规则作废，按新说法做'),
                    new DecisionOption('B', '保持现状', DecisionOutcome::KeepCurrent, '这条改动作废，现行规则不变'),
                ],
            );
        }

        return new self(
            now: '还没有这条规则',
            change: $requirement->title,
            options: [
                new DecisionOption('A', '同意', DecisionOutcome::Accept, '成为已定规则，进待做'),
                new DecisionOption('B', '不要', DecisionOutcome::Reject, '这条作废'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data  already passed errors()
     */
    public static function fromArray(array $data): self
    {
        return new self(
            now: $data['now'],
            change: $data['change'],
            options: array_values(array_map(fn (array $option): DecisionOption => new DecisionOption(
                key: $option['key'],
                label: $option['label'],
                outcome: DecisionOutcome::from($option['outcome']),
                consequence: $option['consequence'],
                resultTitle: $option['result_title'] ?? null,
                recommended: $option['recommended'] ?? false,
            ), $data['options'])),
            difference: $data['difference'] ?? null,
            risk: $data['risk'] ?? null,
            impact: $data['impact'] ?? null,
            whyBoss: $data['why_boss'] ?? null,
        );
    }

    /**
     * Why $raw is not a decision; empty when it is one.
     *
     * @return list<string>
     */
    public static function errors(mixed $raw): array
    {
        if (! is_array($raw) || array_is_list($raw)) {
            return ['decision must be an object {now, change, difference?, risk?, impact?, why_boss?, options}.'];
        }

        $errors = [];

        foreach (array_diff(array_keys($raw), [...self::TEXT_KEYS, 'options']) as $key) {
            $errors[] = "decision has unknown key \"{$key}\".";
        }

        foreach (self::TEXT_KEYS as $key) {
            if (isset($raw[$key]) && ! is_string($raw[$key])) {
                $errors[] = "decision.{$key} must be a string.";
            }
        }

        foreach (['now', 'change'] as $key) {
            if (blank($raw[$key] ?? null)) {
                $errors[] = "decision.{$key} is required.";
            }
        }

        $options = $raw['options'] ?? null;

        if (! is_array($options) || ! array_is_list($options) || count($options) < 2) {
            return [...$errors, 'decision.options must be a list of at least two options.'];
        }

        $keys = [];

        foreach ($options as $index => $option) {
            $errors = [...$errors, ...self::optionErrors($option, $index)];
            $keys[] = is_array($option) ? ($option['key'] ?? null) : null;
        }

        if (count(array_unique(array_filter($keys, 'is_string'))) !== count($keys)) {
            $errors[] = 'decision.options keys must be unique.';
        }

        if (count(array_filter($options, fn (mixed $option): bool => is_array($option) && ($option['recommended'] ?? false) === true)) > 1) {
            $errors[] = 'decision.options may mark at most one option recommended.';
        }

        return $errors;
    }

    /**
     * Whether $text tells what 现在 already tells: one contains the other, they share at least two runs of 8+ characters,
     * or 现在 already names a rule number (#N) that $text cites.
     */
    public function retells(string $text): bool
    {
        preg_match_all('/#\d+/', $text, $citedRules);

        foreach ($citedRules[0] as $rule) {
            if (preg_match('/'.preg_quote($rule, '/').'(?!\d)/', $this->now)) {
                return true;
            }
        }

        $isContained = str_contains($text, $this->now) || (mb_strlen($text) >= 8 && str_contains($this->now, $text));

        return $isContained || $this->sharedRuns($text) >= 2;
    }

    /**
     * Number of separate stretches of 现在, each 8+ characters long, that also appear in $text.
     */
    private function sharedRuns(string $text, int $minimum = 8): int
    {
        $length = mb_strlen($this->now);
        $runs = 0;

        for ($start = 0; $start + $minimum <= $length;) {
            if (! str_contains($text, mb_substr($this->now, $start, $minimum))) {
                $start++;

                continue;
            }

            $end = $start + $minimum;

            while ($end < $length && str_contains($text, mb_substr($this->now, $start, $end - $start + 1))) {
                $end++;
            }

            $runs++;
            $start = $end;
        }

        return $runs;
    }

    public function option(string $key): ?DecisionOption
    {
        foreach ($this->options as $option) {
            if ($option->key === $key) {
                return $option;
            }
        }

        return null;
    }

    public function recommended(): ?DecisionOption
    {
        foreach ($this->options as $option) {
            if ($option->recommended) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'now' => $this->now,
            'change' => $this->change,
            'difference' => $this->difference,
            'risk' => $this->risk,
            'options' => array_map(fn (DecisionOption $option): array => $option->toArray(), $this->options),
            'impact' => $this->impact,
            'why_boss' => $this->whyBoss,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return list<string>
     */
    private static function optionErrors(mixed $option, int $index): array
    {
        $at = "decision.options[{$index}]";

        if (! is_array($option)) {
            return ["{$at} must be an object."];
        }

        $errors = [];

        foreach (['key', 'label', 'consequence'] as $key) {
            if (! is_string($option[$key] ?? null) || blank($option[$key])) {
                $errors[] = "{$at}.{$key} is required.";
            }
        }

        if (in_array($option['key'] ?? null, RequirementDecisionDraft::RESERVED_CHOICES, true)) {
            $errors[] = "{$at}.key cannot be ".implode('/', RequirementDecisionDraft::RESERVED_CHOICES).'.';
        }

        $outcome = is_string($option['outcome'] ?? null) ? DecisionOutcome::tryFrom($option['outcome']) : null;

        if ($outcome === null) {
            $errors[] = "{$at}.outcome must be one of ".implode('/', array_column(DecisionOutcome::cases(), 'value')).'.';
        }

        if (isset($option['result_title']) && ! is_string($option['result_title'])) {
            $errors[] = "{$at}.result_title must be a string.";
        }

        if ($outcome === DecisionOutcome::Custom && blank($option['result_title'] ?? null)) {
            $errors[] = "{$at}: a custom outcome needs result_title.";
        }

        if (isset($option['recommended']) && ! is_bool($option['recommended'])) {
            $errors[] = "{$at}.recommended must be true or false.";
        }

        return array_merge($errors, array_map(fn (string $key): string => "{$at} has unknown key \"{$key}\".", array_values(array_diff(array_keys($option), ['key', 'label', 'outcome', 'consequence', 'result_title', 'recommended']))));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return CastsAttributes<self|null, self|array<string, mixed>|null>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ?RequirementDecision
            {
                return $value === null ? null : RequirementDecision::fromArray(json_decode($value, true));
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                $data = $value instanceof RequirementDecision ? $value->toArray() : $value;

                return $data === null ? null : (string) json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        };
    }
}
