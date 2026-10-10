<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flowcharts belong to rules only: a feature's chart covered more than any one rule, so feature charts are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('flowcharts')->whereNull('requirement_id')->delete();
        DB::statement('alter table flowcharts drop constraint flowcharts_one_owner');

        Schema::table('flowcharts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feature_id');
            $table->foreignId('requirement_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            $table->foreignId('requirement_id')->nullable()->change();
            $table->foreignId('feature_id')->nullable()->unique()->after('project_id')->constrained()->cascadeOnDelete();
        });

        DB::statement('alter table flowcharts add constraint flowcharts_one_owner check ((feature_id is null) <> (requirement_id is null))');
    }
};
