<?php

namespace App\Services\ImplementationNodes;

use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Module;
use App\Models\NodeRun;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Replaces one entry's whole call tree with the tree in a JSON file:
 * {"project": "sg", "feature": 14, "tree": [{"title", "file", "function",
 * "input", "change", "output", "module"?, "edge"?, "condition"?, "children": [...]}]}.
 * The tree is re-cut as a whole, because nodes merge and split when the granularity changes.
 */
class ReplaceFeatureCallTree
{
    /**
     * @return int nodes written
     */
    public function handle(string $file): int
    {
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['feature']) || ! is_array($spec['tree'] ?? null)) {
            throw new InvalidArgumentException("Expected {project, feature, tree[]} in {$file}");
        }

        $feature = $this->feature((string) $spec['project'], (int) $spec['feature']);
        $moduleIds = Module::query()->where('project_id', $feature->project_id)->pluck('id', 'name')->all();
        $this->validate($spec['tree'], 'tree', $moduleIds);

        return DB::transaction(function () use ($feature, $spec, $moduleIds): int {
            $this->deleteCallTree($feature);

            return $this->insertNodes($feature, $spec['tree'], null, $moduleIds);
        });
    }

    private function feature(string $slug, int $number): Feature
    {
        $project = Project::query()->where('slug', $slug)->first();
        $feature = $project === null ? null : Feature::query()
            ->where('project_id', $project->id)
            ->where('number', $number)
            ->first();

        if ($feature === null) {
            throw new InvalidArgumentException("No feature {$number} in project {$slug}");
        }

        return $feature;
    }

    /**
     * Every function hands something back, even void; a node with no input or output is one nobody finished writing.
     *
     * @param  array<mixed>  $nodes
     * @param  array<string, int>  $moduleIds
     */
    private function validate(array $nodes, string $path, array $moduleIds): void
    {
        foreach ($nodes as $index => $node) {
            $at = "{$path}[{$index}]";

            if (! is_array($node)) {
                throw new InvalidArgumentException("{$at} is not an object");
            }

            foreach (['title', 'input', 'output'] as $required) {
                if (! isset($node[$required]) || $node[$required] === '') {
                    throw new InvalidArgumentException("{$at} is missing {$required}");
                }
            }

            if (isset($node['module']) && ! isset($moduleIds[$node['module']])) {
                throw new InvalidArgumentException("{$at} has unknown module {$node['module']}");
            }

            if (isset($node['edge']) && ! in_array(ImplementationNodeEdgeKind::tryFrom((string) $node['edge']), ImplementationNodeEdgeKind::callTree(), true)) {
                throw new InvalidArgumentException("{$at} has unknown edge {$node['edge']}");
            }

            $this->validate((array) ($node['children'] ?? []), "{$at}.children", $moduleIds);
        }
    }

    private function deleteCallTree(Feature $feature): void
    {
        $nodeIds = ImplementationNode::withoutGlobalScopes()
            ->where('feature_id', $feature->id)
            ->where('kind', ImplementationNodeKind::Function)
            ->pluck('id');

        if (NodeRun::withoutGlobalScopes()->whereIn('implementation_node_id', $nodeIds)->exists()) {
            throw new InvalidArgumentException("Feature {$feature->number} call tree has run history; refusing to replace");
        }

        ImplementationNodeEdge::withoutGlobalScopes()
            ->whereIn('from_node_id', $nodeIds)
            ->orWhereIn('to_node_id', $nodeIds)
            ->delete();
        ImplementationNode::withoutGlobalScopes()->whereIn('id', $nodeIds)->delete();
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  array<string, int>  $moduleIds
     */
    private function insertNodes(Feature $feature, array $nodes, ?ImplementationNode $parent, array $moduleIds): int
    {
        $written = 0;

        foreach ($nodes as $spec) {
            $node = new ImplementationNode([
                'feature_id' => $feature->id,
                'module_id' => isset($spec['module']) ? $moduleIds[$spec['module']] : $feature->module_id,
                'kind' => ImplementationNodeKind::Function,
                'title' => $spec['title'],
                'contract' => '',
                'state' => ImplementationNodeState::Accepted,
                'evidence_required' => '',
                'file' => $spec['file'] ?? null,
                'function' => $spec['function'] ?? null,
                'input' => $spec['input'],
                'change' => $spec['change'] ?? null,
                'output' => $spec['output'],
            ]);
            $node->project_id = $feature->project_id;
            $node->save();

            if ($parent !== null) {
                $edge = new ImplementationNodeEdge([
                    'from_node_id' => $parent->id,
                    'to_node_id' => $node->id,
                    'kind' => $spec['edge'] ?? ImplementationNodeEdgeKind::Calls->value,
                    'condition' => $spec['condition'] ?? null,
                ]);
                $edge->project_id = $feature->project_id;
                $edge->save();
            }

            $written += 1 + $this->insertNodes($feature, (array) ($spec['children'] ?? []), $node, $moduleIds);
        }

        return $written;
    }
}
