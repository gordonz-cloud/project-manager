<?php

use App\Filament\Resources\RequestReplies\Pages\EditRequestReply;
use App\Filament\Resources\RequestReplies\Pages\ListRequestReplies;
use App\Filament\Resources\RequestReplies\RelationManagers\FeaturesRelationManager;
use App\Filament\Resources\UseCases\Pages\EditUseCase;
use App\Filament\Resources\UseCases\RelationManagers\RequestRepliesRelationManager;
use App\Models\Feature;
use App\Models\Project;
use App\Models\RequestReply;
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

test('request replies are listed and shown on their use case [T8]', function () {
    Livewire::test(ListRequestReplies::class)->assertCanSeeTableRecords([$this->login, $this->home]);

    Livewire::test(RequestRepliesRelationManager::class, ['ownerRecord' => $this->useCase, 'pageClass' => EditUseCase::class])
        ->assertCanSeeTableRecords([$this->login, $this->home]);
});

test('a request reply shows its features [T105]', function () {
    $feature = Feature::factory()->forUseCase($this->useCase)->create();
    $feature->requestReplies()->attach($this->login);

    Livewire::test(FeaturesRelationManager::class, ['ownerRecord' => $this->login, 'pageClass' => EditRequestReply::class])
        ->assertCanSeeTableRecords([$feature]);
});
