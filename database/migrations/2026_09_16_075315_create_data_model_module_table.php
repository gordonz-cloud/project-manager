<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_model_module', function (Blueprint $table) {
            $table->foreignId('data_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->primary(['data_model_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_model_module');
    }
};
