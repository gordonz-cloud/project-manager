<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('number');
            $table->string('status');
            $table->jsonb('triggers')->nullable();
            $table->text('entry')->nullable();
            $table->string('commit_range')->nullable();
            $table->string('latest_commit')->nullable();
            $table->foreignId('requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notion_url')->nullable()->unique();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
