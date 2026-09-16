<?php

use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use Illuminate\Support\Facades\File;

it('replaces one path wholesale and leaves the feature\'s other paths alone', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 14]);
    FlowStep::factory()->count(3)->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => '主路径']);
    $other = FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => '错误分支']);

    $file = storage_path('framework/testing/path.json');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, json_encode([
        'project' => 'sg', 'feature' => 14, 'path' => '主路径',
        'steps' => [
            ['order' => 1, 'step' => '入口', 'file' => 'routes/webhooks.php', 'output' => 'Request'],
            ['order' => 2, 'step' => '验签', 'file' => 'app/Http/Middleware/VerifyStripeWebhook.php', 'function' => 'handle', 'input' => 'Request', 'output' => 'Request'],
        ],
    ]));

    $this->artisan('flow-steps:replace-path', ['file' => $file])
        ->expectsOutputToContain('主路径: 2 steps now')
        ->assertSuccessful();

    expect(FlowStep::query()->where('feature_id', $feature->id)->where('path', '主路径')->orderBy('order')->pluck('file')->all())
        ->toBe(['routes/webhooks.php', 'app/Http/Middleware/VerifyStripeWebhook.php'])
        ->and(FlowStep::query()->find($other->id))->not->toBeNull();
});

it('refuses a step without a return value, and writes nothing', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 14]);
    $kept = FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => '主路径']);

    $file = storage_path('framework/testing/path.json');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, json_encode([
        'project' => 'sg', 'feature' => 14, 'path' => '主路径',
        'steps' => [['order' => 1, 'step' => '入口', 'file' => 'routes/webhooks.php']],
    ]));

    // Every function hands something back, even void; a hop with nothing in
    // that column is a hop nobody finished writing.
    $this->artisan('flow-steps:replace-path', ['file' => $file])
        ->expectsOutputToContain('steps[0] is missing output')
        ->assertFailed();

    expect(FlowStep::query()->find($kept->id))->not->toBeNull();
});
