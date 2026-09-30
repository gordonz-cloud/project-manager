<?php

namespace App\Services\Tests;

use App\Data\Tests\TestResultsImportResult;
use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Models\Project;
use App\Models\Test;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Writes back Pest JUnit results onto the Test Matrix tree.
 *
 * Pest does not expose ->group() in JUnit output (verified by generating a junit file from this
 * repo: a testcase tagged ->group('T1') carries no trace of it in the XML). The only marker that
 * survives into JUnit is a "[T<number>]" token in the test's own description, so that is what
 * this importer reads.
 *
 * @phpstan-type Testcase array{name: string, file: string, numbers: list<int>, result: TestLastResult}
 */
class TestResultsImporter
{
    public function import(Project $project, string $junitPath): TestResultsImportResult
    {
        $testcases = $this->testcases($junitPath);
        $claims = $this->claimsByNumber($testcases);
        $tree = Test::withoutGlobalScopes()->where('project_id', $project->id)->get()->keyBy('number');

        [$passed, $failed, $skipped, $unknownNumbers] = $this->applyClaims($claims, $tree);

        $unclaimedTestcases = array_values(array_map(
            fn (array $testcase): string => $testcase['name'],
            array_filter($testcases, fn (array $testcase): bool => $testcase['numbers'] === [])
        ));

        $missingTests = $this->missingTests($tree, array_keys($claims));

        return new TestResultsImportResult($passed, $failed, $skipped, $unknownNumbers, $unclaimedTestcases, $missingTests);
    }

    /**
     * @param  array<int, list<Testcase>>  $claims  number => claiming testcases
     * @param  Collection<int, Test>  $tree  keyed by number
     * @return array{0: int, 1: int, 2: int, 3: list<int>}
     */
    private function applyClaims(array $claims, Collection $tree): array
    {
        $passed = $failed = $skipped = 0;
        $unknownNumbers = [];

        DB::transaction(function () use ($claims, $tree, &$passed, &$failed, &$skipped, &$unknownNumbers): void {
            foreach ($claims as $number => $claiming) {
                $test = $tree->get($number);

                if ($test === null) {
                    $unknownNumbers[] = $number;

                    continue;
                }

                $test->last_result = $this->resultOf($claiming);
                $test->location ??= $claiming[0]['file'];
                $test->test_name ??= $claiming[0]['name'];
                $test->save();

                match ($test->last_result) {
                    TestLastResult::Failed => $failed++,
                    TestLastResult::Skipped => $skipped++,
                    default => $passed++,
                };
            }
        });

        return [$passed, $failed, $skipped, $unknownNumbers];
    }

    /**
     * @param  list<Testcase>  $claiming
     */
    private function resultOf(array $claiming): TestLastResult
    {
        $results = array_column($claiming, 'result');

        return match (true) {
            in_array(TestLastResult::Failed, $results, true) => TestLastResult::Failed,
            array_filter($results, fn (TestLastResult $result): bool => $result !== TestLastResult::Skipped) === [] => TestLastResult::Skipped,
            default => TestLastResult::Passed,
        };
    }

    /**
     * Auto-covered nodes no testcase in this run claimed.
     *
     * @param  Collection<int, Test>  $tree  keyed by number
     * @param  list<int>  $claimedNumbers
     * @return list<non-falsy-string>
     */
    private function missingTests(Collection $tree, array $claimedNumbers): array
    {
        return array_values($tree
            ->reject(fn (Test $test): bool => $test->auto !== TestAuto::Yes || in_array($test->number, $claimedNumbers, true))
            ->map(fn (Test $test): string => "T{$test->number} {$test->title}")
            ->all());
    }

    /**
     * @param  list<Testcase>  $testcases
     * @return array<int, list<Testcase>> number => claiming testcases
     */
    private function claimsByNumber(array $testcases): array
    {
        $claims = [];

        foreach ($testcases as $testcase) {
            foreach ($testcase['numbers'] as $number) {
                $claims[$number][] = $testcase;
            }
        }

        return $claims;
    }

    /**
     * @return list<Testcase>
     */
    private function testcases(string $junitPath): array
    {
        $xml = simplexml_load_file($junitPath);

        if ($xml === false) {
            throw new InvalidArgumentException("Could not parse JUnit XML at {$junitPath}.");
        }

        $testcases = [];

        foreach ($xml->xpath('//testcase') ?: [] as $node) {
            $name = (string) $node['name'];
            $file = explode('::', (string) $node['file'])[0];
            preg_match_all('/\[T(\d+)]/', $name, $matches);

            $testcases[] = [
                'name' => $name,
                'file' => $file,
                'numbers' => array_values(array_unique(array_map('intval', $matches[1]))),
                'result' => $this->resultOfTestcase($node),
            ];
        }

        return $testcases;
    }

    private function resultOfTestcase(SimpleXMLElement $node): TestLastResult
    {
        return match (true) {
            isset($node->failure) || isset($node->error) => TestLastResult::Failed,
            isset($node->skipped) => TestLastResult::Skipped,
            default => TestLastResult::Passed,
        };
    }
}
