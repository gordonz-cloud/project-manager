<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('table_name')->nullable();
            $table->string('status');
            $table->text('description')->nullable();
            $table->text('business_purpose')->nullable();
            $table->text('design_gap')->nullable();
            $table->text('ruling')->nullable();
            $table->string('notion_url')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_models');
    }
};
