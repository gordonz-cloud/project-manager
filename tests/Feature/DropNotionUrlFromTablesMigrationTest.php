<?php

use Illuminate\Support\Facades\Schema;

test('the notion_url drop migration removes the column from every business table', function () {
    $tables = ['modules', 'requirements', 'features', 'data_models', 'model_fields', 'flow_steps', 'tests'];

    foreach ($tables as $table) {
        if (! Schema::hasColumn($table, 'notion_url')) {
            Schema::table($table, fn ($blueprint) => $blueprint->string('notion_url')->nullable()->unique());
        }
    }

    $migration = require base_path('database/migrations/2026_09_17_025630_drop_notion_url_from_business_tables.php');
    $migration->up();

    foreach ($tables as $table) {
        expect(Schema::hasColumn($table, 'notion_url'))->toBeFalse();
    }
});
