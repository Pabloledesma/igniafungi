<?php

namespace App\Filament\Resources\BatchPhaseResource\Pages;

use App\Filament\Resources\BatchPhaseResource;
use Filament\Resources\Pages\ListRecords;

class ListBatchPhases extends ListRecords
{
    protected static string $resource = BatchPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No Create Action
        ];
    }
}
