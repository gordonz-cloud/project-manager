<?php

namespace App\Data\Requirements;

/**
 * One thing said about a requirement: when, by whom (one of WHO), where, and what. current = the saying in force now;
 * conflictWith = which other saying it contradicts; related = said about a neighbouring matter, shown for context.
 */
final readonly class TimelineEntry
{
    public const WHO = ['老板拍板', '老板文档', 'Gordon 拍板', '已被取代的旧规则', '代码现状', '其他'];

    public const CODE = '代码现状';

    public function __construct(
        public ?string $date,
        public string $who,
        public string $said,
        public ?string $where = null,
        public bool $current = false,
        public ?string $conflictWith = null,
        public bool $related = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  already passed RequirementTimeline::errors()
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'] ?? null,
            who: $data['who'],
            said: $data['said'],
            where: $data['where'] ?? null,
            current: $data['current'] ?? false,
            conflictWith: $data['conflict_with'] ?? null,
            related: $data['related'] ?? false,
        );
    }

    public function isCode(): bool
    {
        return $this->who === self::CODE;
    }

    /**
     * Colour of the who-badge: the boss, Gordon, the code, everything else.
     */
    public function badgeClasses(): string
    {
        return match ($this->who) {
            '老板拍板', '老板文档' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            'Gordon 拍板' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
            self::CODE => 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
        };
    }

    /**
     * Gordon is "你" on his own page.
     */
    public function whoLabel(): string
    {
        return $this->who === 'Gordon 拍板' ? '你拍板' : $this->who;
    }
}
