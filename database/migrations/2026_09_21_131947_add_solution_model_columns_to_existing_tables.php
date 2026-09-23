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
        Schema::table('features', function (Blueprint $table) {
            $table->foreignId('use_case_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->foreignId('implementation_node_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->foreignId('scenario_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('commits', function (Blueprint $table) {
            $table->foreignId('implementation_node_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('implementation_node_id');
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scenario_id');
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('implementation_node_id');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropConstrainedForeignId('use_case_id');
        });
    }
};
