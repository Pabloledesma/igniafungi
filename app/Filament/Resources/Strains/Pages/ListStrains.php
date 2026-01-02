<?php

namespace App\Filament\Resources\Strains\Pages;

use App\Filament\Resources\Strains\StrainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStrains extends ListRecords
{
    protected static string $resource = StrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
