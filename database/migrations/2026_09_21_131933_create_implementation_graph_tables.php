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
        Schema::create('implementation_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('implementation_nodes')->cascadeOnDelete();
            $table->string('kind');
            $table->string('title');
            $table->text('contract');
            $table->string('state')->default('proposed');
            $table->text('evidence_required');
            $table->timestamps();

            $table->index(['feature_id', 'state']);
        });

        Schema::create('implementation_node_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('implementation_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('implementation_nodes')->cascadeOnDelete();
            $table->string('kind');
            $table->text('condition')->nullable();
            $table->timestamps();

            $table->unique(['from_node_id', 'to_node_id', 'kind']);
        });

        Schema::create('scenario_implementation_nodes', function (Blueprint $table) {
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('implementation_node_id')->constrained()->cascadeOnDelete();

            $table->primary(['scenario_id', 'implementation_node_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_implementation_nodes');
        Schema::dropIfExists('implementation_node_edges');
        Schema::dropIfExists('implementation_nodes');
    }
};
