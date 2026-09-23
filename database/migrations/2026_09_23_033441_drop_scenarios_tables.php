<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scenarios are gone: alternate paths live in the Use Case Spec, covered by tests on the feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'scenario_id']);
            $table->dropConstrainedForeignId('scenario_id');
        });
        Schema::dropIfExists('scenario_implementation_nodes');
        Schema::dropIfExists('scenario_steps');
        Schema::dropIfExists('scenarios');
    }

    public function down(): void
    {
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('use_case_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->text('given');
            $table->text('when');
            $table->text('then');
            $table->string('coverage_dimension')->nullable();
            $table->string('equivalence_class')->nullable();
            $table->string('boundary')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['use_case_id', 'status']);
        });
        Schema::create('scenario_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('request_reply_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scenario_id', 'position']);
        });
        Schema::create('scenario_implementation_nodes', function (Blueprint $table) {
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('implementation_node_id')->constrained()->cascadeOnDelete();
            $table->primary(['scenario_id', 'implementation_node_id']);
        });
        Schema::table('tests', function (Blueprint $table) {
            $table->foreignId('scenario_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['project_id', 'scenario_id']);
        });
    }
};
