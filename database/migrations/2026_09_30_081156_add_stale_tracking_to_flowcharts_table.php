<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            $table->dateTime('stale_checked_at')->nullable()->after('pseudocode');
            $table->json('stale_nodes')->nullable()->after('stale_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            $table->dropColumn(['stale_checked_at', 'stale_nodes']);
        });
    }
};
