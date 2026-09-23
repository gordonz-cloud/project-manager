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
        Schema::table('use_cases', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('requirement_id')
                ->constrained('use_cases')
                ->nullOnDelete();

            $table->index(['requirement_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('use_cases', function (Blueprint $table) {
            $table->dropIndex(['requirement_id', 'parent_id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
