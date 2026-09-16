<?php

use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use Illuminate\Support\Facades\File;

it('writes only the two shape columns, and names ids it could not find', function () {
    $project = Project::factory()->create();
    $feature = Feature::factory()->create(['project_id' => $project->id]);
    $step = FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'change' => 'prose stays']);

    $file = storage_path('framework/testing/shapes.json');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, json_encode([
        ['id' => $step->id, 'input' => "[\n    'id' => 481, // int\n]", 'output' => null],
        ['id' => 999999, 'input' => 'x'],
    ]));

    $this->artisan('flow-steps:apply-shapes', ['file' => $file])
        ->expectsOutputToContain('1 steps written; no step with id 999999')
        ->assertFailed();

    expect($step->fresh()->input)->toBe("[\n    'id' => 481, // int\n]")
        ->and($step->fresh()->output)->toBeNull()
        ->and($step->fresh()->change)->toBe('prose stays');
});
