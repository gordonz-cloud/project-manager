<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->index('requirement_id');
            $table->index('use_case_id');
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->index(['project_id', 'feature_id']);
            $table->index('implementation_node_id');
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->index(['project_id', 'scenario_id']);
        });

        Schema::table('commits', function (Blueprint $table) {
            $table->index(['project_id', 'feature_id']);
            $table->index('implementation_node_id');
        });

        Schema::table('use_cases', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('business_rules', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('implementation_nodes', function (Blueprint $table) {
            $table->index(['project_id', 'feature_id']);
            $table->index('parent_id');
        });

        Schema::table('implementation_node_edges', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->index(['project_id', 'requirement_id']);
            $table->index('feature_id');
        });

        Schema::table('node_runs', function (Blueprint $table) {
            $table->index('implementation_node_id');
        });

        Schema::table('run_events', function (Blueprint $table) {
            $table->index('node_run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('run_events', function (Blueprint $table) {
            $table->dropIndex(['node_run_id']);
        });

        Schema::table('node_runs', function (Blueprint $table) {
            $table->dropIndex(['implementation_node_id']);
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'requirement_id']);
            $table->dropIndex(['feature_id']);
        });

        Schema::table('implementation_node_edges', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('implementation_nodes', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'feature_id']);
            $table->dropIndex(['parent_id']);
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('business_rules', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('use_cases', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('commits', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'feature_id']);
            $table->dropIndex(['implementation_node_id']);
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'scenario_id']);
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'feature_id']);
            $table->dropIndex(['implementation_node_id']);
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropIndex(['requirement_id']);
            $table->dropIndex(['use_case_id']);
        });
    }
};
