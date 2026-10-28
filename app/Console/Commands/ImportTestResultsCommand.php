<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tests\TestResultsImporter;
use Illuminate\Console\Command;

/**
 * Writes a Pest JUnit run back onto the Test Matrix tree, matching testcases to nodes by a
 * "[T<number>]" token in the test's own description (Pest's ->group() does not survive into
 * JUnit output, so groups cannot be used as the marker). With --full the file is the whole suite:
 * nodes no testcase claimed get their stale result reset to "not run". With --strict as well, the
 * command fails when a live node lost the test that used to prove it, or a test claims a number the
 * tree does not have, so CI turns red the moment a tag goes missing instead of weeks later.
 */
class ImportTestResultsCommand extends Command
{
    protected $signature = 'tests:results {slug : Project slug} {junit : Path to a JUnit XML file} {--full : The file is a full-suite run: reset results on nodes no test claimed} {--strict : With --full, fail when a live node lost its test or a test claims an unknown number}';

    protected $description = 'Write a Pest JUnit run back onto the Test Matrix tree ([T<number>] in the test description)';

    public function handle(TestResultsImporter $importer): int
    {
        $slug = (string) $this->argument('slug');
        $junit = (string) $this->argument('junit');
        $project = Project::query()->where('slug', $slug)->first();

        if ($project === null) {
            $this->error("No project with slug \"{$slug}\".");

            return self::FAILURE;
        }

        if (! is_file($junit)) {
            $this->error("No such file: {$junit}");

            return self::FAILURE;
        }

        $result = $importer->import($project, $junit, (bool) $this->option('full'));

        $this->info("节点更新：通过 {$result->passed}，失败 {$result->failed}，跳过 {$result->skipped}");

        if ($this->option('full')) {
            $this->info("无人认领、结果重置为未跑：{$result->reset}");
        }

        $this->listSection('代码有、树上没有（编号）', array_map(strval(...), $result->unknownNumbers));
        $this->listSection('没认领节点的测试', $result->unclaimedTestcases, limit: 20);
        $this->listSection('缺测试（auto=yes 但本次没有测试认领）', $result->missingTests, limit: 20);
        $this->listSection('丢了认领（上次有测试结果，这次没有测试认领）', $result->lostClaims);

        if ($this->option('strict') && $this->option('full') && ($result->lostClaims !== [] || $result->unknownNumbers !== [])) {
            $this->error('测试树对不上：补回标签、把节点标成过时，或在树上补节点。');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $items
     */
    private function listSection(string $title, array $items, ?int $limit = null): void
    {
        if ($items === []) {
            return;
        }

        $shown = $limit === null ? $items : array_slice($items, 0, $limit);

        $this->warn("{$title}（".count($items).'）：');

        foreach ($shown as $item) {
            $this->line("  - {$item}");
        }
    }
}
