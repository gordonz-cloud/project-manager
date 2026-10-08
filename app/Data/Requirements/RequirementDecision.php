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
 * What a 提议/冲突 asks, in plain words: the question (difference), how things are now and which document that comes
 * from (now, now_source), what the new requirement wants (change), a warning (risk) and the answers to pick from.
 * Stored as JSON on requirements.decision; requirements:save validates it with errors().
 */
final readonly class RequirementDecision implements Castable
{
    /** Choice key of 保持现在, offered when no option keeps things as they are. */
    public const KEEP = 'keep';

    /** Choice key of 以后做, offered when no option puts it off. */
    public const LATER = 'later';

    private const TEXT_KEYS = ['now', 'change', 'difference', 'risk', 'impact', 'now_source'];

    /** Keys of the old 老板 flow: requirements:save says they are gone instead of storing them. */
    private const REMOVED_KEYS = ['why_boss'];

    private const REMOVED_OPTION_KEYS = ['record_as', 'record_date'];

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
        public ?string $nowSource = null,
    ) {}

    /**
     * The plain question every pending node has when nobody wrote a decision for it yet.
     */
    public static function fallbackFor(Requirement $requirement): self
    {
        if ($requirement->status === RequirementStatus::Conflict) {
            return new self(
                now: $requirement->supersedes->title ?? '（现行规则已不在）',
                change: $requirement->title,
                options: [new DecisionOption('A', '改成新说法', DecisionOutcome::Accept, '现行规则作废，按新说法做')],
                difference: '要不要把现行规则改成新说法？',
            );
        }

        return new self(
            now: '还没有这条规则',
            change: $requirement->title,
            options: [new DecisionOption('A', '同意', DecisionOutcome::Accept, '成为已定规则，进待做')],
            difference: '要不要加这条规则？',
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
            nowSource: $data['now_source'] ?? null,
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
            return ['decision must be an object {now, change, difference?, risk?, impact?, now_source?, options}.'];
        }

        $errors = [];

        foreach (array_diff(array_keys($raw), [...self::TEXT_KEYS, 'options']) as $key) {
            $errors[] = in_array($key, self::REMOVED_KEYS, true) ? "decision.{$key} 已移除（不再分老板定，全部 Gordon 定）。" : "decision has unknown key \"{$key}\".";
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

        if (! is_array($options) || ! array_is_list($options) || $options === []) {
            return [...$errors, 'decision.options must be a list of at least one option (保持现在 and 以后做 are always offered).'];
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
     * The answers on the card: the written options, plus 保持现在 and 以后做 when none of them already does that.
     *
     * @return list<DecisionOption>
     */
    public function choices(): array
    {
        $offers = fn (DecisionOutcome ...$outcomes): bool => array_filter($this->options, fn (DecisionOption $option): bool => in_array($option->outcome, $outcomes, true)) !== [];

        return [
            ...$this->options,
            ...($offers(DecisionOutcome::KeepCurrent, DecisionOutcome::Reject) ? [] : [new DecisionOption(self::KEEP, '保持现在', DecisionOutcome::KeepCurrent, '不改，照现在的做')]),
            ...($offers(DecisionOutcome::Later) ? [] : [new DecisionOption(self::LATER, '以后做', DecisionOutcome::Later, '这期不做，将来再看')]),
        ];
    }

    public function option(string $key): ?DecisionOption
    {
        foreach ($this->choices() as $option) {
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
            'now_source' => $this->nowSource,
            'change' => $this->change,
            'difference' => $this->difference,
            'risk' => $this->risk,
            'options' => array_map(fn (DecisionOption $option): array => $option->toArray(), $this->options),
            'impact' => $this->impact,
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

        return array_merge($errors, array_map(fn (string $key): string => in_array($key, self::REMOVED_OPTION_KEYS, true)
            ? "{$at}.{$key} 已移除（拍板一律记 Gordon）。"
            : "{$at} has unknown key \"{$key}\".", array_values(array_diff(array_keys($option), ['key', 'label', 'outcome', 'consequence', 'result_title', 'recommended']))));
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
