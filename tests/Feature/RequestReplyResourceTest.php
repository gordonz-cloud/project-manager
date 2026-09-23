<?php

use App\Filament\Resources\RequestReplies\Pages\EditRequestReply;
use App\Filament\Resources\RequestReplies\Pages\ListRequestReplies;
use App\Filament\Resources\RequestReplies\RelationManagers\FeaturesRelationManager;
use App\Filament\Resources\RequestReplies\RelationManagers\OutgoingEdgesRelationManager;
use App\Filament\Resources\Scenarios\Pages\EditScenario;
use App\Filament\Resources\UseCases\Pages\EditUseCase;
use App\Filament\Resources\UseCases\RelationManagers\RequestRepliesRelationManager;
use App\Models\Feature;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\RequestReplyEdge;
use App\Models\Scenario;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $user = User::factory()->create();
    $this->project = Project::factory()->create();
    $this->project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($this->project);

    $this->useCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $this->project->id])]);
    [$this->login, $this->home] = RequestReply::factory()->count(2)->create(['use_case_id' => $this->useCase->id]);
});

test('request replies are listed and shown on their use case', function () {
    Livewire::test(ListRequestReplies::class)->assertCanSeeTableRecords([$this->login, $this->home]);

    Livewire::test(RequestRepliesRelationManager::class, ['ownerRecord' => $this->useCase, 'pageClass' => EditUseCase::class])
        ->assertCanSeeTableRecords([$this->login, $this->home]);
});

test('a request reply shows its downstream edges and features', function () {
    $edge = RequestReplyEdge::factory()->create(['from_request_reply_id' => $this->login->id, 'to_request_reply_id' => $this->home->id]);
    $feature = Feature::factory()->forUseCase($this->useCase)->create();
    $feature->requestReplies()->attach($this->login);

    Livewire::test(OutgoingEdgesRelationManager::class, ['ownerRecord' => $this->login, 'pageClass' => EditRequestReply::class])
        ->assertCanSeeTableRecords([$edge]);
    Livewire::test(FeaturesRelationManager::class, ['ownerRecord' => $this->login, 'pageClass' => EditRequestReply::class])
        ->assertCanSeeTableRecords([$feature]);
});

test('the scenario form saves its path in order, repeats allowed', function () {
    $scenario = Scenario::factory()->create(['project_id' => $this->project->id, 'use_case_id' => $this->useCase->id]);
    $scenario->replaceSteps([$this->home->id]);

    Livewire::test(EditScenario::class, ['record' => $scenario->getRouteKey()])
        ->fillForm(['steps' => array_map(fn (int $id): array => ['request_reply_id' => $id], [$this->login->id, $this->home->id, $this->login->id])])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($scenario->steps()->pluck('request_reply_id')->all())->toBe([$this->login->id, $this->home->id, $this->login->id])
        ->and($scenario->steps()->pluck('position')->all())->toBe([1, 2, 3]);
});
