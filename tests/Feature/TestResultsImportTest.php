<?php

use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Models\Project;
use App\Models\Test;
use Illuminate\Testing\PendingCommand;

/**
 * @param  list<string>  $testcases  raw <testcase>…</testcase> XML fragments
 */
function importJunit(string $slug, array $testcases, array $options = []): PendingCommand
{
    $body = implode("\n", $testcases);
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <testsuites>
          <testsuite name="Tests\Feature\SampleTest" file="tests/Feature/SampleTest.php" tests="1">
            {$body}
          </testsuite>
        </testsuites>
        XML;

    $file = tempnam(sys_get_temp_dir(), 'junit');
    file_put_contents($file, $xml);

    return test()->artisan('tests:results', ['slug' => $slug, 'junit' => $file, ...$options]);
}

function passingCase(string $name): string
{
    return "<testcase name=\"{$name}\" file=\"tests/Feature/SampleTest.php::{$name}\" assertions=\"1\" time=\"0.01\"/>";
}

function failingCase(string $name): string
{
    return "<testcase name=\"{$name}\" file=\"tests/Feature/SampleTest.php::{$name}\" assertions=\"1\" time=\"0.01\"><failure type=\"Exception\">boom</failure></testcase>";
}

function skippedCase(string $name): string
{
    return "<testcase name=\"{$name}\" file=\"tests/Feature/SampleTest.php::{$name}\" assertions=\"0\" time=\"0.01\"><skipped/></testcase>";
}

it('writes passed, failed, and skipped results onto claimed nodes and fills empty file/name [T126]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    $passNode = Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'last_result' => TestLastResult::NotRun]);
    $failNode = Test::factory()->create(['project_id' => $project->id, 'number' => 2, 'last_result' => TestLastResult::NotRun]);
    $skipNode = Test::factory()->create(['project_id' => $project->id, 'number' => 3, 'last_result' => TestLastResult::NotRun]);

    importJunit('tt', [
        passingCase('opens cart [T1]'),
        failingCase('pays [T2]'),
        skippedCase('ships [T3]'),
    ])->expectsOutputToContain('通过 1，失败 1，跳过 1')->assertSuccessful();

    expect($passNode->fresh()->last_result)->toBe(TestLastResult::Passed)
        ->and($passNode->fresh()->test_name)->toBe('opens cart [T1]')
        ->and($passNode->fresh()->location)->toBe('tests/Feature/SampleTest.php')
        ->and($failNode->fresh()->last_result)->toBe(TestLastResult::Failed)
        ->and($skipNode->fresh()->last_result)->toBe(TestLastResult::Skipped);
});

it('marks a node failed when any claiming testcase fails, even if others pass [T127]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    $node = Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'last_result' => TestLastResult::NotRun]);

    importJunit('tt', [passingCase('a [T1]'), failingCase('b [T1]')])->assertSuccessful();

    expect($node->fresh()->last_result)->toBe(TestLastResult::Failed);
});

it('does not reset a node the run did not claim [T128]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    $node = Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'last_result' => TestLastResult::Passed]);

    importJunit('tt', [passingCase('unrelated')])->assertSuccessful();

    expect($node->fresh()->last_result)->toBe(TestLastResult::Passed);
});

it('resets the stale result of an unclaimed node on a full run, only in that project [T129]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    $other = Project::factory()->create(['slug' => 'other']);
    $claimed = Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'last_result' => TestLastResult::NotRun]);
    $unclaimed = Test::factory()->create(['project_id' => $project->id, 'number' => 2, 'last_result' => TestLastResult::Passed]);
    $otherProject = Test::factory()->create(['project_id' => $other->id, 'number' => 2, 'last_result' => TestLastResult::Passed]);

    importJunit('tt', [passingCase('a [T1]')], ['--full' => true])
        ->expectsOutputToContain('结果重置为未跑：1')
        ->assertSuccessful();

    expect($claimed->fresh()->last_result)->toBe(TestLastResult::Passed)
        ->and($unclaimed->fresh()->last_result)->toBe(TestLastResult::NotRun)
        ->and($otherProject->fresh()->last_result)->toBe(TestLastResult::Passed);
});

it('reports unknown claimed numbers, unclaimed testcases, and auto nodes missing a test [T130]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'title' => 'Covered', 'auto' => TestAuto::Yes]);
    Test::factory()->create(['project_id' => $project->id, 'number' => 9, 'title' => 'Not covered', 'auto' => TestAuto::Yes]);

    importJunit('tt', [passingCase('a [T1]'), passingCase('a [T404]'), passingCase('no marker here')])
        ->expectsOutputToContain('代码有、树上没有（编号）（1）')
        ->expectsOutputToContain('404')
        ->expectsOutputToContain('没认领节点的测试（1）')
        ->expectsOutputToContain('no marker here')
        ->expectsOutputToContain('缺测试')
        ->expectsOutputToContain('T9 Not covered')
        ->assertSuccessful();
});

it('fails for an unknown project or missing file [T131]', function () {
    importJunit('does-not-exist', [passingCase('a [T1]')])->assertFailed();

    Project::factory()->create(['slug' => 'tt']);
    test()->artisan('tests:results', ['slug' => 'tt', 'junit' => '/no/such/file.xml'])->assertFailed();
});
