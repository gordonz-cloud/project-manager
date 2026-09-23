<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $kindHeadings = [
            'definition' => '定义',
            'scope' => '范围',
            'non_goal' => '非目标',
            'requirement' => '需求',
            'business_rule' => '业务规则',
            'decision' => '决策',
            'decision_outcome' => '决策结论',
            'data_meaning' => '数据语义',
            'acceptance' => '验收',
        ];

        foreach (DB::table('module_statements')->orderBy('id')->get() as $statement) {
            $moduleName = DB::table('modules')->where('id', $statement->module_id)->value('name') ?? 'Module';
            $claims = DB::table('statement_claims')
                ->where('module_statement_id', $statement->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($claims->isEmpty()) {
                continue;
            }

            $content = "# {$moduleName}";

            foreach ($kindHeadings as $kind => $heading) {
                $kindClaims = $claims->where('kind', $kind);

                if ($kindClaims->isEmpty()) {
                    continue;
                }

                $content .= "\n\n## {$heading}\n";

                foreach ($kindClaims as $claim) {
                    $content .= "- {$claim->statement}\n";
                }
            }

            DB::table('module_statements')->where('id', $statement->id)->update([
                'content' => rtrim($content),
            ]);
        }

        Schema::dropIfExists('scenario_claims');
        Schema::dropIfExists('statement_claims');
    }

    public function down(): void
    {
        Schema::create('statement_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_statement_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('kind');
            $table->text('statement');
            $table->string('status')->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('modality')->nullable();
            $table->text('scope')->nullable();
            $table->text('enforcement')->nullable();
            $table->string('decision_kind')->nullable();
            $table->json('options')->nullable();
            $table->text('resolution')->nullable();
            $table->text('rationale')->nullable();
            $table->nullableMorphs('target');
            $table->boolean('blocking')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['module_statement_id', 'key']);
            $table->index(['module_statement_id', 'sort_order']);
        });

        Schema::create('scenario_claims', function (Blueprint $table) {
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_claim_id')->constrained()->cascadeOnDelete();

            $table->primary(['scenario_id', 'statement_claim_id']);
        });
    }
};
