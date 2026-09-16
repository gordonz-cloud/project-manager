<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Project;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RegisterProject extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register project';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->required()
                    ->unique(Project::class, 'slug'),
                TextInput::make('description'),
            ]);
    }

    protected function handleRegistration(array $data): Project
    {
        $project = Project::create($data);

        $project->users()->attach(auth()->user());

        return $project;
    }
}
