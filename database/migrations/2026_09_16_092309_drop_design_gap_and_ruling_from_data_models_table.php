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
        Schema::table('data_models', function (Blueprint $table) {
            $table->dropColumn(['design_gap', 'ruling']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_models', function (Blueprint $table) {
            $table->text('design_gap')->nullable();
            $table->text('ruling')->nullable();
        });
    }
};
