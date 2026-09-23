<?php

namespace App\Services\Requirements;

use App\Models\Requirement;

final class RequirementVersionOptions
{
    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return Requirement::query()
            ->withVersion()
            ->distinct()
            ->pluck('version', 'version')
            ->all();
    }
}
