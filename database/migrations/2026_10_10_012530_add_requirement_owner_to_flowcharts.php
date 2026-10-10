<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A flowchart now belongs to a feature or to one requirement rule, never both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            $table->foreignId('feature_id')->nullable()->change();
            $table->foreignId('requirement_id')->nullable()->unique()->after('feature_id')->constrained()->cascadeOnDelete();
        });

        DB::statement('alter table flowcharts add constraint flowcharts_one_owner check ((feature_id is null) <> (requirement_id is null))');
    }

    public function down(): void
    {
        DB::statement('alter table flowcharts drop constraint flowcharts_one_owner');

        Schema::table('flowcharts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requirement_id');
            $table->foreignId('feature_id')->nullable(false)->change();
        });
    }
};
