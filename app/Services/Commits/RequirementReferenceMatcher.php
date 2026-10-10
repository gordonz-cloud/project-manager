<?php

namespace App\Services\Commits;

class RequirementReferenceMatcher
{
    /** "R123" anywhere in the message; not inside a word or a ref like PR123 or ABC-R1. */
    private const string RULE_PATTERN = '/(?<![\w-])R(\d+)\b/';

    /**
     * Every rule number the message names, each once, in order.
     *
     * @return list<int>
     */
    public function numbers(string $subject, ?string $body): array
    {
        preg_match_all(self::RULE_PATTERN, $subject."\n".$body, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
