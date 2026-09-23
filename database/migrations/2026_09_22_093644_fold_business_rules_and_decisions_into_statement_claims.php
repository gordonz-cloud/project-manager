<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_claims', function (Blueprint $table) {
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
        });

        Schema::create('scenario_claims', function (Blueprint $table) {
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_claim_id')->constrained()->cascadeOnDelete();

            $table->primary(['scenario_id', 'statement_claim_id']);
        });

        $ruleClaimIds = [];

        foreach (DB::table('business_rules')->orderBy('id')->get() as $rule) {
            $statementId = $rule->module_statement_id
                ?? DB::table('module_statements')->where('project_id', $rule->project_id)->value('id');

            if ($statementId === null) {
                continue;
            }

            $ruleClaimIds[$rule->id] = DB::table('statement_claims')->insertGetId([
                'project_id' => $rule->project_id,
                'module_statement_id' => $statementId,
                'key' => "LEGACY-BR-{$rule->id}",
                'kind' => 'business_rule',
                'statement' => $rule->statement,
                'status' => $rule->status,
                'sort_order' => 10000 + $rule->id,
                'modality' => $rule->modality,
                'scope' => $rule->scope,
                'enforcement' => $rule->enforcement,
                'blocking' => false,
                'created_at' => $rule->created_at,
                'updated_at' => $rule->updated_at,
            ]);
        }

        foreach (DB::table('scenario_rules')->orderBy('scenario_id')->get() as $scenarioRule) {
            $statementClaimId = $ruleClaimIds[$scenarioRule->business_rule_id] ?? null;

            if ($statementClaimId === null) {
                continue;
            }

            DB::table('scenario_claims')->insert([
                'scenario_id' => $scenarioRule->scenario_id,
                'statement_claim_id' => $statementClaimId,
            ]);
        }

        foreach (DB::table('decisions')->orderBy('id')->get() as $decision) {
            $statementId = $decision->module_statement_id
                ?? DB::table('module_statements')->where('project_id', $decision->project_id)->value('id');

            if ($statementId === null) {
                continue;
            }

            $targetType = $decision->target_type;
            $targetId = $decision->target_id;

            if ($targetType === 'App\\Models\\BusinessRule' && $targetId !== null) {
                $targetType = 'App\\Models\\StatementClaim';
                $targetId = $ruleClaimIds[$targetId] ?? null;
            }

            DB::table('statement_claims')->insert([
                'project_id' => $decision->project_id,
                'module_statement_id' => $statementId,
                'key' => "LEGACY-D-{$decision->id}",
                'kind' => 'decision',
                'statement' => $decision->question,
                'status' => $decision->status,
                'sort_order' => 20000 + $decision->id,
                'decision_kind' => $decision->kind,
                'options' => $decision->options,
                'resolution' => $decision->resolution,
                'rationale' => $decision->rationale,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'blocking' => $decision->blocking,
                'decided_at' => $decision->decided_at,
                'created_at' => $decision->created_at,
                'updated_at' => $decision->updated_at,
            ]);
        }

        Schema::dropIfExists('scenario_rules');
        Schema::dropIfExists('business_rules');
        Schema::dropIfExists('decisions');
    }

    public function down(): void
    {
        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_statement_id')->nullable()->constrained()->nullOnDelete();
            $table->text('statement');
            $table->string('modality');
            $table->text('scope')->nullable();
            $table->text('enforcement')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_statement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->text('question');
            $table->json('options')->nullable();
            $table->text('resolution')->nullable();
            $table->text('rationale')->nullable();
            $table->nullableMorphs('target');
            $table->boolean('blocking')->default(false);
            $table->string('status')->default('open');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scenario_rules', function (Blueprint $table) {
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_rule_id')->constrained()->cascadeOnDelete();

            $table->primary(['scenario_id', 'business_rule_id']);
        });

        $ruleIds = [];

        foreach (DB::table('statement_claims')->where('kind', 'business_rule')->orderBy('id')->get() as $claim) {
            $ruleIds[$claim->id] = DB::table('business_rules')->insertGetId([
                'project_id' => $claim->project_id,
                'requirement_id' => null,
                'module_statement_id' => $claim->module_statement_id,
                'statement' => $claim->statement,
                'modality' => $claim->modality,
                'scope' => $claim->scope,
                'enforcement' => $claim->enforcement,
                'status' => $claim->status,
                'created_at' => $claim->created_at,
                'updated_at' => $claim->updated_at,
            ]);
        }

        foreach (DB::table('scenario_claims')->orderBy('scenario_id')->get() as $scenarioClaim) {
            if (! isset($ruleIds[$scenarioClaim->statement_claim_id])) {
                continue;
            }

            DB::table('scenario_rules')->insert([
                'scenario_id' => $scenarioClaim->scenario_id,
                'business_rule_id' => $ruleIds[$scenarioClaim->statement_claim_id],
            ]);
        }

        foreach (DB::table('statement_claims')->where('kind', 'decision')->orderBy('id')->get() as $claim) {
            $targetType = $claim->target_type;
            $targetId = $claim->target_id;

            if ($targetType === 'App\\Models\\StatementClaim' && $targetId !== null) {
                $targetType = 'App\\Models\\BusinessRule';
                $targetId = $ruleIds[$targetId] ?? null;
            }

            DB::table('decisions')->insert([
                'project_id' => $claim->project_id,
                'requirement_id' => null,
                'module_statement_id' => $claim->module_statement_id,
                'kind' => $claim->decision_kind,
                'question' => $claim->statement,
                'options' => $claim->options,
                'resolution' => $claim->resolution,
                'rationale' => $claim->rationale,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'blocking' => $claim->blocking,
                'status' => $claim->status,
                'decided_at' => $claim->decided_at,
                'created_at' => $claim->created_at,
                'updated_at' => $claim->updated_at,
            ]);
        }

        Schema::dropIfExists('scenario_claims');

        Schema::table('statement_claims', function (Blueprint $table) {
            $table->dropMorphs('target');
            $table->dropColumn([
                'modality',
                'scope',
                'enforcement',
                'decision_kind',
                'options',
                'resolution',
                'rationale',
                'blocking',
                'decided_at',
            ]);
        });
    }
};
