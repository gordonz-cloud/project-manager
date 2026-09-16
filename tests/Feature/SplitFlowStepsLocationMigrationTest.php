<?php

use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the location-split migration parses each location format into file and function', function () {
    $migration = require base_path('database/migrations/2026_09_16_110000_split_flow_steps_location_into_file_and_function.php');

    $project = Project::factory()->create();
    $feature = Feature::factory()->create(['project_id' => $project->id]);

    // Simulate the pre-migration schema (flow_steps.location, no file/function)
    // so the migration's up() can run again against known data.
    Schema::table('flow_steps', fn ($table) => $table->string('location')->nullable());
    Schema::table('flow_steps', fn ($table) => $table->dropColumn(['file', 'function']));

    // Raw inserts, not the factory: the pre-migration schema has no
    // file/function columns, and the factory's default state sets `file`.
    $row = ['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => 'x', 'order' => 1, 'step' => 'step'];

    $colonMethod = DB::table('flow_steps')->insertGetId($row + ['location' => 'app/Services/OrderService.php::charge()']);
    $dotMethod = DB::table('flow_steps')->insertGetId($row + ['location' => 'app/Services/Inventory/InventoryService.php · adjust']);
    $colonNoParens = DB::table('flow_steps')->insertGetId($row + ['location' => 'app/Http/Middleware/VerifyStripeWebhook.php::handle']);
    $pathOnly = DB::table('flow_steps')->insertGetId($row + ['location' => 'routes/web.php']);

    $migration->up();

    expect(FlowStep::find($colonMethod))
        ->file->toBe('app/Services/OrderService.php')
        ->function->toBe('charge');

    expect(FlowStep::find($dotMethod))
        ->file->toBe('app/Services/Inventory/InventoryService.php')
        ->function->toBe('adjust');

    expect(FlowStep::find($colonNoParens))
        ->file->toBe('app/Http/Middleware/VerifyStripeWebhook.php')
        ->function->toBe('handle');

    expect(FlowStep::find($pathOnly))
        ->file->toBe('routes/web.php')
        ->function->toBeNull();

    expect(Schema::hasColumn('flow_steps', 'location'))->toBeFalse();
});
