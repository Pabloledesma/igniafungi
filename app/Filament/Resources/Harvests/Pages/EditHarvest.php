<?php

namespace App\Filament\Resources\Harvests\Pages;

use App\Filament\Resources\Harvests\HarvestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHarvest extends EditRecord
{
    protected static string $resource = HarvestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
