<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the flat requirements list into a tree of verifiable statements (目标 → 子目标 → 规则).
 * Status now records the decision; delivery is derived from linked features and tests, so the old
 * delivery-ish values collapse: 完成/进行中/待做 → 已定, 不确定/暂缓 → 提议 (暂缓 noted in rationale).
 * features.requirement_id keeps working; its links are copied into the new many-to-many pivot.
 *
 * parent_id / supersedes_id carry no foreign-key constraint on purpose: adding one makes SQLite rebuild the table,
 * and the rebuild's DROP runs inside the migration transaction where foreign keys cannot be switched off, so it
 * would fire ON DELETE on everything pointing at requirements (use_cases cascade, features set null).
 * requirements:save validates both references instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('kind')->nullable();
            $table->text('rationale')->nullable();
            $table->string('source')->nullable();
            $table->string('decided_by')->nullable();
            $table->date('decided_at')->nullable();
            $table->unsignedBigInteger('supersedes_id')->nullable()->index();
        });

        DB::table('requirements')->where('status', '暂缓')->update(['rationale' => '原状态：暂缓']);
        DB::table('requirements')->whereIn('status', ['完成', '进行中', '待做'])->update(['status' => '已定']);
        DB::table('requirements')->whereIn('status', ['不确定', '暂缓'])->update(['status' => '提议']);

        $numbers = [];
        foreach (DB::table('requirements')->orderBy('id')->get(['id', 'project_id']) as $requirement) {
            $numbers[$requirement->project_id] = ($numbers[$requirement->project_id] ?? 0) + 1;
            DB::table('requirements')->where('id', $requirement->id)->update(['number' => $numbers[$requirement->project_id]]);
        }

        Schema::table('requirements', function (Blueprint $table) {
            $table->unique(['project_id', 'number']);
        });

        Schema::create('requirement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->text('old_statement')->nullable();
            $table->text('new_statement')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason')->nullable();
            $table->string('source')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('feature_requirement', function (Blueprint $table) {
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['feature_id', 'requirement_id']);
        });

        Schema::create('requirement_test', function (Blueprint $table) {
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->primary(['requirement_id', 'test_id']);
        });

        DB::table('feature_requirement')->insertUsing(
            ['feature_id', 'requirement_id'],
            DB::table('features')->whereNotNull('requirement_id')->select('id', 'requirement_id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_test');
        Schema::dropIfExists('feature_requirement');
        Schema::dropIfExists('requirement_revisions');

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['supersedes_id']);
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn(['number', 'parent_id', 'kind', 'rationale', 'source', 'decided_by', 'decided_at', 'supersedes_id']);
        });
    }
};
