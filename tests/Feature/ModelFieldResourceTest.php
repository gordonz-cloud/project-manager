<?php

use App\Enums\DataModelStatus;
use App\Filament\Resources\ModelFields\Pages\CreateModelField;
use App\Filament\Resources\ModelFields\Pages\ListModelFields;
use App\Models\DataModel;
use App\Models\ModelField;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('model field list only shows model fields for the current tenant [T12]', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p2 = Project::factory()->create();
    $p1->users()->attach($user);

    $p1Field = ModelField::factory()->for($p1)->create();
    ModelField::factory()->for($p2)->create();

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(ListModelFields::class)
        ->assertCanSeeTableRecords([$p1Field])
        ->assertCountTableRecords(1);
});

test('model field form data model select only offers models from the current tenant [T12]', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p2 = Project::factory()->create();
    $p1->users()->attach($user);

    $p1Model = DataModel::factory()->for($p1)->create();
    $p2Model = DataModel::factory()->for($p2)->create();

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(CreateModelField::class)
        ->fillForm([
            'data_model_id' => $p1Model->id,
            'name' => 'field_one',
            'status' => DataModelStatus::Existing->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ModelField::first()->data_model_id)->toBe($p1Model->id);

    Livewire::test(CreateModelField::class)
        ->fillForm([
            'data_model_id' => $p2Model->id,
            'name' => 'field_two',
            'status' => DataModelStatus::Existing->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['data_model_id']);
});

test('creating a model field via the resource sets the tenant and the next number [T12]', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p1->users()->attach($user);

    $p1Model = DataModel::factory()->for($p1)->create();

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(CreateModelField::class)
        ->fillForm([
            'data_model_id' => $p1Model->id,
            'name' => 'field_one',
            'status' => DataModelStatus::Existing->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $field = ModelField::first();

    expect($field->project_id)->toBe($p1->id)
        ->and($field->number)->toBe(1);
});
