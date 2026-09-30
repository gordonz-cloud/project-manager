<?php

use App\Models\Project;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Project::query()->whereNotNull('repo_path')->pluck('slug')->each(
        fn (string $slug) => Artisan::call('flowcharts:check', ['project-slug' => $slug]),
    );
})->daily()->name('flowcharts:check-all');
