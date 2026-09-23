<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('use_cases', function (Blueprint $table) {
            $table->foreignId('module_statement_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('business_rules', function (Blueprint $table) {
            $table->foreignId('module_statement_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->foreignId('module_statement_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->foreignId('module_statement_id')->nullable()->constrained()->nullOnDelete();
        });

        $requirementStatements = [];
        $now = now();

        foreach (DB::table('modules')->orderBy('id')->get() as $module) {
            $requirementIds = DB::table('module_requirement')
                ->where('module_id', $module->id)
                ->orderBy('requirement_id')
                ->pluck('requirement_id');

            $statementId = DB::table('module_statements')->insertGetId([
                'project_id' => $module->project_id,
                'module_id' => $module->id,
                'version' => 1,
                'status' => 'draft',
                'summary' => "Legacy statement for {$module->name}; review required.",
                'content' => "Module: {$module->name}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($requirementIds as $position => $requirementId) {
                $requirementStatements[$requirementId] ??= $statementId;
                $requirement = DB::table('requirements')->where('id', $requirementId)->first();

                if ($requirement === null) {
                    continue;
                }

                DB::table('statement_claims')->insert([
                    'project_id' => $module->project_id,
                    'module_statement_id' => $statementId,
                    'key' => "LEGACY-R{$requirement->id}",
                    'kind' => 'requirement',
                    'statement' => $requirement->title,
                    'status' => 'draft',
                    'sort_order' => $position + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (blank($requirement->acceptance)) {
                    continue;
                }

                DB::table('statement_claims')->insert([
                    'project_id' => $module->project_id,
                    'module_statement_id' => $statementId,
                    'key' => "LEGACY-R{$requirement->id}-ACCEPTANCE",
                    'kind' => 'acceptance',
                    'statement' => $requirement->acceptance,
                    'status' => 'draft',
                    'sort_order' => $position + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($requirementStatements as $requirementId => $statementId) {
            DB::table('use_cases')
                ->where('requirement_id', $requirementId)
                ->update(['module_statement_id' => $statementId]);
            DB::table('business_rules')
                ->where('requirement_id', $requirementId)
                ->update(['module_statement_id' => $statementId]);
            DB::table('decisions')
                ->where('requirement_id', $requirementId)
                ->update(['module_statement_id' => $statementId]);
            DB::table('workflow_runs')
                ->where('requirement_id', $requirementId)
                ->update(['module_statement_id' => $statementId]);
        }

        Schema::table('use_cases', function (Blueprint $table) {
            $table->foreignId('requirement_id')->nullable()->change();
            $table->dropIndex(['requirement_id', 'parent_id']);
            $table->dropConstrainedForeignId('parent_id');
        });

        Schema::table('business_rules', function (Blueprint $table) {
            $table->foreignId('requirement_id')->nullable()->change();
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->foreignId('requirement_id')->nullable()->change();
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->foreignId('requirement_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_statement_id');
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_statement_id');
        });

        Schema::table('business_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_statement_id');
        });

        Schema::table('use_cases', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('use_cases')
                ->nullOnDelete();
            $table->index(['requirement_id', 'parent_id']);
            $table->dropConstrainedForeignId('module_statement_id');
        });
    }
};
