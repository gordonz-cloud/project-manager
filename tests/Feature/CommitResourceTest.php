<?php

use App\Filament\Resources\Commits\Pages\ListCommits;
use App\Models\Commit;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a project does not see another project\'s commits', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create(['slug' => 'p1']);
    $p2 = Project::factory()->create(['slug' => 'p2']);
    $p1->users()->attach($user);
    $p2->users()->attach($user);

    $p1Commit = Commit::factory()->for($p1)->create(['subject' => 'p1 commit']);
    $p2Commit = Commit::factory()->for($p2)->create(['subject' => 'p2 commit']);

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(ListCommits::class)
        ->assertCanSeeTableRecords([$p1Commit])
        ->assertCanNotSeeTableRecords([$p2Commit]);
});
