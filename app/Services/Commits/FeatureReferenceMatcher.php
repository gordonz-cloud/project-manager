<?php

namespace App\Services\Commits;

use App\Models\Feature;
use Illuminate\Support\Collection;

class FeatureReferenceMatcher
{
    private const string FEATURE_PATTERN = '/(?:feature\s*#?|功能\s*)(\d+)/iu';

    /**
     * @param  Collection<int, Feature>  $featuresByNumber
     */
    public function match(string $subject, ?string $body, Collection $featuresByNumber): ?Feature
    {
        if (preg_match(self::FEATURE_PATTERN, $subject."\n".$body, $matches) !== 1) {
            return null;
        }

        return $featuresByNumber->get((int) $matches[1]);
    }
}
