<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For a node waiting on 老板: Gordon's opinion (taken to the boss with the question) and when the question went out.
 * Additive only: requirements is never rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->json('decision_opinion')->nullable();
            $table->timestamp('sent_to_boss_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn(['decision_opinion', 'sent_to_boss_at']);
        });
    }
};
