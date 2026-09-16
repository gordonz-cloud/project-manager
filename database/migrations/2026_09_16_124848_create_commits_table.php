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
        Schema::create('commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hash', 40);
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('author');
            $table->dateTime('committed_at');
            $table->timestamps();

            $table->unique(['project_id', 'hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commits');
    }
};
