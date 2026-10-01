<?php

namespace App\Data\Tests;

final readonly class TestResultsImportResult
{
    /**
     * @param  list<int>  $unknownNumbers  T-numbers claimed by testcases but absent from the tree
     * @param  list<string>  $unclaimedTestcases  testcase names carrying no T-number
     * @param  list<string>  $missingTests  "T<number> <title>" for auto=yes nodes no testcase claimed this run
     * @param  int  $reset  nodes a full run found unclaimed whose old result went back to "not run"
     */
    public function __construct(
        public int $passed,
        public int $failed,
        public int $skipped,
        public array $unknownNumbers,
        public array $unclaimedTestcases,
        public array $missingTests,
        public int $reset = 0,
    ) {}
}
