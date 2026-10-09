<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A 提议/冲突 carries what Gordon needs to decide it (RequirementDecision as JSON), and his picks wait in
 * requirement_decision_drafts until he confirms the batch. Additive only: requirements is never rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->jsonb('decision')->nullable();
        });

        Schema::create('requirement_decision_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('choice');
            $table->text('custom_text')->nullable();
            $table->timestamps();
            $table->unique(['requirement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_decision_drafts');

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('decision');
        });
    }
};
