<?php

use App\Data\Requirements\DeliveryStatus;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Filament\Pages\RequirementTree;
use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('keeps delivery queries constant on search, next hit and selection as the tree grows [T192]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($project);
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided]);
    $addRules = function (int $count) use ($project, $goal): void {
        Requirement::factory()->count($count)->create([
            'project_id' => $project->id, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule,
            'status' => RequirementStatus::Decided, 'title' => 'Searchable rule',
        ]);
    };
    $requests = function () use ($goal): array {
        $page = Livewire::test(RequirementTree::class);
        $queryCounts = [];

        foreach (['search', 'next', 'select'] as $action) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            match ($action) {
                'search' => $page->set('search', 'Searchable'),
                'next' => $page->call('jumpToHit', 1),
                'select' => $page->call('selectNode', $goal->number),
            };
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $queryCounts[$action] = count($queries);
            $deliveryQueries = array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'as "commits_exists"')));
            expect($deliveryQueries)->toHaveCount(1);
        }

        return $queryCounts;
    };

    $addRules(2);
    $few = $requests();
    $addRules(24);

    expect($requests())->toBe($few);

    $rule = Requirement::where('parent_id', $goal->id)->firstOrFail();
    $page = Livewire::test(RequirementTree::class);
    $delivery = fn (): DeliveryStatus => $page->get('nodesById')[$rule->id]->delivery;
    expect($delivery())->toBe(DeliveryStatus::NotBuilt);

    $rule->commits()->attach(Commit::factory()->create(['project_id' => $project->id]));
    $page->set('search', 'Searchable');
    expect($delivery())->toBe(DeliveryStatus::AwaitingAcceptance);

    $rule->update(['accepted_at' => now()]);
    $page->call('selectNode', $rule->number);
    expect($delivery())->toBe(DeliveryStatus::Verified);

    $rule->update(['title' => 'Updated rule']);
    $page->call('jumpToHit', 1)->assertSee('Updated rule');
    expect($delivery())->toBe(DeliveryStatus::AwaitingAcceptance);

    $rule->commits()->detach();
    $page->call('selectNode', $rule->number);
    expect($delivery())->toBe(DeliveryStatus::NotBuilt);
});
