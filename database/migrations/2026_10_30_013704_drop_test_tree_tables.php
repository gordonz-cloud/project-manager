<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Haorui's read-only schema mirrors these two tables as views; they go with the test tree.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS haorui_review.requirement_test');
            DB::statement('DROP VIEW IF EXISTS haorui_review.tests');
        }

        Schema::dropIfExists('requirement_test');
        Schema::dropIfExists('tests');
    }

    public function down(): void
    {
        // The test tree was retired; its backed-up data is not restored by rollback.
    }
};
