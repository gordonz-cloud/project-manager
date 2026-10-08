<?php

use App\Models\Concerns\BelongsToProject;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
