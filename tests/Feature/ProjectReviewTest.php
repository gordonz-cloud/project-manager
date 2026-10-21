<?php

use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test as ProjectTest;
use App\Models\User;

it('keeps reviewers outside the writable panel while exposing their project trees', function () {
    $project = Project::factory()->create(['slug' => 'sg']);
    $other = Project::factory()->create(['slug' => 'private']);
    $reviewer = User::factory()->create(['review_only' => true]);
    $reviewer->projects()->attach($project);
    $root = Requirement::factory()->create(['project_id' => $project->id, 'title' => 'Review visible root']);
    Requirement::factory()->create(['project_id' => $project->id, 'parent_id' => $root->id, 'title' => 'Review visible child']);
    Requirement::factory()->create(['project_id' => $other->id, 'title' => 'Private other project']);
    ProjectTest::factory()->create(['project_id' => $project->id, 'title' => 'Visible test evidence']);

    $this->actingAs($reviewer)->get(route('review.tree', ['project' => $project, 'tree' => 'requirements']))
        ->assertOk()->assertSee('Review visible root')->assertSee('Review visible child')->assertDontSee('Private other project');
    $this->get(route('review.tree', ['project' => $project, 'tree' => 'tests']))->assertOk()->assertSee('Visible test evidence');
    $this->get(route('review.tree', ['project' => $other, 'tree' => 'requirements']))->assertForbidden();
    $this->get('/admin/sg/requirement-tree')->assertForbidden();
    $this->post(route('review.tree', ['project' => $project, 'tree' => 'requirements']), ['title' => 'Changed'])->assertStatus(405);
    expect($root->fresh()->title)->toBe('Review visible root');
});

it('requires verified identity and project membership and protects the review flag', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create(['email_verified_at' => null, 'review_only' => true]);
    $user->projects()->attach($project);
    $this->actingAs($user)->get(route('review.tree', ['project' => $project, 'tree' => 'requirements']))->assertForbidden();
    $user->fill(['review_only' => false]);
    expect($user->review_only)->toBeTrue();
});
