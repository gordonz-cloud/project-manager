<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tests\TestResultsImporter;
use Illuminate\Console\Command;

/**
 * Writes a Pest JUnit run back onto the Test Matrix tree, matching testcases to nodes by a
 * "[T<number>]" token in the test's own description (Pest's ->group() does not survive into
 * JUnit output, so groups cannot be used as the marker).
 */
class ImportTestResultsCommand extends Command
{
    protected $signature = 'tests:results {slug : Project slug} {junit : Path to a JUnit XML file}';

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

        $result = $importer->import($project, $junit);

        $this->info("节点更新：通过 {$result->passed}，失败 {$result->failed}，跳过 {$result->skipped}");

        $this->listSection('代码有、树上没有（编号）', array_map(strval(...), $result->unknownNumbers));
        $this->listSection('没认领节点的测试', $result->unclaimedTestcases, limit: 20);
        $this->listSection('缺测试（auto=yes 但本次没有测试认领）', $result->missingTests, limit: 20);

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
