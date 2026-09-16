<?php

use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Support\Facades\Schema;

test('the version-move migration backfills a requirement version from any of its features', function () {
    $migration = require base_path('database/migrations/2026_09_16_101047_move_version_from_features_to_requirements.php');

    $project = Project::factory()->create();
    $withVersion = Requirement::factory()->create(['project_id' => $project->id]);
    $withoutVersion = Requirement::factory()->create(['project_id' => $project->id]);

    // Simulate the pre-migration schema (features.version, no requirements.version)
    // so the migration's up() can run again against known data.
    Schema::table('features', fn ($table) => $table->string('version')->nullable());
    Schema::table('requirements', fn ($table) => $table->dropColumn('version'));

    Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $withVersion->id, 'version' => '1']);
    Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $withVersion->id, 'version' => null]);
    Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $withoutVersion->id, 'version' => null]);

    $migration->up();

    expect($withVersion->fresh()->version)->toBe('1')
        ->and($withoutVersion->fresh()->version)->toBeNull();

    expect(Schema::hasColumn('features', 'version'))->toBeFalse();
});
