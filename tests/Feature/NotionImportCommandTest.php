<?php

use App\Models\DataModel;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\ModelField;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

function importFixtures(?Project $project = null): Project
{
    $project ??= Project::factory()->create(['slug' => 'sg']);

    Artisan::call('notion:import', [
        'project' => $project->slug,
        '--dir' => base_path('tests/Fixtures/notion-export'),
    ]);

    return $project;
}

test('imports all tables and relations', function () {
    $project = importFixtures();

    expect(Module::count())->toBe(2)
        ->and(Requirement::count())->toBe(2)
        ->and(DataModel::count())->toBe(2)
        ->and(ModelField::count())->toBe(2)
        ->and(Feature::count())->toBe(2)
        ->and(FlowStep::count())->toBe(2)
        ->and(Test::count())->toBe(2);

    $requirement = Requirement::where('notion_url', 'https://notion.test/r1')->first();
    expect($requirement->modules)->toHaveCount(1)
        ->and($requirement->modules->first()->notion_url)->toBe('https://notion.test/m1')
        ->and($requirement->modelFields)->toHaveCount(1);

    $feature = Feature::where('notion_url', 'https://notion.test/f1')->first();
    expect($feature->number)->toBe(1)
        ->and($feature->requirement_id)->toBe($requirement->id)
        ->and($feature->dataModels)->toHaveCount(1);

    $field = ModelField::where('notion_url', 'https://notion.test/mf1')->first();
    expect($field->number)->toBe(1)
        ->and($field->requirements)->toHaveCount(1);

    $test = Test::where('notion_url', 'https://notion.test/t1')->first();
    expect($test->features)->toHaveCount(1);
});

test('running the import twice is idempotent', function () {
    $project = importFixtures();
    importFixtures($project);

    expect(Module::count())->toBe(2)
        ->and(Requirement::count())->toBe(2)
        ->and(DataModel::count())->toBe(2)
        ->and(ModelField::count())->toBe(2)
        ->and(Feature::count())->toBe(2)
        ->and(FlowStep::count())->toBe(2)
        ->and(Test::count())->toBe(2);
});

test('unknown enum values abort the import and roll everything back', function () {
    $project = Project::factory()->create(['slug' => 'sg']);

    $dir = sys_get_temp_dir().'/notion-import-'.uniqid();
    File::ensureDirectoryExists($dir);
    File::copyDirectory(base_path('tests/Fixtures/notion-export'), $dir);
    File::put($dir.'/requirements.json', json_encode([
        ['需求' => 'bad', '验收标准' => null, '状态' => '不存在的状态', '模块' => '[]', 'Model Field' => '[]', 'url' => 'https://notion.test/bad'],
    ]));

    $exitCode = Artisan::call('notion:import', ['project' => $project->slug, '--dir' => $dir]);

    expect($exitCode)->toBe(1)
        ->and(Requirement::count())->toBe(0)
        ->and(Module::count())->toBe(0);
});

test('a dangling relation url is skipped without aborting the import', function () {
    importFixtures();

    // f2's 需求 points at a url that was never imported; f2 itself must still exist.
    $feature = Feature::where('notion_url', 'https://notion.test/f2')->first();
    expect($feature)->not->toBeNull()
        ->and($feature->requirement_id)->toBeNull();

    // t2's second 功能 url is dangling; only the valid one should be linked.
    $test = Test::where('notion_url', 'https://notion.test/t2')->first();
    expect($test->features)->toHaveCount(1);
});
