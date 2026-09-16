<?php

use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Support\Facades\File;

it('hangs commits on features by hash prefix and never moves one that already hangs somewhere', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $refund = Feature::factory()->create(['project_id' => $project->id, 'number' => 15]);
    $checkout = Feature::factory()->create(['project_id' => $project->id, 'number' => 19]);
    $loose = Commit::factory()->create(['project_id' => $project->id, 'hash' => str_repeat('a', 40), 'feature_id' => null]);
    $placed = Commit::factory()->create(['project_id' => $project->id, 'hash' => str_repeat('b', 40), 'feature_id' => $refund->id]);

    $file = storage_path('framework/testing/assign.json');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, json_encode(['project' => 'sg', 'assignments' => [
        ['hash' => 'aaaaaaaa', 'feature' => 19],
        ['hash' => 'bbbbbbbb', 'feature' => 19],
        ['hash' => 'cccccccc', 'feature' => 19],
    ]]));

    $this->artisan('commits:assign', ['file' => $file])
        ->expectsOutputToContain('assigned 1, already assigned 1, problems 1')
        ->assertFailed();

    expect($loose->fresh()->feature_id)->toBe($checkout->id)
        ->and($placed->fresh()->feature_id)->toBe($refund->id);
});
