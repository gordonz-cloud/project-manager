<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_field_requirement', function (Blueprint $table) {
            $table->foreignId('model_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['model_field_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_field_requirement');
    }
};
