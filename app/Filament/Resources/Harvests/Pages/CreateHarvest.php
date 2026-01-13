<?php

namespace App\Filament\Resources\Harvests\Pages;

use App\Filament\Resources\Harvests\HarvestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHarvest extends CreateRecord
{
    protected static string $resource = HarvestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
