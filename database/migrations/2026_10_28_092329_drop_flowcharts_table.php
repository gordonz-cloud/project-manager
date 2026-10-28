<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('flowcharts');
    }

    public function down(): void
    {
        Schema::create('flowcharts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->index()->constrained()->cascadeOnDelete();
            $table->json('chart');
            $table->text('pseudocode')->nullable();
            $table->timestamps();
            $table->dateTime('stale_checked_at')->nullable();
            $table->json('stale_nodes')->nullable();
            $table->foreignId('requirement_id')->unique()->constrained()->cascadeOnDelete();
        });
    }
};
