<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scenario is a path through the use case's flow graph; the path replaces the single end node.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('request_reply_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scenario_id', 'position']);
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_node_id');
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->foreignId('end_node_id')->nullable()->constrained('implementation_nodes')->nullOnDelete();
        });
        Schema::dropIfExists('scenario_steps');
    }
};
