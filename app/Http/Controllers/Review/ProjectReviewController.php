<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectReviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Project $project, string $tree): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->email_verified_at !== null && $user->canAccessTenant($project), 403);

        $model = $tree === 'requirements' ? Requirement::class : Test::class;
        $nodes = $model::query()->where('project_id', $project->id)->orderBy('number')->get();

        return view('review.project', [
            'project' => $project,
            'tree' => $tree,
            'nodes' => $nodes->groupBy(fn ($node): int => $node->parent_id ?? 0),
            'count' => $nodes->count(),
        ]);
    }
}
