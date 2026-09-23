<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_nodes', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file')->nullable();
            $table->string('function')->nullable();
            $table->text('input')->nullable();
            $table->text('change')->nullable();
            $table->text('output')->nullable();
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->foreignId('end_node_id')->nullable()->constrained('implementation_nodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_node_id');
        });

        Schema::table('implementation_nodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
            $table->dropColumn(['file', 'function', 'input', 'change', 'output']);
        });
    }
};
