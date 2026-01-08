<?php

namespace App\Filament\Resources\BatchLosses\Pages;

use App\Filament\Resources\BatchLosses\BatchLossResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBatchLoss extends EditRecord
{
    protected static string $resource = BatchLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
