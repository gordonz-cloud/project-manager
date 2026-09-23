<?php

namespace App\Support;

class StateMachineCatalog
{
    /**
     * @return array{key: string, label: string, field: string, states: array<string, string>, transitions: list<array{from: string, to: string, label: string, tone: string}>}|null
     */
    public function forNodeType(string $type): ?array
    {
        return match ($type) {
            'Requirement' => [
                'key' => 'requirements',
                'label' => 'Requirements',
                'field' => 'status',
                'states' => [
                    '不确定' => '不确定',
                    '待做' => '待做',
                    '进行中' => '进行中',
                    '暂缓' => '暂缓',
                    '完成' => '完成',
                    '作废' => '作废',
                ],
                'transitions' => [
                    $this->transition('不确定', '待做', '定稿'),
                    $this->transition('待做', '进行中', '开始'),
                    $this->transition('进行中', '完成', '验收通过'),
                    $this->transition('进行中', '暂缓', '暂缓'),
                    $this->transition('暂缓', '进行中', '恢复'),
                    $this->transition('不确定', '作废', '作废', 'danger'),
                    $this->transition('待做', '作废', '作废', 'danger'),
                    $this->transition('进行中', '作废', '作废', 'danger'),
                ],
            ],
            'Feature' => [
                'key' => 'features',
                'label' => 'Features',
                'field' => 'status',
                'states' => [
                    '不确定' => '不确定',
                    '待做' => '待做',
                    '开发中' => '开发中',
                    '验证中' => '验证中',
                    '完成' => '完成',
                    '作废' => '作废',
                ],
                'transitions' => [
                    $this->transition('不确定', '待做', '明确'),
                    $this->transition('待做', '开发中', '开始开发'),
                    $this->transition('开发中', '验证中', '提交验证'),
                    $this->transition('验证中', '完成', '验收通过'),
                    $this->transition('验证中', '开发中', '验收失败', 'back'),
                    $this->transition('不确定', '作废', '作废', 'danger'),
                    $this->transition('待做', '作废', '作废', 'danger'),
                    $this->transition('开发中', '作废', '作废', 'danger'),
                    $this->transition('验证中', '作废', '作废', 'danger'),
                ],
            ],
            'Use Case' => [
                'key' => 'use_cases',
                'label' => 'Use Cases',
                'field' => 'status',
                'states' => [
                    'draft' => '草稿',
                    'ready' => '就绪',
                    'implemented' => '已实现',
                    'verified' => '已验证',
                    'void' => '作废',
                ],
                'transitions' => [
                    $this->transition('draft', 'ready', '定稿'),
                    $this->transition('ready', 'implemented', '实现'),
                    $this->transition('implemented', 'verified', '验证通过'),
                    $this->transition('verified', 'implemented', '重新实现', 'back'),
                    $this->transition('draft', 'void', '作废', 'danger'),
                    $this->transition('ready', 'void', '作废', 'danger'),
                    $this->transition('implemented', 'void', '作废', 'danger'),
                ],
            ],
            'Scenario' => [
                'key' => 'scenarios',
                'label' => 'Scenarios',
                'field' => 'status',
                'states' => [
                    'draft' => '草稿',
                    'ready' => '就绪',
                    'implemented' => '已实现',
                    'verified' => '已验证',
                    'void' => '作废',
                ],
                'transitions' => [
                    $this->transition('draft', 'ready', '定稿'),
                    $this->transition('ready', 'implemented', '实现'),
                    $this->transition('implemented', 'verified', '测试通过'),
                    $this->transition('verified', 'implemented', '测试失败', 'back'),
                    $this->transition('draft', 'void', '作废', 'danger'),
                    $this->transition('ready', 'void', '作废', 'danger'),
                    $this->transition('implemented', 'void', '作废', 'danger'),
                ],
            ],
            'Implementation Node' => [
                'key' => 'implementation_nodes',
                'label' => 'Implementation Nodes',
                'field' => 'state',
                'states' => [
                    'proposed' => '提议中',
                    'accepted' => '已接受',
                    'stale' => '已过时',
                    'obsolete' => '已废弃',
                ],
                'transitions' => [
                    $this->transition('proposed', 'accepted', '接受'),
                    $this->transition('accepted', 'stale', '标记过时'),
                    $this->transition('stale', 'accepted', '重新接受'),
                    $this->transition('proposed', 'obsolete', '废弃', 'danger'),
                    $this->transition('accepted', 'obsolete', '废弃', 'danger'),
                    $this->transition('stale', 'obsolete', '废弃', 'danger'),
                ],
            ],
            'Workflow Run' => [
                'key' => 'workflow_runs',
                'label' => 'Workflow Runs',
                'field' => 'status',
                'states' => [
                    'pending' => '待执行',
                    'talking' => '对话中',
                    'running' => '执行中',
                    'waiting' => '等待中',
                    'paused' => '已暂停',
                    'done' => '完成',
                    'failed' => '失败',
                ],
                'transitions' => [
                    $this->transition('pending', 'talking', '开始对话'),
                    $this->transition('pending', 'running', '开始执行'),
                    $this->transition('talking', 'running', '进入执行'),
                    $this->transition('running', 'waiting', '等待'),
                    $this->transition('running', 'paused', '暂停'),
                    $this->transition('running', 'done', '完成'),
                    $this->transition('running', 'failed', '失败', 'danger'),
                    $this->transition('waiting', 'running', '恢复执行'),
                    $this->transition('paused', 'running', '继续'),
                    $this->transition('failed', 'running', '重试', 'back'),
                ],
            ],
            'Node Run' => [
                'key' => 'node_runs',
                'label' => 'Node Runs',
                'field' => 'status',
                'states' => [
                    'pending' => '待执行',
                    'talking' => '对话中',
                    'running' => '执行中',
                    'blocked' => '阻塞',
                    'done' => '完成',
                    'failed' => '失败',
                ],
                'transitions' => [
                    $this->transition('pending', 'talking', '进入对话'),
                    $this->transition('pending', 'running', '开始执行'),
                    $this->transition('talking', 'running', '执行'),
                    $this->transition('talking', 'blocked', '阻塞', 'danger'),
                    $this->transition('talking', 'done', '完成'),
                    $this->transition('running', 'blocked', '阻塞', 'danger'),
                    $this->transition('running', 'done', '完成'),
                    $this->transition('running', 'failed', '失败', 'danger'),
                    $this->transition('blocked', 'talking', '解除阻塞', 'back'),
                    $this->transition('failed', 'talking', '重新对话', 'back'),
                    $this->transition('failed', 'running', '重试', 'back'),
                ],
            ],
            'Data Model', 'Model Field' => [
                'key' => $type === 'Data Model' ? 'data_models' : 'model_fields',
                'label' => $type === 'Data Model' ? 'Data Models' : 'Model Fields',
                'field' => 'status',
                'states' => [
                    '计划中' => '计划中',
                    '设计中' => '设计中',
                    '现有' => '现有',
                    '废弃' => '废弃',
                ],
                'transitions' => [
                    $this->transition('计划中', '设计中', '开始设计'),
                    $this->transition('设计中', '现有', '落地'),
                    $this->transition('现有', '废弃', '废弃', 'danger'),
                    $this->transition('废弃', '设计中', '重新设计', 'back'),
                ],
            ],
            'Test' => [
                'key' => 'tests',
                'label' => 'Tests',
                'field' => 'status',
                'states' => [
                    '待写' => '待写',
                    '有效' => '有效',
                    '过时' => '过时',
                    '停用' => '停用',
                ],
                'transitions' => [
                    $this->transition('待写', '有效', '完成测试'),
                    $this->transition('有效', '过时', '标记过时'),
                    $this->transition('有效', '停用', '停用', 'danger'),
                    $this->transition('过时', '有效', '重新启用'),
                    $this->transition('停用', '有效', '重新启用'),
                ],
            ],
            default => null,
        };
    }

    /**
     * @return array{from: string, to: string, label: string, tone: string}
     */
    private function transition(string $from, string $to, string $label, string $tone = 'forward'): array
    {
        return compact('from', 'to', 'label', 'tone');
    }
}
