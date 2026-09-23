<?php

namespace App\Filament\Resources\Features\Schemas;

use App\Enums\FeatureLayer;
use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use App\Models\Flowchart;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FeatureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('功能')
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(FeatureStatus::class)
                    ->required(),
                CheckboxList::make('layers')
                    ->label('层')
                    ->options(FeatureLayer::class),
                CheckboxList::make('triggers')
                    ->label('触发方式')
                    ->options(FeatureTrigger::class),
                TextInput::make('entry')
                    ->label('入口'),
                Select::make('use_case_id')
                    ->label('Use case')
                    ->relationship(
                        name: 'useCase',
                        titleAttribute: 'goal',
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn ($record): string => "#{$record->id} · {$record->actor} · {$record->goal}",
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                self::moduleSelect(),
                self::flowchartSection(),
            ]);
    }

    public static function configureForUseCase(Schema $schema, int $useCaseId): Schema
    {
        return $schema
            ->components([
                Hidden::make('use_case_id')
                    ->default($useCaseId),
                TextInput::make('title')
                    ->label('功能')
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(FeatureStatus::class)
                    ->required(),
                CheckboxList::make('layers')
                    ->label('层')
                    ->options(FeatureLayer::class),
                CheckboxList::make('triggers')
                    ->label('触发方式')
                    ->options(FeatureTrigger::class),
                TextInput::make('entry')
                    ->label('入口'),
                self::moduleSelect(),
                self::flowchartSection(),
            ]);
    }

    private static function flowchartSection(): Section
    {
        return Section::make('流程图')
            ->relationship('flowchart', condition: fn (?array $state): bool => filled($state['chart'] ?? null))
            ->columnSpanFull()
            ->schema([
                Textarea::make('chart')
                    ->label('Chart JSON')
                    ->helperText('{"nodes":[{"id","label","shape":"start|step|decision|end|io","file"?,"function"?}],"edges":[{"from","to","label"?,"kind"?:"next|failure"}]}')
                    ->rows(15)
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->formatStateUsing(fn (mixed $state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null : $state)
                    ->dehydrateStateUsing(fn (?string $state): mixed => filled($state) ? json_decode($state, true) : null)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (blank($value)) {
                            return;
                        }

                        $chart = json_decode((string) $value, true);
                        $error = $chart === null ? '不是合法 JSON' : Flowchart::chartError($chart);

                        if ($error !== null) {
                            $fail($error);
                        }
                    }),
                Textarea::make('pseudocode')
                    ->label('伪代码')
                    ->rows(15)
                    ->extraInputAttributes(['class' => 'font-mono']),
            ]);
    }

    private static function moduleSelect(): Select
    {
        return Select::make('module_id')
            ->label('模块')
            ->relationship(
                'module',
                'name',
                fn (Builder $query, Get $get): Builder => $query->whereHas(
                    'useCases',
                    fn (Builder $query): Builder => $query->whereKey($get('use_case_id')),
                ),
            )
            ->helperText('留空时，Use Case 只挂一个模块就自动取它。')
            ->preload();
    }
}
