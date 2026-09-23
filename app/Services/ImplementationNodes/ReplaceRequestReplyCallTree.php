<?php

namespace App\Services\ImplementationNodes;

use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Module;
use App\Models\NodeRun;
use App\Models\Project;
use App\Models\RequestReply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Replaces one request reply's whole call tree with the tree in a JSON file:
 * {"project": "sg", "use_case"?: 12, "entry": "POST /login", "tree": [{"title", "file", "function",
 * "input", "change", "output", "module"?, "edge"?, "condition"?, "children": [...]}]}.
 * `entry` is "METHOD path" for a route, or the bare command / job entry; `use_case` is only
 * needed when the same entry exists in several use cases.
 * The tree is re-cut as a whole, because nodes merge and split when the granularity changes.
 */
class ReplaceRequestReplyCallTree
{
    /**
     * @return int nodes written
     */
    public function handle(string $file): int
    {
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['entry']) || ! is_array($spec['tree'] ?? null)) {
            throw new InvalidArgumentException("Expected {project, entry, tree[]} in {$file}");
        }

        $requestReply = $this->requestReply((string) $spec['project'], (string) $spec['entry'], isset($spec['use_case']) ? (int) $spec['use_case'] : null);
        $moduleIds = Module::query()->where('project_id', $requestReply->project_id)->pluck('id', 'name')->all();
        $this->validate($spec['tree'], 'tree', $moduleIds);

        return DB::transaction(function () use ($requestReply, $spec, $moduleIds): int {
            $this->deleteCallTree($requestReply);

            return $this->insertNodes($requestReply, $spec['tree'], null, $moduleIds);
        });
    }

    private function requestReply(string $slug, string $entry, ?int $useCaseId): RequestReply
    {
        $project = Project::query()->where('slug', $slug)->first();
        [$method, $path] = preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(\S+)$/', trim($entry), $route) === 1
            ? [$route[1], $route[2]]
            : [null, trim($entry)];

        $matches = $project === null ? collect() : RequestReply::query()
            ->where('project_id', $project->id)
            ->where('method', $method)
            ->where('entry', $path)
            ->when($useCaseId !== null, fn ($query) => $query->where('use_case_id', $useCaseId))
            ->get();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException("No entry \"{$entry}\" in project {$slug}");
        }

        if ($matches->count() > 1) {
            throw new InvalidArgumentException("Entry \"{$entry}\" is in use cases {$matches->pluck('use_case_id')->implode(', ')}; add \"use_case\"");
        }

        return $matches->first();
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

    private function deleteCallTree(RequestReply $requestReply): void
    {
        $nodeIds = ImplementationNode::withoutGlobalScopes()
            ->where('request_reply_id', $requestReply->id)
            ->where('kind', ImplementationNodeKind::Function)
            ->pluck('id');

        if (NodeRun::withoutGlobalScopes()->whereIn('implementation_node_id', $nodeIds)->exists()) {
            throw new InvalidArgumentException("Entry {$requestReply->label()} call tree has run history; refusing to replace");
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
    private function insertNodes(RequestReply $requestReply, array $nodes, ?ImplementationNode $parent, array $moduleIds): int
    {
        $written = 0;

        foreach ($nodes as $spec) {
            $node = new ImplementationNode([
                'request_reply_id' => $requestReply->id,
                'module_id' => isset($spec['module']) ? $moduleIds[$spec['module']] : $requestReply->module_id,
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
            $node->project_id = $requestReply->project_id;
            $node->save();

            if ($parent !== null) {
                $edge = new ImplementationNodeEdge([
                    'from_node_id' => $parent->id,
                    'to_node_id' => $node->id,
                    'kind' => $spec['edge'] ?? ImplementationNodeEdgeKind::Calls->value,
                    'condition' => $spec['condition'] ?? null,
                ]);
                $edge->project_id = $requestReply->project_id;
                $edge->save();
            }

            $written += 1 + $this->insertNodes($requestReply, (array) ($spec['children'] ?? []), $node, $moduleIds);
        }

        return $written;
    }
}
