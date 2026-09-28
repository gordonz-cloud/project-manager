<?php

use App\Enums\FeatureStatus;
use App\Models\Feature;
use App\Models\Flowchart;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use Illuminate\Support\Facades\Artisan;

test('file overlap outranks text overlap', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $useCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $project->id])]);

    $fileMatch = Feature::factory()->forUseCase($useCase)->create(['title' => '别的标题']);
    Flowchart::factory()->create([
        'feature_id' => $fileMatch->id,
        'chart' => [
            'nodes' => [
                ['id' => 'n1', 'label' => '开始', 'shape' => 'start', 'file' => 'app/Services/Checkout.php'],
                ['id' => 'n2', 'label' => '结束', 'shape' => 'end'],
            ],
            'edges' => [['from' => 'n1', 'to' => 'n2']],
        ],
    ]);

    $textMatch = Feature::factory()->forUseCase($useCase)->create(['title' => '结账扫码支付流程']);

    Artisan::call('features:match', ['project-slug' => 'sg', '--files' => 'Checkout.php', '--text' => '结账扫码支付']);
    $output = Artisan::output();

    // File match must be ranked above the text-only match.
    expect($output)->toContain((string) $fileMatch->number)
        ->and(strpos($output, (string) $fileMatch->number))->toBeLessThan(strpos($output, (string) $textMatch->number));
});

test('entry match ranks a feature with no file or text overlap', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $useCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $project->id])]);
    $requestReply = RequestReply::factory()->create(['use_case_id' => $useCase->id, 'method' => 'POST', 'entry' => '/login']);

    $feature = Feature::factory()->forUseCase($useCase)->create(['title' => '无关标题']);
    $feature->requestReplies()->attach($requestReply);

    $this->artisan('features:match', ['project-slug' => 'sg', '--entry' => 'POST /login'])
        ->expectsOutputToContain((string) $feature->number)
        ->assertSuccessful();
});

test('excludes void features and other projects', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $otherProject = Project::factory()->create(['slug' => 'other']);
    $useCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $project->id])]);
    $otherUseCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $otherProject->id])]);

    $void = Feature::factory()->forUseCase($useCase)->create(['title' => '扫码支付', 'status' => FeatureStatus::Void]);
    $otherProjectFeature = Feature::factory()->forUseCase($otherUseCase)->create(['title' => '扫码支付']);

    $this->artisan('features:match', ['project-slug' => 'sg', '--text' => '扫码支付'])
        ->doesntExpectOutputToContain((string) $void->number)
        ->doesntExpectOutputToContain((string) $otherProjectFeature->number)
        ->assertSuccessful();
});
