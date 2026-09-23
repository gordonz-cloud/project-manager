<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('module_statements', 'module_specs');

        Schema::create('module_use_cases', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('use_case_id')->constrained()->cascadeOnDelete();

            $table->primary(['module_id', 'use_case_id']);
        });

        Schema::table('use_cases', function (Blueprint $table) {
            $table->foreignId('use_case_group_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('features', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (DB::table('projects')->orderBy('id')->get() as $project) {
            $groupId = DB::table('use_case_groups')->insertGetId([
                'project_id' => $project->id,
                'name' => '未分类',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('use_cases')->where('project_id', $project->id)->orderBy('id')->get() as $useCase) {
                $moduleId = DB::table('module_specs')->where('id', $useCase->module_statement_id)->value('module_id');

                if ($moduleId !== null) {
                    DB::table('module_use_cases')->insertOrIgnore([
                        'module_id' => $moduleId,
                        'use_case_id' => $useCase->id,
                    ]);
                }

                $content = "# {$useCase->actor} · {$useCase->goal}";

                foreach ([
                    '触发' => $useCase->trigger,
                    '前置条件' => $useCase->precondition,
                    '成功结果' => $useCase->success_outcome,
                    '失败结果' => $useCase->failure_outcome,
                ] as $heading => $value) {
                    if (blank($value)) {
                        continue;
                    }

                    $content .= "\n\n## {$heading}\n{$value}";
                }

                DB::table('use_case_specs')->insert([
                    'project_id' => $project->id,
                    'use_case_id' => $useCase->id,
                    'version' => 1,
                    'status' => 'draft',
                    'content' => $content,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('use_cases')->where('id', $useCase->id)->update([
                    'use_case_group_id' => $groupId,
                ]);
            }
        }

        foreach (DB::table('features')->orderBy('id')->get() as $feature) {
            $moduleIds = $feature->use_case_id === null
                ? collect()
                : DB::table('module_use_cases')
                    ->where('use_case_id', $feature->use_case_id)
                    ->pluck('module_id');

            if ($moduleIds->isEmpty() && $feature->requirement_id !== null) {
                $moduleIds = DB::table('module_requirement')
                    ->where('requirement_id', $feature->requirement_id)
                    ->pluck('module_id');
            }

            if ($moduleIds->count() !== 1) {
                continue;
            }

            DB::table('features')->where('id', $feature->id)->update([
                'module_id' => $moduleIds->first(),
            ]);
        }

        Schema::table('use_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_statement_id');
        });
    }

    public function down(): void
    {
        Schema::table('use_cases', function (Blueprint $table) {
            $table->foreignId('module_statement_id')->nullable()->constrained('module_specs')->nullOnDelete();
        });

        foreach (DB::table('use_cases')->orderBy('id')->get() as $useCase) {
            $moduleId = DB::table('module_use_cases')
                ->where('use_case_id', $useCase->id)
                ->orderBy('module_id')
                ->value('module_id');

            if ($moduleId === null) {
                continue;
            }

            $statementId = DB::table('module_specs')->where('module_id', $moduleId)->value('id');

            if ($statementId !== null) {
                DB::table('use_cases')->where('id', $useCase->id)->update([
                    'module_statement_id' => $statementId,
                ]);
            }
        }

        Schema::table('features', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
        });

        Schema::table('use_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('use_case_group_id');
        });

        Schema::dropIfExists('module_use_cases');
        Schema::rename('module_specs', 'module_statements');
    }
};
