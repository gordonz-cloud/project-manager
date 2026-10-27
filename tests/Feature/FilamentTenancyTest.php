<?php

use App\Filament\Pages\Tenancy\EditProject;
use App\Filament\Pages\Tenancy\RegisterProject;
use App\Models\Concerns\BelongsToProject;
use App\Models\Project;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('login page is reachable [T76]', function () {
    $this->get('/admin/login')->assertOk();
});

test('admin panel uses the full content width', function () {
    expect(Filament::getPanel('admin')->getMaxContentWidth())->toBe(Width::Full);
});

test('a user cannot access a project they do not belong to [T2]', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create(['slug' => 'p1']);
    $p2 = Project::factory()->create(['slug' => 'p2']);
    $p1->users()->attach($user);

    $this->actingAs($user)
        ->get('/admin/p2')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/admin/p1')
        ->assertOk();
});

test('BelongsToProject scopes records to the current tenant and fills project_id on create [T2]', function () {
    Schema::create('project_scoped_widgets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('project_id')->nullable()->constrained('projects');
        $table->string('name');
        $table->timestamps();
    });

    $model = new class extends Model
    {
        use BelongsToProject;

        protected $table = 'project_scoped_widgets';

        protected $fillable = ['name'];
    };

    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p2 = Project::factory()->create();
    $p1->users()->attach($user);
    $p2->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($p1);

    $widget = $model::create(['name' => 'Widget']);

    expect($widget->project_id)->toBe($p1->id);
    expect($model::count())->toBe(1);

    Filament::setTenant($p2);

    expect($model::count())->toBe(0);

    Schema::dropIfExists('project_scoped_widgets');
});

it('registers a project with its repo path and lists it among the registering user tenants [T1]', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(RegisterProject::class)
        ->fillForm(['name' => 'Operations', 'slug' => 'operations', 'repo_path' => '/repos/operations'])
        ->call('register')
        ->assertHasNoFormErrors();

    $project = Project::where('slug', 'operations')->sole();

    expect($project->repo_path)->toBe('/repos/operations')
        ->and($project->users()->pluck('users.id')->all())->toBe([$user->id])
        ->and($user->fresh()->getTenants(Filament::getPanel('admin'))->pluck('id')->all())->toBe([$project->id]);
});

it('keeps a wrong-password admin login unauthenticated and shows the error [T77]', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertSee(__('filament-panels::auth/pages/login.messages.failed'))
        ->assertNoRedirect();

    $this->assertGuest();
});

it('redirects an unauthenticated project visitor to the admin login [T78]', function () {
    $project = Project::factory()->create();

    $this->get('/admin/'.$project->slug)->assertRedirect('/admin/login');

    $this->assertGuest();
});

it('rejects an occupied slug without registering a project or membership [T91]', function () {
    $user = User::factory()->create();
    $existing = Project::factory()->create(['slug' => 'occupied']);
    $this->actingAs($user);

    Livewire::test(RegisterProject::class)
        ->fillForm(['name' => 'Another project', 'slug' => 'occupied', 'repo_path' => '/repos/another'])
        ->call('register')
        ->assertHasFormErrors(['slug' => 'unique']);

    expect(Project::sole()->id)->toBe($existing->id)
        ->and($user->projects()->count())->toBe(0);
});

it('saves the edited repo path from the project profile [T92]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['repo_path' => '/repos/old']);
    $project->users()->attach($user);
    $this->actingAs($user);
    Filament::setTenant($project);

    Livewire::test(EditProject::class)
        ->fillForm(['repo_path' => '/repos/new'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->fresh()->repo_path)->toBe('/repos/new');
});
