<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These hold free text that already runs past 255 characters; SQLite never enforced the limit, Postgres does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_fields', fn (Blueprint $table) => $table->text('constraint')->nullable()->change());
        Schema::table('requirements', fn (Blueprint $table) => $table->text('source')->nullable()->change());
        Schema::table('requirement_revisions', fn (Blueprint $table) => $table->text('source')->nullable()->change());
        Schema::table('commits', fn (Blueprint $table) => $table->text('subject')->change());
        Schema::table('tests', fn (Blueprint $table) => $table->text('test_name')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('model_fields', fn (Blueprint $table) => $table->string('constraint')->nullable()->change());
        Schema::table('requirements', fn (Blueprint $table) => $table->string('source')->nullable()->change());
        Schema::table('requirement_revisions', fn (Blueprint $table) => $table->string('source')->nullable()->change());
        Schema::table('commits', fn (Blueprint $table) => $table->string('subject')->change());
        Schema::table('tests', fn (Blueprint $table) => $table->string('test_name')->nullable()->change());
    }
};
