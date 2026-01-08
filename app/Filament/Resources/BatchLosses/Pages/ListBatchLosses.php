<?php

namespace App\Filament\Resources\BatchLosses\Pages;

use App\Filament\Resources\BatchLosses\BatchLossResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBatchLosses extends ListRecords
{
    protected static string $resource = BatchLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
