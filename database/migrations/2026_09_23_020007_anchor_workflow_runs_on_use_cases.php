<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->foreignId('use_case_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (DB::table('workflow_runs')->whereNotNull('feature_id')->get() as $run) {
            DB::table('workflow_runs')->where('id', $run->id)->update([
                'use_case_id' => DB::table('features')->where('id', $run->feature_id)->value('use_case_id'),
            ]);
        }

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_statement_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->foreignId('module_statement_id')->nullable()->constrained('module_specs')->nullOnDelete();
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('use_case_id');
        });
    }
};
