<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every decision is Gordon's now: who must decide (decider), his opinion for 老板 and when it went out are gone.
 * SQLite drops these unindexed columns in place, requirements is not rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn(['decider', 'decision_opinion', 'sent_to_boss_at']);
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('decider')->nullable();
            $table->jsonb('decision_opinion')->nullable();
            $table->timestamp('sent_to_boss_at')->nullable();
        });
    }
};
