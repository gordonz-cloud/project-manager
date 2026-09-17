<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_dependencies', function (Blueprint $table) {
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_requirement_id')->constrained('requirements')->cascadeOnDelete();
            $table->unique(['requirement_id', 'depends_on_requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_dependencies');
    }
};
