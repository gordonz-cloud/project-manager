<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns tests into Test Matrix nodes: one atomic action + expected per row, parent_id forms the tree.
 * "Blocked" is derived from a failed ancestor, so stored 阻塞 becomes 未跑.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('tests')->cascadeOnDelete();
            $table->string('module')->nullable();
            $table->text('expected')->nullable();
            $table->string('priority')->nullable();
            $table->string('platform')->nullable();
            $table->string('test_name')->nullable();
            $table->string('auto')->nullable();
            $table->text('notes')->nullable();
        });

        DB::table('tests')->where('last_result', '阻塞')->update(['last_result' => '未跑']);
        DB::table('tests')->where('last_result', 'pass')->update(['last_result' => '通过']);

        $numbers = [];
        foreach (DB::table('tests')->orderBy('id')->get(['id', 'project_id']) as $test) {
            $numbers[$test->project_id] = ($numbers[$test->project_id] ?? 0) + 1;
            DB::table('tests')->where('id', $test->id)->update(['number' => $numbers[$test->project_id]]);
        }

        Schema::table('tests', function (Blueprint $table) {
            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['number', 'module', 'expected', 'priority', 'platform', 'test_name', 'auto', 'notes']);
        });
    }
};
