<?php

namespace App\Console\Commands;

use App\Enums\DataModelStatus;
use App\Enums\FeatureStatus;
use App\Enums\RequirementStatus;
use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\ModelField;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Imports the seven Notion-exported JSON files into a project, upserting by
 * `notion_url` so re-running the command is idempotent.
 */
class NotionImportCommand extends Command
{
    protected $signature = 'notion:import {project : Project slug} {--dir=storage/notion-export : Directory containing the exported JSON files}';

    protected $description = 'Import Notion-exported JSON files into a project';

    /** @var array<string, int> Maps every imported row's notion_url to its local id. */
    private array $urlMap = [];

    /** @var array<string, array{created: int, updated: int, skipped: int}> */
    private array $stats = [];

    public function handle(): int
    {
        $project = Project::where('slug', $this->argument('project'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project')}\".");

            return self::FAILURE;
        }

        $dir = rtrim((string) $this->option('dir'), '/');

        $data = [];
        $badFiles = [];

        foreach (['modules', 'requirements', 'data_models', 'model_fields', 'features', 'flow_steps', 'tests'] as $table) {
            $rows = $this->readJson("{$dir}/{$table}.json");

            if ($rows === null) {
                $badFiles[] = "{$table}.json";

                continue;
            }

            $data[$table] = $rows;
        }

        if ($badFiles !== []) {
            $this->error("Missing or invalid JSON, import aborted:\n".implode("\n", $badFiles));

            return self::FAILURE;
        }

        $invalidEnums = $this->collectInvalidEnumValues($data);

        if ($invalidEnums !== []) {
            $this->error("Unknown enum values, import aborted:\n".implode("\n", $invalidEnums));

            return self::FAILURE;
        }

        DB::transaction(function () use ($project, $data): void {
            $this->importModules($project, $data['modules']);
            $this->importRequirements($project, $data['requirements']);
            $this->importDataModels($project, $data['data_models']);
            $this->importModelFields($project, $data['model_fields']);
            $this->importFeatures($project, $data['features']);
            $this->importFlowSteps($project, $data['flow_steps']);
            $this->importTests($project, $data['tests']);

            // Second pass: relation columns, resolved once every row exists.
            $this->linkRequirementModules($data['requirements']);
            $this->linkDataModelModules($data['data_models']);
            $this->linkFeatureDataModels($data['features']);
            $this->linkModelFieldRequirements($data['model_fields']);
            $this->linkTestFeatures($data['tests']);
        });

        foreach ($this->stats as $table => $counts) {
            $this->info(sprintf(
                '%s: %d new, %d updated, %d relations skipped',
                $table,
                $counts['created'],
                $counts['updated'],
                $counts['skipped'],
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null Null when the file is missing or not valid JSON.
     */
    private function readJson(string $path): ?array
    {
        if (! File::exists($path)) {
            return null;
        }

        $rows = json_decode(File::get($path), true);

        return is_array($rows) ? $rows : null;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $data
     * @return array<int, string>
     */
    private function collectInvalidEnumValues(array $data): array
    {
        $checks = [
            'requirements' => ['状态', RequirementStatus::class],
            'data_models' => ['状态', DataModelStatus::class],
            'model_fields' => ['状态', DataModelStatus::class],
            'features' => ['状态', FeatureStatus::class],
            'tests' => ['状态', TestStatus::class],
        ];

        $invalid = [];

        foreach ($checks as $table => [$column, $enum]) {
            foreach ($data[$table] as $row) {
                $value = $row[$column] ?? null;

                if ($value !== null && $enum::tryFrom($value) === null) {
                    $invalid[] = "{$table}.{$column} = \"{$value}\"";
                }
            }
        }

        foreach ($data['tests'] as $row) {
            $value = $row['最近结果'] ?? null;

            if ($value !== null && TestLastResult::tryFrom($value) === null) {
                $invalid[] = "tests.最近结果 = \"{$value}\"";
            }
        }

        return array_unique($invalid);
    }

    /**
     * @return array<int, string>
     */
    private function decodeUrls(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true) ?? [];
    }

    private function resolve(string $url, string $table): ?int
    {
        if (! array_key_exists($url, $this->urlMap)) {
            $this->stats[$table]['skipped']++;
            $this->warn("Skipping relation, no local row imported for {$url}");

            return null;
        }

        return $this->urlMap[$url];
    }

    private function remember(string $notionUrl, int $id): void
    {
        if ($notionUrl !== '') {
            $this->urlMap[$notionUrl] = $id;
        }
    }

    private function trackUpsert(string $table, object $model): void
    {
        $this->stats[$table] ??= ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->stats[$table][$model->wasRecentlyCreated ? 'created' : 'updated']++;
    }

    /**
     * Upserts by `notion_url`. `project_id` is not mass-fillable (it is
     * auto-filled from the Filament tenant in normal request flow), so it is
     * set directly here since a console import has no tenant.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $class
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function upsert(string $class, Project $project, string $notionUrl, array $attributes): object
    {
        $model = $class::firstOrNew(['notion_url' => $notionUrl]);
        $model->project_id = $project->id;
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importModules(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $model = $this->upsert(Module::class, $project, $row['url'], [
                'name' => $row['模块'] ?? $row['name'] ?? '',
            ]);
            $this->trackUpsert('modules', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importRequirements(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $model = $this->upsert(Requirement::class, $project, $row['url'], [
                'title' => $row['需求'],
                'acceptance' => $row['验收标准'] ?? null,
                'status' => $row['状态'],
            ]);
            $this->trackUpsert('requirements', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importDataModels(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $model = $this->upsert(DataModel::class, $project, $row['url'], [
                'name' => $row['Model'],
                'table_name' => $row['表名'] ?? null,
                'status' => $row['状态'],
                'description' => $row['说明'] ?? null,
                'business_purpose' => $row['商业目的'] ?? null,
                'design_gap' => $row['设计差异'] ?? null,
                'ruling' => $row['拍板'] ?? null,
            ]);
            $this->trackUpsert('data_models', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importModelFields(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $dataModelId = $this->resolve($this->decodeUrls($row['Model'])[0] ?? '', 'model_fields');

            $model = $this->upsert(ModelField::class, $project, $row['url'], [
                'data_model_id' => $dataModelId,
                'number' => $row['Field ID'],
                'name' => $row['字段'],
                'type' => $row['类型'] ?? null,
                'nullable' => (bool) ($row['可空'] ?? false),
                'default_value' => $row['默认值'] ?? null,
                'constraint' => $row['约束'] ?? null,
                'description' => $row['说明'] ?? null,
                'status' => $row['状态'],
            ]);
            $this->trackUpsert('model_fields', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importFeatures(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $requirementUrl = $this->decodeUrls($row['需求'] ?? null)[0] ?? null;
            $requirementId = $requirementUrl ? $this->resolve($requirementUrl, 'features') : null;

            $model = $this->upsert(Feature::class, $project, $row['url'], [
                'title' => $row['功能'],
                'number' => $row['Feature ID'],
                'status' => $row['状态'],
                'triggers' => $row['触发方式'] ?? null,
                'entry' => $row['入口'] ?? null,
                'commit_range' => $row['Commit Range'] ?? null,
                'latest_commit' => $row['Latest Commit'] ?? null,
                'requirement_id' => $requirementId,
            ]);
            $this->trackUpsert('features', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importFlowSteps(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $featureId = $this->resolve($this->decodeUrls($row['功能'])[0] ?? '', 'flow_steps');

            $model = $this->upsert(FlowStep::class, $project, $row['url'], [
                'feature_id' => $featureId,
                'path' => $row['路径'],
                'order' => $row['顺序'],
                'step' => $row['步骤'],
                'location' => $row['位置'] ?? null,
                'input' => $row['输入'] ?? null,
                'change' => $row['变化'] ?? null,
                'output' => $row['输出'] ?? null,
            ]);
            $this->trackUpsert('flow_steps', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importTests(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $model = $this->upsert(Test::class, $project, $row['url'], [
                'title' => $row['测试'],
                'location' => $row['测试位置'] ?? null,
                'status' => $row['状态'],
                'last_result' => $row['最近结果'],
            ]);
            $this->trackUpsert('tests', $model);
            $this->remember($row['url'], $model->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function linkRequirementModules(array $rows): void
    {
        foreach ($rows as $row) {
            $requirementId = $this->urlMap[$row['url']] ?? null;

            if (! $requirementId) {
                continue;
            }

            $moduleIds = $this->resolveMany($this->decodeUrls($row['模块'] ?? null), 'requirements');
            Requirement::find($requirementId)->modules()->sync($moduleIds);

            $fieldIds = $this->resolveMany($this->decodeUrls($row['Model Field'] ?? null), 'requirements');
            Requirement::find($requirementId)->modelFields()->sync($fieldIds);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function linkDataModelModules(array $rows): void
    {
        foreach ($rows as $row) {
            $dataModelId = $this->urlMap[$row['url']] ?? null;

            if (! $dataModelId) {
                continue;
            }

            $moduleIds = $this->resolveMany($this->decodeUrls($row['模块'] ?? null), 'data_models');
            DataModel::find($dataModelId)->modules()->sync($moduleIds);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function linkFeatureDataModels(array $rows): void
    {
        foreach ($rows as $row) {
            $featureId = $this->urlMap[$row['url']] ?? null;

            if (! $featureId) {
                continue;
            }

            $dataModelIds = $this->resolveMany($this->decodeUrls($row['Model'] ?? null), 'features');
            Feature::find($featureId)->dataModels()->sync($dataModelIds);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function linkModelFieldRequirements(array $rows): void
    {
        foreach ($rows as $row) {
            $fieldId = $this->urlMap[$row['url']] ?? null;

            if (! $fieldId) {
                continue;
            }

            $requirementIds = $this->resolveMany($this->decodeUrls($row['支持需求'] ?? null), 'model_fields');
            ModelField::find($fieldId)->requirements()->sync($requirementIds);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function linkTestFeatures(array $rows): void
    {
        foreach ($rows as $row) {
            $testId = $this->urlMap[$row['url']] ?? null;

            if (! $testId) {
                continue;
            }

            $featureIds = $this->resolveMany($this->decodeUrls($row['功能'] ?? null), 'tests');
            Test::find($testId)->features()->sync($featureIds);
        }
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, int>
     */
    private function resolveMany(array $urls, string $table): array
    {
        return array_values(array_filter(array_map(
            fn (string $url): ?int => $this->resolve($url, $table),
            $urls,
        )));
    }
}
