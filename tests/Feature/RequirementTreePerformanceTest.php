<?php

use App\Data\Requirements\DeliveryStatus;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Filament\Pages\RequirementTree;
use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('keeps delivery aggregation to one pass on search, next hit and selection as the tree grows [T192]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($project);
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided]);
    $tests = Test::factory()->count(3)->create(['project_id' => $project->id, 'last_result' => TestLastResult::Passed, 'auto' => TestAuto::Yes]);
    $addRules = function (int $count) use ($project, $goal, $tests): void {
        Requirement::factory()->count($count)->create([
            'project_id' => $project->id, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule,
            'status' => RequirementStatus::Decided, 'title' => 'Searchable rule',
        ])->each(fn (Requirement $rule) => $rule->tests()->attach($tests));
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
            $deliveryQueries = array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'live_tests_count')));

            expect($deliveryQueries)->toHaveCount(1);

            $query = $deliveryQueries[0];
            $plan = DB::select('EXPLAIN (ANALYZE) '.$query['query'], $query['bindings']);
            $aggregateLoops = [];
            foreach ($plan as $line) {
                if (preg_match('/Aggregate.*loops=(\d+)/', $line->{'QUERY PLAN'}, $match) === 1) {
                    $aggregateLoops[] = (int) $match[1];
                }
            }

            // Query count alone misses correlated counts executed once per requirement inside one SQL query.
            expect(array_sum($aggregateLoops))->toBeLessThanOrEqual(1);
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
    expect($delivery())->toBe(DeliveryStatus::Verified);

    $tests->firstOrFail()->update(['last_result' => TestLastResult::Failed]);
    $page->set('search', 'Searchable');
    expect($delivery())->toBe(DeliveryStatus::Failed);

    $rule->update(['title' => 'Updated rule']);
    $page->call('jumpToHit', 1)->assertSee('Updated rule');

    $rule->tests()->detach();
    $page->call('selectNode', $rule->number);
    expect($delivery())->toBe(DeliveryStatus::NotBuilt);

    $rule->commits()->attach(Commit::factory()->create(['project_id' => $project->id]));
    $page->call('jumpToHit', 1);
    expect($delivery())->toBe(DeliveryStatus::InProgress);

    $rule->tests()->attach($tests);
    $page->call('selectNode', $rule->number);
    expect($delivery())->toBe(DeliveryStatus::Failed);
});
