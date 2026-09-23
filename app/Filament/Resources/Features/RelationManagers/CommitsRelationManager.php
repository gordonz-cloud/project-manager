<?php

namespace App\Filament\Resources\Features\RelationManagers;

use App\Models\Project;
use App\Services\Commits\SyncProjectCommits;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommitsRelationManager extends RelationManager
{
    protected static string $relationship = 'commits';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('feature_id')
                    ->label('改挂到入口')
                    ->relationship(name: 'feature', titleAttribute: 'title')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('committed_at', 'desc')
            ->columns([
                TextColumn::make('hash')
                    ->label('Hash')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 8))
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap(),
                TextColumn::make('author')
                    ->label('作者'),
                TextColumn::make('committed_at')
                    ->label('提交时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('syncFromGit')
                    ->label('Sync from git')
                    ->action(function (): void {
                        $projectId = $this->getOwnerRecord()->getAttribute('project_id');
                        $project = Project::query()->whereKey($projectId)->first();

                        abort_if($project === null, 404);

                        $result = resolve(SyncProjectCommits::class)->handle($project);

                        Notification::make()
                            ->title('已同步')
                            ->body($result->summary())
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('改挂到别的入口')
                    ->slideOver(),
                DetachAction::make()
                    ->label('取消挂载')
                    ->action(fn ($record) => $record->unassignFeature()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->action(fn ($records) => $records->each->unassignFeature()),
                ]),
            ]);
    }
}
