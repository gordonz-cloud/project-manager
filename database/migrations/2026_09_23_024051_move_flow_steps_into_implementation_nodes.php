<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each feature's flow-step paths become one call tree: the main path is a chain of `calls`
 * edges from its first step; every other path skips the opening steps it shares with the
 * main path, then hangs off the main-path step just before it (by `order`), or off the root. Irreversible: flow_steps is dropped.
 */
return new class extends Migration
{
    private const FAILURE_WORDS = '/错误|异常|拒绝|失败|error/iu';

    public function up(): void
    {
        $now = now();

        foreach (DB::table('flow_steps')->orderBy('id')->get()->groupBy('feature_id') as $featureId => $steps) {
            $moduleId = DB::table('features')->where('id', $featureId)->value('module_id');
            $paths = $steps->groupBy('path')->map(fn (Collection $pathSteps) => $pathSteps->sortBy([['order', 'asc'], ['id', 'asc']])->values());
            $mainSteps = $paths->pull($paths->keys()->first(fn (string $path) => str_contains($path, '主路径')) ?? $paths->keys()->first());

            if ($mainSteps === null) {
                continue;
            }

            $mainNodeIdsByOrder = [];
            $mainNodeIds = $this->insertChain($mainSteps, $moduleId, $now);

            foreach ($mainSteps as $index => $step) {
                $mainNodeIdsByOrder[$step->order] ??= $mainNodeIds[$index];
            }

            foreach ($paths as $path => $branchSteps) {
                $lastSharedId = $mainNodeIds[0];

                while ($branchSteps->isNotEmpty() && $this->repeatsMainStep($branchSteps[0], $mainSteps)) {
                    $lastSharedId = $mainNodeIdsByOrder[$branchSteps[0]->order];
                    $branchSteps = $branchSteps->slice(1)->values();
                }

                if ($branchSteps->isEmpty()) {
                    continue;
                }

                $attachToId = $mainNodeIdsByOrder[$branchSteps[0]->order - 1] ?? $lastSharedId;

                $branchNodeIds = $this->insertChain($branchSteps, $moduleId, $now);
                $this->insertEdge(
                    $attachToId,
                    $branchNodeIds[0],
                    preg_match(self::FAILURE_WORDS, (string) $path) === 1 ? 'on_failure' : 'calls',
                    (string) $path,
                    $branchSteps[0]->project_id,
                    $now,
                );
            }
        }

        Schema::drop('flow_steps');
    }

    /**
     * A branch often restates the main path's opening steps before it diverges; those share the main node.
     *
     * @param  Collection<int, stdClass>  $mainSteps
     */
    private function repeatsMainStep(stdClass $step, Collection $mainSteps): bool
    {
        $mainStep = $mainSteps->firstWhere('order', $step->order);

        return $mainStep !== null
            && $mainStep->step === $step->step
            && $mainStep->file === $step->file
            && $mainStep->function === $step->function;
    }

    /**
     * @param  Collection<int, stdClass>  $steps
     * @return list<int>
     */
    private function insertChain(Collection $steps, mixed $moduleId, mixed $now): array
    {
        $ids = [];

        foreach ($steps as $step) {
            $ids[] = DB::table('implementation_nodes')->insertGetId([
                'project_id' => $step->project_id,
                'feature_id' => $step->feature_id,
                'module_id' => $moduleId,
                'kind' => 'function',
                'title' => $step->step,
                'contract' => '',
                'state' => 'accepted',
                'evidence_required' => '',
                'file' => $step->file,
                'function' => $step->function,
                'input' => $step->input,
                'change' => $step->change,
                'output' => $step->output,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        for ($i = 1; $i < count($ids); $i++) {
            $this->insertEdge($ids[$i - 1], $ids[$i], 'calls', null, $steps[0]->project_id, $now);
        }

        return $ids;
    }

    private function insertEdge(int $fromId, int $toId, string $kind, ?string $condition, int $projectId, mixed $now): void
    {
        DB::table('implementation_node_edges')->insert([
            'project_id' => $projectId,
            'from_node_id' => $fromId,
            'to_node_id' => $toId,
            'kind' => $kind,
            'condition' => $condition,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
