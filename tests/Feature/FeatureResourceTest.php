<?php

use App\Enums\FeatureStatus;
use App\Filament\Resources\Features\Pages\CreateFeature;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('creating a feature assigns the current tenant and the first sequence number', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p1->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(CreateFeature::class)
        ->fillForm([
            'title' => 'Checkout flow',
            'status' => FeatureStatus::Todo->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $feature = Feature::first();

    expect($feature->project_id)->toBe($p1->id);
    expect($feature->number)->toBe(1);
});
