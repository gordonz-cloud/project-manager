<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('statement_claims')->where('key', 'like', 'LEGACY-%')->get() as $claim) {
            $key = match (true) {
                str_starts_with($claim->key, 'LEGACY-R') && str_ends_with($claim->key, '-ACCEPTANCE') => str_replace(
                    ['LEGACY-R', '-ACCEPTANCE'],
                    ['R-', '-A'],
                    (string) $claim->key,
                ),
                str_starts_with($claim->key, 'LEGACY-R') => Str::replaceFirst('LEGACY-R', 'R-', $claim->key),
                str_starts_with($claim->key, 'LEGACY-BR-') => Str::replaceFirst('LEGACY-BR-', 'BR-', $claim->key),
                str_starts_with($claim->key, 'LEGACY-D-') => Str::replaceFirst('LEGACY-D-', 'D-', $claim->key),
                default => $claim->key,
            };

            DB::table('statement_claims')->where('id', $claim->id)->update(['key' => $key]);
        }

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
            $summaryClaims = $claims
                ->whereIn('kind', ['definition', 'scope', 'requirement'])
                ->take(3)
                ->pluck('statement')
                ->filter()
                ->values();

            if ($summaryClaims->isEmpty()) {
                $summaryClaims = $claims->take(3)->pluck('statement')->filter()->values();
            }

            $summary = $summaryClaims->isEmpty()
                ? $moduleName
                : Str::limit($summaryClaims->implode('；'), 220);
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

            if ($claims->isEmpty()) {
                $content .= "\n\n暂无陈述。";
            }

            DB::table('module_statements')->where('id', $statement->id)->update([
                'summary' => $summary,
                'content' => rtrim($content),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('statement_claims')->orderBy('id')->get() as $claim) {
            $key = match (true) {
                str_starts_with($claim->key, 'R-') && str_ends_with($claim->key, '-A') => str_replace(
                    ['R-', '-A'],
                    ['LEGACY-R', '-ACCEPTANCE'],
                    (string) $claim->key,
                ),
                str_starts_with($claim->key, 'R-') => Str::replaceFirst('R-', 'LEGACY-R', $claim->key),
                str_starts_with($claim->key, 'BR-') => Str::replaceFirst('BR-', 'LEGACY-BR-', $claim->key),
                str_starts_with($claim->key, 'D-') => Str::replaceFirst('D-', 'LEGACY-D-', $claim->key),
                default => $claim->key,
            };

            DB::table('statement_claims')->where('id', $claim->id)->update(['key' => $key]);
        }

        foreach (DB::table('module_statements')->orderBy('id')->get() as $statement) {
            $moduleName = DB::table('modules')->where('id', $statement->module_id)->value('name') ?? 'Module';

            DB::table('module_statements')->where('id', $statement->id)->update([
                'summary' => "Legacy statement for {$moduleName}; review required.",
                'content' => "Module: {$moduleName}",
            ]);
        }
    }
};
