<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Resources\Batches\BatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBatch extends CreateRecord
{
    protected static string $resource = BatchResource::class;

    protected function afterCreate(): void
    {
        $batch = $this->record;
        
        // Registramos la fase inicial en el historial de fases (pivote)
        $batch->phases()->attach($batch->phase_id, [
            'started_at' => now(),
        ]);
    }
}
