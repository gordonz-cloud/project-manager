<?php

namespace App\Filament\Resources\Commits\Pages;

use App\Filament\Resources\Commits\CommitResource;
use Filament\Resources\Pages\ListRecords;

class ListCommits extends ListRecords
{
    protected static string $resource = CommitResource::class;
}
