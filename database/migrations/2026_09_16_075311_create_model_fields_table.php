<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_model_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('nullable')->default(false);
            $table->string('default_value')->nullable();
            $table->string('constraint')->nullable();
            $table->text('description')->nullable();
            $table->string('status');
            $table->string('notion_url')->nullable()->unique();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_fields');
    }
};
