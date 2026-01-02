<?php

namespace App\Filament\Resources\Strains\Pages;

use App\Filament\Resources\Strains\StrainResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStrain extends EditRecord
{
    protected static string $resource = StrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
