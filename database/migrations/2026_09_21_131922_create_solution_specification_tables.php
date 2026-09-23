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
        Schema::create('use_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->string('actor');
            $table->text('goal');
            $table->text('trigger')->nullable();
            $table->text('precondition')->nullable();
            $table->text('success_outcome');
            $table->text('failure_outcome')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['requirement_id', 'status']);
        });

        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
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

        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->text('statement');
            $table->string('modality');
            $table->text('scope')->nullable();
            $table->text('enforcement')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['requirement_id', 'status']);
        });

        Schema::create('scenario_rules', function (Blueprint $table) {
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_rule_id')->constrained()->cascadeOnDelete();

            $table->primary(['scenario_id', 'business_rule_id']);
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->text('question');
            $table->json('options')->nullable();
            $table->text('resolution')->nullable();
            $table->text('rationale')->nullable();
            $table->nullableMorphs('target');
            $table->boolean('blocking')->default(false);
            $table->string('status')->default('open');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['requirement_id', 'status', 'blocking']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('scenario_rules');
        Schema::dropIfExists('business_rules');
        Schema::dropIfExists('scenarios');
        Schema::dropIfExists('use_cases');
    }
};
