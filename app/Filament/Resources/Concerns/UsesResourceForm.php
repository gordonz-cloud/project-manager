<?php

namespace App\Filament\Resources\Concerns;

use Filament\Schemas\Schema;

trait UsesResourceForm
{
    /**
     * @return class-string
     */
    abstract protected static function relatedResource(): string;

    public function form(Schema $schema): Schema
    {
        $resource = static::relatedResource();

        return $resource::form($schema);
    }
}
