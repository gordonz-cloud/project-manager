<?php

namespace App\Data\Requirements;

use App\Models\Commit;
use App\Models\Requirement;
use App\Models\RequirementRevision;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Recent activity on one requirement: its revisions and its commits, each newest first.
 */
final readonly class RequirementChangeGroup
{
    /**
     * @param  Collection<int, RequirementRevision>  $revisions
     * @param  Collection<int, Commit>  $commits
     */
    public function __construct(
        public Requirement $requirement,
        public Collection $revisions,
        public Collection $commits,
    ) {}

    public function latestAt(): ?CarbonInterface
    {
        return collect([$this->revisions->first()?->created_at, $this->commits->first()?->committed_at])->filter()->max();
    }
}
