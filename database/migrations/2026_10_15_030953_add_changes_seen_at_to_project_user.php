<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this member last opened the requirement tree's 最近变化 tab in this project: 上次看过以后 starts there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->timestamp('changes_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('changes_seen_at');
        });
    }
};
