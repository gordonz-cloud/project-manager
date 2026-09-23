<?php

namespace App\Services\RequestReplies;

use App\Data\RequestReplies\ParsedEntry;
use App\Enums\FeatureTrigger;

/**
 * Finds the real requests named in a feature's free-text `entry`: "POST /x" routes,
 * "artisan foo:bar" signatures and "命令 ClassName" commands. Everything else
 * (class names, notes, UI work) is not a request and yields nothing.
 */
class FeatureEntryParser
{
    private const ROUTE = '/\b(GET|POST|PUT|PATCH|DELETE)\s+([A-Za-z0-9_\-\/{}\[\]\.\\\\]+)/';

    private const ARTISAN = '/artisan\s+([a-z][\w-]*:[\w:-]+)/';

    private const COMMAND_CLASS = '/命令\s*([A-Z][A-Za-z]+)/u';

    /**
     * @param  list<string>|null  $triggers  the feature's triggers; a scheduled feature's commands run on the scheduler
     * @return list<ParsedEntry>
     */
    public function parse(?string $entry, ?array $triggers = []): array
    {
        $entry ??= '';
        $found = [];

        preg_match_all(self::ROUTE, $entry, $routes, PREG_SET_ORDER);

        foreach ($routes as [, $method, $path]) {
            $path = rtrim(str_replace('\\', '', $path), '.');

            if (! str_contains($path, '/') || str_starts_with($path, '.')) {
                continue;
            }

            $path = '/'.ltrim($path, '/');
            $found[] = new ParsedEntry(str_starts_with($path, '/webhooks') ? FeatureTrigger::Webhook : FeatureTrigger::Http, $method, $path);
        }

        $commandTrigger = $this->isScheduled($triggers ?? []) ? FeatureTrigger::Scheduler : FeatureTrigger::Cli;

        foreach ([self::ARTISAN, self::COMMAND_CLASS] as $pattern) {
            preg_match_all($pattern, $entry, $commands);

            foreach ($commands[1] as $command) {
                $found[] = new ParsedEntry($commandTrigger, null, $command);
            }
        }

        $unique = [];

        foreach ($found as $parsed) {
            $unique[$parsed->title()] ??= $parsed;
        }

        return array_values($unique);
    }

    /**
     * @param  list<string>  $triggers
     */
    private function isScheduled(array $triggers): bool
    {
        foreach ($triggers as $trigger) {
            if (mb_strtolower($trigger) === 'scheduler' || $trigger === '调度') {
                return true;
            }
        }

        return false;
    }
}
