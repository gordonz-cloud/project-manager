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
        Schema::create('data_shapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('format')->nullable();
            $table->text('sample');
            $table->foreignId('data_model_id')->nullable()->constrained('data_models')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->foreignId('input_shape_id')->nullable()->constrained('data_shapes')->nullOnDelete();
            $table->foreignId('output_shape_id')->nullable()->constrained('data_shapes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('input_shape_id');
            $table->dropConstrainedForeignId('output_shape_id');
        });

        Schema::dropIfExists('data_shapes');
    }
};
