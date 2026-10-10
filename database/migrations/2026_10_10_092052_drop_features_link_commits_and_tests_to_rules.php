<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The feature layer goes: commits and tests hang straight on requirements, and acceptance moves onto the rule.
 * A rule served by a 验证中 feature needs review and is not accepted yet; one whose live features are all 完成 is accepted.
 * Use cases, modules, entry points, workflow runs and the data-model ↔ feature links go with it.
 * Irreversible: restore from the pg_dump taken before it ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_requirement', function (Blueprint $table) {
            $table->foreignId('commit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['commit_id', 'requirement_id']);
            $table->index('requirement_id');
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false);
            $table->timestamp('accepted_at')->nullable();
        });

        DB::statement('insert into commit_requirement (commit_id, requirement_id)
            select distinct commits.id, fr.requirement_id from commits join feature_requirement fr on fr.feature_id = commits.feature_id');

        DB::statement("insert into requirement_test (requirement_id, test_id)
            select distinct fr.requirement_id, ft.test_id from feature_test ft
            join feature_requirement fr on fr.feature_id = ft.feature_id
            join features on features.id = ft.feature_id and features.status <> '作废'
            on conflict do nothing");

        DB::statement("update requirements set needs_review = true where id in (
            select fr.requirement_id from feature_requirement fr join features on features.id = fr.feature_id where features.status = '验证中')");

        DB::statement("update requirements set accepted_at = now() where not needs_review and id in (
            select fr.requirement_id from feature_requirement fr join features on features.id = fr.feature_id
            where features.status <> '作废' group by fr.requirement_id having bool_and(features.status = '完成'))");

        Schema::table('commits', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'feature_id']);
            $table->dropConstrainedForeignId('feature_id');
        });

        foreach (['run_events', 'workflow_runs', 'feature_test', 'feature_requirement', 'feature_request_reply', 'data_model_feature', 'features',
            'request_replies', 'module_use_cases', 'use_case_specs', 'use_cases', 'use_case_groups', 'module_requirement', 'module_dependencies', 'module_specs', 'modules'] as $table) {
            Schema::drop($table);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible: restore from the pg_dump taken before this migration.');
    }
};
